<?php

namespace App\Services;

use App\Models\DonHang;
use App\Models\LichSuXuLyDonHang;
use App\Models\PaymentShippingSetting;
use App\Models\SuKienVanChuyen;
use App\Models\VanDonVanChuyen;
use App\Support\OrderStatus;
use App\Support\PaymentStatus;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * Owns the GHN fulfilment boundary. Inventory is intentionally not touched
 * here: stock is reserved during checkout and can only be returned through
 * the explicit internal cancellation/returns flows.
 */
class GhnShipmentService
{
    public function __construct(
        private readonly GhnShippingService $ghn,
        private readonly GhnShipmentStatusMapper $statusMapper,
    ) {
    }

    public function handoff(string $orderId, ?string $actorId, bool $retry = false): VanDonVanChuyen
    {
        $setting = PaymentShippingSetting::current();
        $context = DB::transaction(function () use ($orderId, $actorId, $retry, $setting) {
            /** @var DonHang $order */
            $order = DonHang::with(['chiTiets.bienThe.sanPham'])
                ->where('ma_dh', $orderId)
                ->lockForUpdate()
                ->firstOrFail();

            $this->assertCanHandoff($order, $setting);

            $shipment = VanDonVanChuyen::where('ma_dh', $order->ma_dh)->lockForUpdate()->first();
            if ($shipment?->ma_van_don_ghn) {
                return ['shipment' => $shipment, 'payload' => null];
            }

            if ($shipment?->trang_thai_tao === 'dang_tao') {
                throw ValidationException::withMessages([
                    'shipping' => 'GHN đang xử lý yêu cầu tạo vận đơn. Hãy đồng bộ lại sau ít phút thay vì gửi lại.',
                ]);
            }
            if ($shipment?->trang_thai_tao === 'cho_xac_minh') {
                throw ValidationException::withMessages([
                    'shipping' => 'Chưa xác minh được phản hồi tạo vận đơn từ GHN. Không gửi lại để tránh tạo trùng; hãy kiểm tra GHN trước.',
                ]);
            }

            $payload = $this->buildCreatePayload($order, $setting);
            if (!$shipment) {
                $shipment = VanDonVanChuyen::create([
                    'ma_dh' => $order->ma_dh,
                    'nha_van_chuyen' => 'ghn',
                    'moi_truong' => (string) config('services.ghn.env', 'sandbox'),
                    'ma_don_khach_hang' => $order->ma_dh,
                    'trang_thai_tao' => 'chua_tao',
                    'so_lan_tao' => 0,
                    'ngay_tao' => now(),
                    'ngay_cap_nhat' => now(),
                ]);
            }

            if ($order->trang_thai === OrderStatus::PREPARING) {
                $oldStatus = $order->trang_thai;
                $order->update(['trang_thai' => OrderStatus::READY_TO_SHIP]);
                $this->recordOrderHistory(
                    $order,
                    $oldStatus,
                    OrderStatus::READY_TO_SHIP,
                    $actorId,
                    'noi_bo',
                    $shipment->ma_van_chuyen,
                    'Đã sẵn sàng bàn giao GHN.'
                );
            }

            $shipment->update([
                'trang_thai_tao' => 'dang_tao',
                'so_lan_tao' => (int) $shipment->so_lan_tao + 1,
                'lan_tao_cuoi' => now(),
                'loi_dong_bo_cuoi' => null,
                'du_lieu_gui' => $this->sanitizePayload($payload),
                'ngay_cap_nhat' => now(),
            ]);

            $this->recordShippingEvent(
                $shipment,
                'noi_bo',
                'create_requested',
                'create_requested',
                now(),
                ['action' => 'create_shipment', 'retry' => $retry, 'attempt' => (int) $shipment->so_lan_tao],
                $retry ? 'Đã gửi lại yêu cầu tạo vận đơn GHN.' : 'Đã gửi yêu cầu tạo vận đơn GHN.',
                false
            );
            $this->recordOrderHistory(
                $order,
                $order->trang_thai,
                $order->trang_thai,
                $actorId,
                'noi_bo',
                $shipment->ma_van_chuyen,
                $retry ? 'Đã yêu cầu tạo lại vận đơn GHN.' : 'Đã bàn giao đơn để GHN tạo vận đơn.'
            );

            return ['shipment' => $shipment->fresh(), 'payload' => $payload];
        });

        /** @var VanDonVanChuyen $shipment */
        $shipment = $context['shipment'];
        if (!$context['payload']) {
            return $shipment;
        }

        try {
            $response = $this->ghn->createOrder($context['payload'], $setting);
            $orderCode = $this->providerOrderCode($response);
            if (!$orderCode) {
                throw new RuntimeException('GHN không trả về mã vận đơn.');
            }

            // Lead time is useful but must not turn a successfully-created shipment into a failed one.
            $estimated = $this->estimatedDelivery($context['payload'], $setting);
            if ($estimated) {
                $response['leadtime'] = $estimated['leadtime'] ?? $estimated['expected_delivery_time'] ?? null;
            }

            $response['order_code'] = $orderCode;
            $response['status'] = $response['status'] ?? 'ready_to_pick';

            return $this->applyProviderUpdate(
                $shipment->ma_van_chuyen,
                $response,
                'ghn_create',
                $actorId,
                false
            );
        } catch (ConnectionException $exception) {
            $this->markCreationUncertain($shipment->ma_van_chuyen, $exception);

            throw new RuntimeException(
                'Không xác minh được phản hồi GHN. Hệ thống chưa gửi lại để tránh tạo trùng vận đơn.',
                previous: $exception
            );
        } catch (Throwable $exception) {
            $this->markCreationFailed($shipment->ma_van_chuyen, $exception);

            throw new RuntimeException(
                $retry
                    ? 'GHN chưa tạo được vận đơn lần này. Đơn vẫn ở trạng thái sẵn sàng bàn giao để có thể thử lại.'
                    : 'GHN chưa tạo được vận đơn. Đơn vẫn được giữ nguyên để bạn kiểm tra và thử lại.',
                previous: $exception
            );
        }
    }

