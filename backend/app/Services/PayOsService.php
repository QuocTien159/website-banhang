<?php

namespace App\Services;

use App\Models\DonHang;
use App\Models\PaymentLog;
use App\Support\OrderStatus;
use App\Support\PaymentStatus;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class PayOsService
{
    public function createPaymentLink(DonHang $order): array
    {
        $this->ensureConfigured();

        $orderCode = $order->payos_order_code ?: $this->makeOrderCode($order);
        $amount = (int) round((float) $order->tong_tien);
        $description = mb_substr('DH'.$orderCode, 0, 25);
        $returnUrl = $this->callbackUrl(config('services.payos.return_url'), $order, $orderCode, 'return');
        $cancelUrl = $this->callbackUrl(config('services.payos.cancel_url'), $order, $orderCode, 'cancel');

        $payload = [
            'orderCode' => $orderCode,
            'amount' => $amount,
            'description' => $description,
            'returnUrl' => $returnUrl,
            'cancelUrl' => $cancelUrl,
        ];
        $payload['signature'] = $this->signature($payload);

        $response = Http::acceptJson()
            ->withOptions($this->httpOptions())
            ->withHeaders([
                'x-client-id' => config('services.payos.client_id'),
                'x-api-key' => config('services.payos.api_key'),
            ])
            ->post(rtrim(config('services.payos.base_url'), '/').'/v2/payment-requests', $payload);

        if (!$response->successful()) {
            throw new RuntimeException('Không thể tạo link thanh toán payOS.');
        }

        $body = $response->json();
        if (($body['code'] ?? null) !== '00' || !isset($body['data'])) {
            throw new RuntimeException($body['desc'] ?? 'payOS từ chối tạo link thanh toán.');
        }

        $data = $body['data'];
        $order->forceFill([
            'payment_provider' => 'payos',
            'payos_order_code' => $orderCode,
            'payment_link_id' => $data['paymentLinkId'] ?? null,
            'payment_checkout_url' => $data['checkoutUrl'] ?? null,
            'noi_dung_chuyen_khoan' => $description,
            'qr_code_url' => $data['qrCode'] ?? $data['checkoutUrl'] ?? null,
        ])->save();

        return $data;
    }

    public function verifyWebhook(array $payload): bool
    {
        $signature = (string) ($payload['signature'] ?? '');
        $data = $payload['data'] ?? null;

        if ($signature === '' || !is_array($data)) {
            return false;
        }

        return hash_equals($this->signature($data), $signature);
    }

    public function getPaymentLinkInfo(DonHang $order): ?array
    {
        $this->ensureConfigured();

        $id = $order->payment_link_id ?: $order->payos_order_code;
        if (!$id) {
            return null;
        }

        $response = Http::acceptJson()
            ->withOptions($this->httpOptions())
            ->withHeaders([
                'x-client-id' => config('services.payos.client_id'),
                'x-api-key' => config('services.payos.api_key'),
            ])
            ->get(rtrim(config('services.payos.base_url'), '/').'/v2/payment-requests/'.rawurlencode((string) $id));

        if (!$response->successful()) {
            throw new RuntimeException('Không thể lấy trạng thái thanh toán payOS.');
        }

        $body = $response->json();
        if (($body['code'] ?? null) !== '00' || !is_array($body['data'] ?? null)) {
            throw new RuntimeException($body['desc'] ?? 'payOS từ chối trả trạng thái thanh toán.');
        }

        return $body['data'];
    }

    public function syncPaymentStatus(DonHang $order): DonHang
    {
        if ($order->payment_provider !== 'payos' || $order->trang_thai_thanh_toan === PaymentStatus::PAID) {
            return $order;
        }

        try {
            $data = $this->getPaymentLinkInfo($order);
        } catch (Throwable $exception) {
            PaymentLog::create([
                'ma_dh' => $order->ma_dh,
                'provider' => 'payos',
                'event_type' => 'payos_status_sync_failed',
                'raw_payload' => ['message' => $exception->getMessage()],
                'verified' => false,
            ]);

            return $order;
        }

        if (!$data) {
            return $order;
        }

        PaymentLog::create([
            'ma_dh' => $order->ma_dh,
            'provider' => 'payos',
            'event_type' => 'payos_status_sync',
            'raw_payload' => $data,
            'verified' => true,
        ]);

        $status = strtoupper((string) ($data['status'] ?? ''));
        $expectedAmount = (int) round((float) $order->tong_tien);
        $payosAmount = (int) ($data['amount'] ?? 0);
        $amountPaid = (int) ($data['amountPaid'] ?? 0);

        if ($status === 'PAID' || $amountPaid >= $expectedAmount) {
            if ($payosAmount !== $expectedAmount && $amountPaid !== $expectedAmount) {
                PaymentLog::create([
                    'ma_dh' => $order->ma_dh,
                    'provider' => 'payos',
                    'event_type' => 'payos_status_sync_amount_mismatch',
                    'raw_payload' => $data,
                    'verified' => true,
                ]);

                return $order->fresh();
            }

            return $this->markOrderPaid($order);
        }

        if ($status === 'CANCELLED') {
            $order->update(['trang_thai_thanh_toan' => PaymentStatus::CANCELLED]);
            return $order->fresh();
        }

        if ($status === 'EXPIRED') {
            $order->update(['trang_thai_thanh_toan' => PaymentStatus::EXPIRED]);
            return $order->fresh();
        }

        return $order->fresh();
    }

    public function markOrderPaid(DonHang $order): DonHang
    {
        DB::transaction(function () use ($order) {
            $lockedOrder = DonHang::where('ma_dh', $order->ma_dh)->lockForUpdate()->firstOrFail();

            if ($lockedOrder->trang_thai_thanh_toan === PaymentStatus::PAID) {
                return;
            }

            $updates = [
                'payment_provider' => 'payos',
                'trang_thai_thanh_toan' => PaymentStatus::PAID,
                'thanh_toan_xac_nhan_at' => now(),
                'paid_at' => now(),
            ];

            if ($lockedOrder->trang_thai === OrderStatus::PENDING) {
                $updates['trang_thai'] = OrderStatus::CONFIRMED;
            }

            $lockedOrder->update($updates);
        });

        return $order->fresh();
    }

    public function signature(array $data): string
    {
        $checksumKey = (string) config('services.payos.checksum_key');
        $normalized = Arr::except($data, ['signature']);
        ksort($normalized);

        $raw = collect($normalized)
            ->map(fn ($value, $key) => $key.'='.$this->normalizeValue($value))
            ->implode('&');

        return hash_hmac('sha256', $raw, $checksumKey);
    }

    private function ensureConfigured(): void
    {
        foreach (['client_id', 'api_key', 'checksum_key'] as $key) {
            if (!config("services.payos.$key")) {
                throw new RuntimeException('Thiếu cấu hình payOS. Vui lòng kiểm tra PAYOS_CLIENT_ID, PAYOS_API_KEY và PAYOS_CHECKSUM_KEY.');
            }
        }
    }

    private function httpOptions(): array
    {
        $caBundle = config('services.payos.ca_bundle');

        if ($caBundle && is_file($caBundle)) {
            return ['verify' => $caBundle];
        }

        return [];
    }

    private function makeOrderCode(DonHang $order): int
    {
        $digits = (int) preg_replace('/\D+/', '', $order->ma_dh);

        return $digits > 0 ? $digits : random_int(100000, 999999999);
    }

    private function callbackUrl(?string $url, DonHang $order, int $orderCode, string $result): string
    {
        $base = trim((string) $url);
        if ($base === '') {
            $frontendUrl = rtrim((string) config('services.payos.frontend_url'), '/');
            $base = "{$frontendUrl}/account/orders/{order_id}/qr-payment";
        }

        $base = str_replace([
            '{order_id}',
            '{order_code}',
            '{payos_order_code}',
            '{result}',
        ], [
            rawurlencode($order->ma_dh),
            rawurlencode($order->ma_dh),
            rawurlencode((string) $orderCode),
            rawurlencode($result),
        ], $base);

        $params = [];
        if (!str_contains($base, 'orderId=')) {
            $params[] = 'orderId='.rawurlencode($order->ma_dh);
        }
        if (!str_contains($base, 'payosResult=')) {
            $params[] = 'payosResult='.rawurlencode($result);
        }

        if ($params === []) {
            return $base;
        }

        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.implode('&', $params);
    }

    private function normalizeValue(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return (string) $value;
    }
}
