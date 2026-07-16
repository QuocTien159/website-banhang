<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\GhnShipmentService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Throwable;

class GhnWebhookController extends Controller
{
    public function orderStatus(Request $request, GhnShipmentService $shipmentService)
    {
        $secret = trim((string) config('services.ghn.webhook_secret'));
        if ($secret === '') {
            return response()->json(['message' => 'GHN webhook chưa được cấu hình.'], 503);
        }

        if (!$this->isAuthorized($request, $secret)) {
            return response()->json(['message' => 'Webhook GHN không hợp lệ.'], 403);
        }

        $payload = $request->json()->all() ?: $request->all();
        if (!is_array($payload) || $payload === []) {
            return response()->json(['message' => 'Webhook GHN thiếu dữ liệu.'], 422);
        }

        try {
            $shipment = $shipmentService->handleWebhook($payload);
            if (!$shipment) {
                return response()->json(['accepted' => true, 'ignored' => true]);
            }

            return response()->json([
                'accepted' => true,
                'shipment_id' => $shipment->ma_van_chuyen,
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            // Return a retryable error for a valid callback that could not be persisted.
            return response()->json(['message' => 'Không thể lưu trạng thái GHN ở thời điểm này.'], 500);
        }
    }

    private function isAuthorized(Request $request, string $secret): bool
    {
        $provided = (string) (
            $request->header('X-GHN-Webhook-Secret')
            ?: $request->bearerToken()
            ?: $request->query('token')
        );

        if ($provided !== '' && hash_equals($secret, $provided)) {
            return true;
        }

        $signature = (string) $request->header('X-GHN-Signature');
        if ($signature === '') {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $request->getContent(), $secret), $signature);
    }
}