    public function sync(string $orderId, ?string $actorId = null): VanDonVanChuyen
    {
        $shipment = VanDonVanChuyen::where('ma_dh', $orderId)->firstOrFail();
        if (!$shipment->ma_van_don_ghn) {
            throw ValidationException::withMessages([
                'shipping' => 'Đơn chưa có mã vận đơn GHN để đồng bộ.',
            ]);
        }

        try {
            $response = $this->ghn->orderDetail($shipment->ma_van_don_ghn);
            $response['order_code'] = $response['order_code'] ?? $shipment->ma_van_don_ghn;

            $shipment = $this->applyProviderUpdate($shipment->ma_van_chuyen, $response, 'ghn_sync');
            if ($actorId) {
                $this->recordManualSync($shipment, $actorId);
            }

            return $shipment;
        } catch (Throwable $exception) {
            $this->markSyncFailed($shipment->ma_van_chuyen, $exception);
            throw new RuntimeException('Không thể đồng bộ trạng thái GHN ở thời điểm này.', previous: $exception);
        }
    }

    public function requestCancellation(string $orderId, ?string $actorId): VanDonVanChuyen
    {
        $shipment = VanDonVanChuyen::where('ma_dh', $orderId)->firstOrFail();
        if (!$shipment->ma_van_don_ghn) {
            throw ValidationException::withMessages(['shipping' => 'Đơn chưa có vận đơn GHN để yêu cầu hủy.']);
        }
        if ($shipment->isTerminal()) {
            throw ValidationException::withMessages(['shipping' => 'Vận đơn đã ở trạng thái kết thúc, không thể gửi yêu cầu hủy.']);
        }

        try {
            $response = $this->ghn->cancelOrder($shipment->ma_van_don_ghn);
        } catch (Throwable $exception) {
            $this->markSyncFailed($shipment->ma_van_chuyen, $exception);
            throw new RuntimeException('GHN chưa nhận được yêu cầu hủy vận đơn.', previous: $exception);
        }

        DB::transaction(function () use ($shipment, $response, $actorId) {
            $locked = VanDonVanChuyen::where('ma_van_chuyen', $shipment->ma_van_chuyen)->lockForUpdate()->firstOrFail();
            $this->recordShippingEvent(
                $locked,
                'noi_bo',
                'cancel_requested',
                'cancel_requested',
                now(),
                ['response' => $response],
                'Đã gửi yêu cầu hủy vận đơn GHN.',
                false
            );
            $locked->update([
                'ngay_dong_bo' => now(),
                'loi_dong_bo_cuoi' => null,
                'ngay_cap_nhat' => now(),
            ]);

            $order = DonHang::where('ma_dh', $locked->ma_dh)->lockForUpdate()->firstOrFail();
            $this->recordOrderHistory(
                $order,
                $order->trang_thai,
                $order->trang_thai,
                $actorId,
                'noi_bo',
                $locked->ma_van_chuyen,
                'Đã gửi yêu cầu hủy vận đơn GHN; chờ GHN xác nhận.'
            );
        });

        return $shipment->fresh();
    }

