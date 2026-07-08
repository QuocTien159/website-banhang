<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Models\PaymentLog;
use App\Services\PayOsService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function payosWebhook(Request $request, PayOsService $payOsService)
    {
        $payload = $request->all();
        $verified = $payOsService->verifyWebhook($payload);
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $order = null;

        if (isset($data['orderCode'])) {
            $order = DonHang::where('payos_order_code', (int) $data['orderCode'])->first();
        }

        $log = PaymentLog::create([
            'ma_dh' => $order?->ma_dh,
            'provider' => 'payos',
            'event_type' => $this->eventType($payload, $verified),
            'raw_payload' => $payload,
            'verified' => $verified,
        ]);

        if (!$verified) {
            return response()->json(['message' => 'Invalid signature'], 400);
        }

        if (!$order) {
            $log->update(['event_type' => 'payos_webhook_order_not_found']);
            return response()->json(['message' => 'Webhook ignored: order not found']);
        }

        if (!$this->isSuccessPayload($payload, $data)) {
            return response()->json(['message' => 'Webhook received']);
        }

        $amount = (int) ($data['amount'] ?? 0);
        if ($amount !== (int) round((float) $order->tong_tien)) {
            $log->update(['event_type' => 'payos_webhook_amount_mismatch']);
            return response()->json(['message' => 'Invalid amount'], 400);
        }

        if ($order->payment_link_id && isset($data['paymentLinkId']) && $order->payment_link_id !== $data['paymentLinkId']) {
            $log->update(['event_type' => 'payos_webhook_payment_link_mismatch']);
            return response()->json(['message' => 'Invalid payment link'], 400);
        }

        $payOsService->markOrderPaid($order);

        return response()->json(['message' => 'OK']);
    }

    private function isSuccessPayload(array $payload, array $data): bool
    {
        return ($payload['success'] ?? false) === true
            && ($payload['code'] ?? null) === '00'
            && ($data['code'] ?? null) === '00';
    }

    private function eventType(array $payload, bool $verified): string
    {
        if (!$verified) {
            return 'payos_webhook_invalid';
        }

        if (($payload['success'] ?? false) === true && ($payload['code'] ?? null) === '00') {
            return 'payos_webhook_paid';
        }

        return 'payos_webhook';
    }
}