    /** @param array<string, mixed> $payload */
    public function handleWebhook(array $payload): ?VanDonVanChuyen
    {
        $orderCode = $this->providerOrderCode($payload);
        $clientOrderCode = $this->clientOrderCode($payload);

        if (!$orderCode && !$clientOrderCode) {
            throw ValidationException::withMessages(['shipping' => 'Webhook GHN thiếu mã vận đơn.']);
        }

        // Prefer the carrier code. Falling back to the client code is useful
        // for GHN callbacks that arrive immediately after order creation.
        $shipment = $orderCode
            ? VanDonVanChuyen::where('ma_van_don_ghn', $orderCode)->first()
            : null;

        if (!$shipment && $clientOrderCode) {
            $shipment = VanDonVanChuyen::where('ma_don_khach_hang', $clientOrderCode)->first();
        }

        if (!$shipment) {
            return null;
        }

        if ($shipment->ma_van_don_ghn && $orderCode && !hash_equals($shipment->ma_van_don_ghn, $orderCode)) {
            throw ValidationException::withMessages([
                'shipping' => 'Webhook GHN có mã vận đơn không khớp với đơn hàng đã lưu.',
            ]);
        }

        if ($clientOrderCode && !hash_equals($shipment->ma_don_khach_hang, $clientOrderCode)) {
            throw ValidationException::withMessages([
                'shipping' => 'Webhook GHN có mã đơn khách hàng không khớp.',
            ]);
        }

        return $this->applyProviderUpdate($shipment->ma_van_chuyen, $payload, 'ghn_webhook');
    }

    /** @param array<string, mixed> $payload */
    public function applyProviderUpdate(
        string $shipmentId,
        array $payload,
        string $source,
        ?string $actorId = null,
        bool $fromWebhook = true,
    ): VanDonVanChuyen {
        return DB::transaction(function () use ($shipmentId, $payload, $source, $actorId, $fromWebhook) {
            $shipment = VanDonVanChuyen::where('ma_van_chuyen', $shipmentId)->lockForUpdate()->firstOrFail();
            $order = DonHang::where('ma_dh', $shipment->ma_dh)->lockForUpdate()->firstOrFail();

            $rawStatus = $this->providerStatus($payload);
            $normalizedStatus = $this->statusMapper->normalize($rawStatus);
            $providerEventTime = $this->providerEventTime($payload);
            $eventTime = $providerEventTime ?? now();
            $payloadHash = $this->payloadHash($payload);
            $isStale = $fromWebhook
                && $providerEventTime
                && $shipment->ngay_cap_nhat_ghn
                && $providerEventTime->lt($shipment->ngay_cap_nhat_ghn);

            $existingEvent = SuKienVanChuyen::where('ma_van_chuyen', $shipment->ma_van_chuyen)
                ->where('ma_bam_payload', $payloadHash)
                ->first();
            if ($existingEvent) {
                return $shipment;
            }

            $this->recordShippingEvent(
                $shipment,
                $source,
                $rawStatus,
                $normalizedStatus,
                $eventTime,
                $payload,
                $isStale ? 'Sự kiện GHN cũ hơn trạng thái hiện tại nên chỉ lưu để đối soát.' : null,
                $isStale,
                $payloadHash
            );

            if ($isStale) {
                return $shipment;
            }

            $orderCode = $this->providerOrderCode($payload) ?: $shipment->ma_van_don_ghn;
            $fee = $this->providerFee($payload);
            $expectedDelivery = $this->providerExpectedDelivery($payload);

            $shipment->update([
                'ma_van_don_ghn' => $orderCode,
                'trang_thai_ghn_goc' => $rawStatus,
                'trang_thai_van_chuyen' => $normalizedStatus,
                // Only provider timestamps participate in stale-callback checks.
                // A local create/sync timestamp must never cause a fresh webhook to be discarded.
                'ngay_cap_nhat_ghn' => $providerEventTime ?? $shipment->ngay_cap_nhat_ghn,
                'phi_van_chuyen' => $fee ?? $shipment->phi_van_chuyen,
                'thoi_gian_giao_du_kien' => $expectedDelivery ?? $shipment->thoi_gian_giao_du_kien,
                'du_lieu_phan_hoi' => $this->sanitizePayload($payload),
                'loi_dong_bo_cuoi' => null,
                'ngay_dong_bo' => now(),
                'trang_thai_tao' => $orderCode ? 'da_tao' : $shipment->trang_thai_tao,
                'ngay_cap_nhat' => now(),
            ]);

            $feeBreakdown = is_array($order->shipping_fee_breakdown) ? $order->shipping_fee_breakdown : [];
            if ($fee !== null) {
                $feeBreakdown['ghn_actual_fee'] = $fee;
            }
            if ($expectedDelivery) {
                $feeBreakdown['ghn_expected_delivery_at'] = $expectedDelivery->toISOString();
            }
            $order->update([
                'shipping_provider' => 'ghn',
                'shipping_order_code' => $orderCode,
                'shipping_status' => $rawStatus ?: $normalizedStatus,
                'shipping_expected_delivery_at' => $expectedDelivery ?? $order->shipping_expected_delivery_at,
                'shipping_fee_breakdown' => $feeBreakdown,
            ]);

            $this->applyInternalStatusFromShipping($order, $shipment->fresh(), $normalizedStatus, $source, $actorId);

            return $shipment->fresh();
        });
    }

    private function assertCanHandoff(DonHang $order, PaymentShippingSetting $setting): void
    {
        if (!in_array($order->trang_thai, [OrderStatus::PREPARING, OrderStatus::READY_TO_SHIP], true)) {
            throw ValidationException::withMessages([
                'shipping' => 'Chỉ đơn đang chuẩn bị hoặc sẵn sàng bàn giao mới có thể tạo vận đơn GHN.',
            ]);
        }

        if ($order->phuong_thuc_tt !== 'cod' && $order->trang_thai_thanh_toan !== PaymentStatus::PAID) {
            throw ValidationException::withMessages([
                'payment' => 'Đơn thanh toán trước cần được xác nhận đã thanh toán trước khi bàn giao GHN.',
            ]);
        }

        if ($setting->shipping_provider !== 'ghn' || !$setting->ghn_enabled) {
            throw ValidationException::withMessages(['shipping' => 'Vận chuyển GHN hiện chưa được bật trong cấu hình quản trị.']);
        }
        if (!$this->ghn->isConfigured($setting)) {
            throw ValidationException::withMessages(['shipping' => 'Thiếu cấu hình GHN (Token hoặc Shop ID).']);
        }
        if (!$this->ghn->hasFulfillmentPickupAddress($setting)) {
            throw ValidationException::withMessages(['shipping' => 'Thiếu thông tin kho lấy hàng GHN: tên, số điện thoại hoặc địa chỉ.']);
        }
    }

    /** @return array<string, mixed> */
    private function buildCreatePayload(DonHang $order, PaymentShippingSetting $setting): array
    {
        $recipient = $this->recipient($order);
        $errors = [];
        foreach ([
            'recipient_name' => $recipient['name'],
            'recipient_phone' => $recipient['phone'],
            'recipient_address' => $order->dia_chi_chi_tiet,
            'province_code' => $order->ma_tinh_thanh,
            'district_code' => $order->ma_quan_huyen,
            'ward_code' => $order->ma_phuong_xa,
        ] as $field => $value) {
            if (!filled($value)) {
                $errors[$field] = 'Thông tin giao hàng GHN còn thiếu.';
            }
        }
        if (!$order->chiTiets->count()) {
            $errors['items'] = 'Đơn hàng chưa có sản phẩm để tạo vận đơn GHN.';
        }
        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        $pickup = $this->ghn->pickupDetails($setting);
        $items = $order->chiTiets->map(function ($line) use ($setting) {
            $quantity = max(1, (int) $line->so_luong);
            $name = trim((string) ($line->bienThe?->sanPham?->ten_sp ?: $line->ma_bien_the));

            return [
                'name' => mb_substr($name, 0, 200),
                'code' => mb_substr((string) ($line->bienThe?->sku ?: $line->ma_bien_the), 0, 50),
                'quantity' => $quantity,
                'price' => max(0, (int) round((float) $line->don_gia)),
                'length' => max(1, (int) ($setting->default_length_cm ?: 25)),
                'width' => max(1, (int) ($setting->default_width_cm ?: 20)),
                'height' => max(1, (int) ($setting->default_height_cm ?: 10)),
                'weight' => max(1, (int) ($setting->default_weight_gram ?: 500)),
            ];
        })->values()->all();
        $dimensions = $this->ghn->dimensions($items, $setting);

        $payload = [
            'payment_type_id' => $order->phuong_thuc_tt === 'cod' ? 2 : 1,
            'note' => mb_substr((string) ($order->ghi_chu ?: 'Giao hàng TienProSport'), 0, 500),
            'required_note' => 'CHOXEMHANGKHONGTHU',
            'from_name' => $pickup['name'],
            'from_phone' => $pickup['phone'],
            'from_address' => $pickup['address'],
            'from_ward_code' => $pickup['ward_code'],
            'from_district_id' => $pickup['district_id'],
            'return_name' => $pickup['name'],
            'return_phone' => $pickup['phone'],
            'return_address' => $pickup['address'],
            'return_ward_code' => $pickup['ward_code'],
            'return_district_id' => $pickup['district_id'],
            'to_name' => $recipient['name'],
            'to_phone' => $recipient['phone'],
            'to_address' => $order->dia_chi_chi_tiet,
            'to_ward_code' => $order->ma_phuong_xa,
            'to_district_id' => (int) $order->ma_quan_huyen,
            'cod_amount' => $order->phuong_thuc_tt === 'cod' ? (int) round((float) $order->tong_tien) : 0,
            'content' => mb_substr('Đơn '.$order->ma_dh, 0, 500),
            'weight' => $dimensions['weight'],
            'length' => $dimensions['length'],
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
            'service_type_id' => $order->shipping_service_type_id ? (int) $order->shipping_service_type_id : 2,
            'insurance_value' => min(5000000, max(0, (int) round((float) $order->tam_tinh))),
            'client_order_code' => $order->ma_dh,
            'items' => $items,
        ];

        if ($order->shipping_service_id) {
            $payload['service_id'] = (int) $order->shipping_service_id;
        }

        return $payload;
    }

    /** @return array{name: string, phone: string} */
    private function recipient(DonHang $order): array
    {
        $parts = array_map('trim', explode('|', (string) $order->dia_chi_giao));

        return [
            'name' => $parts[0] ?? '',
            'phone' => preg_replace('/\s+/', '', $parts[1] ?? '') ?: '',
        ];
    }

    /** @param array<string, mixed> $payload */
    private function estimatedDelivery(array $payload, PaymentShippingSetting $setting): ?array
    {
        try {
            $leadtimePayload = array_filter([
                'from_district_id' => $payload['from_district_id'],
                'from_ward_code' => $payload['from_ward_code'],
                'to_district_id' => $payload['to_district_id'],
                'to_ward_code' => $payload['to_ward_code'],
                'service_id' => $payload['service_id'] ?? null,
                'service_type_id' => $payload['service_type_id'],
            ], fn ($value) => $value !== null);

            return $this->ghn->estimateDelivery($leadtimePayload, $setting);
        } catch (Throwable) {
            return null;
        }
    }

    private function markCreationFailed(string $shipmentId, Throwable $exception): void
    {
        DB::transaction(function () use ($shipmentId, $exception) {
            $shipment = VanDonVanChuyen::where('ma_van_chuyen', $shipmentId)->lockForUpdate()->first();
            if (!$shipment || $shipment->ma_van_don_ghn) {
                return;
            }

            $shipment->update([
                'trang_thai_tao' => 'that_bai',
                'loi_dong_bo_cuoi' => $this->shortError($exception),
                'ngay_dong_bo' => now(),
                'ngay_cap_nhat' => now(),
            ]);
        });
    }

    private function recordManualSync(VanDonVanChuyen $shipment, string $actorId): void
    {
        DB::transaction(function () use ($shipment, $actorId) {
            $lockedShipment = VanDonVanChuyen::where('ma_van_chuyen', $shipment->ma_van_chuyen)->lockForUpdate()->firstOrFail();
            $order = DonHang::where('ma_dh', $lockedShipment->ma_dh)->lockForUpdate()->firstOrFail();
            $this->recordOrderHistory(
                $order,
                $order->trang_thai,
                $order->trang_thai,
                $actorId,
                'noi_bo',
                $lockedShipment->ma_van_chuyen,
                'Đã yêu cầu đồng bộ trạng thái GHN.'
            );
        });
    }

    private function markCreationUncertain(string $shipmentId, Throwable $exception): void
    {
        DB::transaction(function () use ($shipmentId, $exception) {
            $shipment = VanDonVanChuyen::where('ma_van_chuyen', $shipmentId)->lockForUpdate()->first();
            if (!$shipment || $shipment->ma_van_don_ghn) {
                return;
            }

            $shipment->update([
                'trang_thai_tao' => 'cho_xac_minh',
                'loi_dong_bo_cuoi' => 'Chưa xác minh được phản hồi GHN: '.$this->shortError($exception),
                'ngay_dong_bo' => now(),
                'ngay_cap_nhat' => now(),
            ]);
        });
    }

    private function markSyncFailed(string $shipmentId, Throwable $exception): void
    {
        VanDonVanChuyen::where('ma_van_chuyen', $shipmentId)->update([
            'loi_dong_bo_cuoi' => $this->shortError($exception),
            'ngay_dong_bo' => now(),
            'ngay_cap_nhat' => now(),
        ]);
    }

    private function applyInternalStatusFromShipping(
        DonHang $order,
        VanDonVanChuyen $shipment,
        ?string $shippingStatus,
        string $source,
        ?string $actorId,
    ): void {
        $nextStatus = null;
        $note = null;

        if ($shipment->ma_van_don_ghn && in_array($order->trang_thai, [OrderStatus::PREPARING, OrderStatus::READY_TO_SHIP], true)) {
            $nextStatus = OrderStatus::HANDED_TO_CARRIER;
            $note = 'Đã tạo vận đơn GHN '.$shipment->ma_van_don_ghn.'.';
        }

        if ($shippingStatus === 'delivered') {
            if ($order->phuong_thuc_tt === 'cod' && $order->trang_thai_thanh_toan !== PaymentStatus::PAID) {
                $order->update([
                    'trang_thai_thanh_toan' => PaymentStatus::PAID,
                    'paid_at' => now(),
                    'thanh_toan_xac_nhan_at' => now(),
                ]);
            }

            if ($order->phuong_thuc_tt === 'cod' || $order->trang_thai_thanh_toan === PaymentStatus::PAID) {
                $nextStatus = OrderStatus::COMPLETED;
                $note = 'GHN xác nhận giao hàng thành công.';
            }
        }

        if ($shippingStatus === 'returning') {
            $nextStatus = OrderStatus::RETURNING;
            $note = 'GHN đang hoàn hàng về kho.';
        }
        if ($shippingStatus === 'returned') {
            $nextStatus = OrderStatus::RETURNED;
            $note = 'GHN đã hoàn hàng; chờ kho xác nhận trước khi nhập lại tồn.';
        }

        if ($nextStatus && $nextStatus !== $order->trang_thai) {
            $oldStatus = $order->trang_thai;
            $order->update(['trang_thai' => $nextStatus]);
            $this->recordOrderHistory($order, $oldStatus, $nextStatus, $actorId, $source, $shipment->ma_van_chuyen, $note);
        }
    }

    private function recordOrderHistory(
        DonHang $order,
        ?string $oldStatus,
        string $newStatus,
        ?string $actorId,
        string $source,
        ?string $shipmentId,
        ?string $note,
    ): void {
        LichSuXuLyDonHang::create([
            'ma_dh' => $order->ma_dh,
            'trang_thai_cu' => $oldStatus,
            'trang_thai_moi' => $newStatus,
            'ma_nguoi_xu_ly' => $actorId,
            'thoi_gian_xu_ly' => now(),
            'ghi_chu' => $note,
            'nguon' => $source,
            'ma_van_chuyen' => $shipmentId,
        ]);
    }

    /** @param array<string, mixed> $payload */
    private function recordShippingEvent(
        VanDonVanChuyen $shipment,
        string $source,
        ?string $rawStatus,
        ?string $normalizedStatus,
        Carbon $eventTime,
        array $payload,
        ?string $note,
        bool $ignored,
        ?string $payloadHash = null,
    ): void {
        $payloadHash ??= $this->payloadHash($payload);

        try {
            SuKienVanChuyen::create([
                'ma_van_chuyen' => $shipment->ma_van_chuyen,
                'ma_dh' => $shipment->ma_dh,
                'nguon' => $source,
                'trang_thai_ghn_goc' => $rawStatus,
                'trang_thai_van_chuyen' => $normalizedStatus,
                'thoi_gian_su_kien' => $eventTime,
                'ma_bam_payload' => $payloadHash,
                'du_lieu_payload' => $this->sanitizePayload($payload),
                'ghi_chu' => $note,
                'da_bo_qua' => $ignored,
                'ngay_tao' => now(),
            ]);
        } catch (QueryException $exception) {
            // The unique payload hash makes duplicate callbacks harmless.
            $message = strtolower($exception->getMessage());
            if (!str_contains($message, 'duplicate') && !str_contains($message, 'unique constraint')) {
                throw $exception;
            }
        }
    }

    /** @param array<string, mixed> $payload */
    private function providerOrderCode(array $payload): ?string
    {
        foreach ($this->providerData($payload) as $data) {
            foreach (['order_code', 'orderCode', 'code'] as $key) {
                if (filled($data[$key] ?? null)) {
                    return trim((string) $data[$key]);
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function clientOrderCode(array $payload): ?string
    {
        foreach ($this->providerData($payload) as $data) {
            foreach (['client_order_code', 'clientOrderCode'] as $key) {
                if (filled($data[$key] ?? null)) {
                    return trim((string) $data[$key]);
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function providerStatus(array $payload): ?string
    {
        foreach ($this->providerData($payload) as $data) {
            foreach (['status', 'Status'] as $key) {
                if (filled($data[$key] ?? null)) {
                    return trim((string) $data[$key]);
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function providerFee(array $payload): ?float
    {
        foreach ($this->providerData($payload) as $data) {
            foreach (['total_fee', 'total', 'fee', 'shipping_fee'] as $key) {
                if (isset($data[$key]) && is_numeric($data[$key])) {
                    return (float) $data[$key];
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function providerExpectedDelivery(array $payload): ?Carbon
    {
        foreach ($this->providerData($payload) as $data) {
            foreach (['leadtime', 'expected_delivery_time', 'expected_delivery_at'] as $key) {
                if (isset($data[$key])) {
                    return $this->toCarbon($data[$key]);
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload */
    private function providerEventTime(array $payload): ?Carbon
    {
        foreach ($this->providerData($payload) as $data) {
            foreach (['updated_date', 'updated_at', 'updatedAt', 'time', 'Time', 'status_time'] as $key) {
                if (isset($data[$key])) {
                    return $this->toCarbon($data[$key]);
                }
            }
        }

        return null;
    }

    /** @param array<string, mixed> $payload @return array<int, array<string, mixed>> */
    private function providerData(array $payload): array
    {
        $items = [$payload];
        if (is_array($payload['data'] ?? null)) {
            $items[] = $payload['data'];
        }

        return $items;
    }

    private function toCarbon(mixed $value): ?Carbon
    {
        try {
            if (is_numeric($value)) {
                $timestamp = (int) $value;
                if ($timestamp > 100000000000) {
                    $timestamp = (int) floor($timestamp / 1000);
                }

                return $timestamp > 0 ? Carbon::createFromTimestamp($timestamp) : null;
            }

            return filled($value) ? Carbon::parse((string) $value) : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<string, mixed> $payload */
    private function payloadHash(array $payload): string
    {
        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @param array<string, mixed> $payload @return array<string, mixed> */
    private function sanitizePayload(array $payload): array
    {
        $sensitiveKeys = [
            'token', 'authorization', 'from_phone', 'to_phone', 'return_phone', 'phone',
            'from_address', 'to_address', 'return_address', 'address',
        ];

        $sanitize = function (mixed $value, ?string $key = null) use (&$sanitize, $sensitiveKeys): mixed {
            if (is_array($value)) {
                $result = [];
                foreach ($value as $nestedKey => $nestedValue) {
                    $result[$nestedKey] = $sanitize($nestedValue, (string) $nestedKey);
                }

                return $result;
            }

            if ($key && in_array(strtolower($key), $sensitiveKeys, true) && is_scalar($value)) {
                $text = (string) $value;
                if (str_contains(strtolower($key), 'phone')) {
                    return strlen($text) > 4 ? str_repeat('*', max(0, strlen($text) - 4)).substr($text, -4) : '****';
                }

                return '[đã ẩn]';
            }

            return $value;
        };

        return $sanitize($payload);
    }

    private function shortError(Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        $message = preg_replace('/\d{7,}/', '[đã ẩn]', $message) ?? $message;

        return mb_substr($message, 0, 1000);
    }
}
