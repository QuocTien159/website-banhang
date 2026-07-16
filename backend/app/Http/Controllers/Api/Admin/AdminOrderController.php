<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Models\LichSuXuLyDonHang;
use App\Models\VanDonVanChuyen;
use App\Services\GhnShipmentService;
use App\Services\InventoryService;
use App\Services\PayOsService;
use App\Support\OrderStatus;
use App\Support\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AdminOrderController extends Controller
{
    public function index(Request $request, PayOsService $payOsService)
    {
        $relations = ['khachHang', 'chiTiets', 'xuLyGanNhat.nguoiXuLy', 'vanDonVanChuyen'];
        $query = DonHang::with($relations);

        if ($status = $request->input('status')) {
            $query->where('trang_thai', $status);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($query) use ($search) {
                $query->where('ma_dh', 'like', "%{$search}%")
                    ->orWhere('dia_chi_giao', 'like', "%{$search}%")
                    ->orWhere('noi_dung_chuyen_khoan', 'like', "%{$search}%")
                    ->orWhere('shipping_order_code', 'like', "%{$search}%")
                    ->orWhereHas('khachHang', fn ($customer) => $customer->where('ten_kh', 'like', "%{$search}%"));
            });
        }

        $orders = $query->orderByDesc('ngay_dat')->paginate(20);
        $orders->getCollection()->transform(function (DonHang $order) use ($payOsService, $relations) {
            if ($order->payment_provider === 'payos' && $order->trang_thai_thanh_toan !== PaymentStatus::PAID) {
                $order = $payOsService->syncPaymentStatus($order);
                $order->load($relations);
            }

            return $order;
        });

        return response()->json([
            'data' => $orders->getCollection()->map(fn (DonHang $order) => $this->formatListOrder($order))->values(),
            'meta' => [
                'total' => $orders->total(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ],
            'stats' => $this->getStats(),
        ]);
    }

    public function show(string $id)
    {
        $order = DonHang::with([
            'khachHang',
            'chiTiets.bienThe.sanPham.anhChinh',
            'chiTiets.bienThe.giaTriThuocTinhs.thuocTinh',
            'lichSuXuLy.nguoiXuLy',
            'vanDonVanChuyen.suKiens',
        ])->findOrFail($id);

        return response()->json($this->formatDetailOrder($order));
    }

    public function updateStatus(Request $request, string $id, InventoryService $inventoryService)
    {
        $data = $request->validate([
            'status' => ['required', 'in:'.implode(',', [
                OrderStatus::CONFIRMED,
                OrderStatus::PREPARING,
                OrderStatus::CANCELLED,
            ])],
            'note' => ['nullable', 'string', 'max:1000'],
            'confirm_stock_return' => ['nullable', 'boolean'],
        ]);

        $order = DonHang::with(['chiTiets', 'vanDonVanChuyen'])->findOrFail($id);
        $oldStatus = $order->trang_thai;
        $targetStatus = $data['status'];

        if (!$this->canMoveInternalStatus($oldStatus, $targetStatus)) {
            return response()->json([
                'message' => 'Thao tác này không hợp lệ. Trạng thái giao hàng phải được GHN cập nhật tự động.',
            ], 422);
        }

        if ($order->payment_provider === 'payos'
            && $order->trang_thai_thanh_toan !== PaymentStatus::PAID
            && $targetStatus !== OrderStatus::CANCELLED
        ) {
            return response()->json([
                'message' => 'Đơn payOS chưa được xác nhận thanh toán, không thể chuyển sang bước xử lý nội bộ.',
            ], 422);
        }

        if ($targetStatus === OrderStatus::CANCELLED) {
            return $this->cancelOrder($request, $order, $data, $inventoryService);
        }

        DB::transaction(function () use ($order, $oldStatus, $targetStatus, $data, $request) {
            $order->update(['trang_thai' => $targetStatus]);
            $this->recordStatusHistory($order, $oldStatus, $targetStatus, $request->user()->ma_kh, $data['note'] ?? null);
        });

        return response()->json([
            'message' => 'Đã cập nhật bước xử lý nội bộ.',
            'status' => $targetStatus,
        ]);
    }

    public function handoffToGhn(Request $request, string $id, GhnShipmentService $shipmentService)
    {
        try {
            $shipment = $shipmentService->handoff($id, $request->user()->ma_kh);

            return response()->json([
                'message' => $shipment->ma_van_don_ghn
                    ? 'Đã tạo vận đơn GHN và bàn giao trạng thái cho GHN.'
                    : 'Yêu cầu tạo vận đơn đang được GHN xử lý.',
                'shipping' => $this->formatShipment($shipment, true),
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
                'retry_available' => true,
            ], 502);
        }
    }

    public function retryGhnShipment(Request $request, string $id, GhnShipmentService $shipmentService)
    {
        $shipment = VanDonVanChuyen::where('ma_dh', $id)->first();
        if (!$shipment || !$shipment->canRetryCreation()) {
            return response()->json(['message' => 'Đơn không có vận đơn GHN lỗi để tạo lại.'], 422);
        }

        try {
            $shipment = $shipmentService->handoff($id, $request->user()->ma_kh, true);

            return response()->json([
                'message' => 'Đã gửi lại yêu cầu tạo vận đơn GHN.',
                'shipping' => $this->formatShipment($shipment, true),
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage(), 'retry_available' => true], 502);
        }
    }

    public function syncGhnShipment(Request $request, string $id, GhnShipmentService $shipmentService)
    {
        try {
            $shipment = $shipmentService->sync($id, $request->user()->ma_kh);

            return response()->json([
                'message' => 'Đã lấy trạng thái mới nhất từ GHN.',
                'shipping' => $this->formatShipment($shipment, true),
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }
    }

    public function requestGhnShipmentCancellation(Request $request, string $id, GhnShipmentService $shipmentService)
    {
        try {
            $shipment = $shipmentService->requestCancellation($id, $request->user()->ma_kh);

            return response()->json([
                'message' => 'Đã gửi yêu cầu hủy vận đơn đến GHN. Tồn kho chưa thay đổi cho đến khi có xác nhận nghiệp vụ.',
                'shipping' => $this->formatShipment($shipment, true),
            ]);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            return response()->json(['message' => $exception->getMessage()], 502);
        }
    }

    public function updatePaymentStatus(Request $request, string $id)
    {
        $data = $request->validate([
            'payment_status' => ['required', 'in:'.implode(',', [PaymentStatus::PAID, PaymentStatus::PAYMENT_NOT_RECEIVED])],
        ]);

        $order = DonHang::findOrFail($id);
        if ($order->payment_provider === 'payos' || $order->phuong_thuc_tt === 'payos') {
            return response()->json(['message' => 'Thanh toán payOS được xác nhận tự động bằng webhook, không cần duyệt thủ công.'], 422);
        }

        if ($order->phuong_thuc_tt !== 'bank_transfer_qr') {
            return response()->json(['message' => 'Đơn hàng này không dùng thanh toán QR chuyển khoản.'], 422);
        }

        $payload = ['trang_thai_thanh_toan' => $data['payment_status']];
        if ($data['payment_status'] === PaymentStatus::PAID) {
            $payload['thanh_toan_xac_nhan_at'] = now();
            $payload['thanh_toan_xac_nhan_boi'] = $request->user()->ma_kh;
        } else {
            $payload['thanh_toan_xac_nhan_at'] = null;
            $payload['thanh_toan_xac_nhan_boi'] = null;
        }

        $order->update($payload);

        return response()->json([
            'message' => $data['payment_status'] === PaymentStatus::PAID
                ? 'Đã xác nhận đơn hàng đã thanh toán.'
                : 'Đã đánh dấu chưa nhận được tiền.',
            'payment_status' => $order->fresh()->trang_thai_thanh_toan,
        ]);
    }

    private function cancelOrder(Request $request, DonHang $order, array $data, InventoryService $inventoryService)
    {
        $shipment = $order->vanDonVanChuyen;
        if ($shipment?->ma_van_don_ghn && $shipment->trang_thai_van_chuyen !== 'cancelled') {
            return response()->json([
                'message' => 'Vận đơn đã được tạo. Hãy gửi yêu cầu hủy GHN và chỉ hủy đơn nội bộ sau khi GHN xác nhận.',
            ], 422);
        }

        if ($shipment?->ma_van_don_ghn && empty($data['confirm_stock_return'])) {
            return response()->json([
                'message' => 'GHN đã xác nhận hủy. Xác nhận kiểm tra hàng chưa rời kho trước khi hoàn tồn.',
                'requires_stock_confirmation' => true,
            ], 422);
        }

        DB::transaction(function () use ($order, $data, $request, $inventoryService) {
            $lockedOrder = DonHang::with('chiTiets')->where('ma_dh', $order->ma_dh)->lockForUpdate()->firstOrFail();
            if ($lockedOrder->trang_thai === OrderStatus::CANCELLED) {
                return;
            }

            foreach ($lockedOrder->chiTiets as $item) {
                $inventoryService->changeStock(
                    $item->ma_bien_the,
                    (int) $item->so_luong,
                    'order_cancelled',
                    $request->user()->ma_kh,
                    'Hoàn tồn kho khi hủy đơn',
                    $lockedOrder->ma_dh
                );
            }
            $oldStatus = $lockedOrder->trang_thai;
            $lockedOrder->update(['trang_thai' => OrderStatus::CANCELLED]);
            $this->recordStatusHistory($lockedOrder, $oldStatus, OrderStatus::CANCELLED, $request->user()->ma_kh, $data['note'] ?? null);
        });

        return response()->json(['message' => 'Đã hủy đơn và hoàn tồn kho theo xác nhận nội bộ.', 'status' => OrderStatus::CANCELLED]);
    }

    private function canMoveInternalStatus(string $current, string $target): bool
    {
        return match ($current) {
            OrderStatus::PENDING => in_array($target, [OrderStatus::CONFIRMED, OrderStatus::CANCELLED], true),
            OrderStatus::CONFIRMED => in_array($target, [OrderStatus::PREPARING, OrderStatus::CANCELLED], true),
            OrderStatus::PREPARING, OrderStatus::READY_TO_SHIP => $target === OrderStatus::CANCELLED,
            default => false,
        };
    }

    private function getStats(): array
    {
        return [
            OrderStatus::PENDING => DonHang::where('trang_thai', OrderStatus::PENDING)->count(),
            OrderStatus::CONFIRMED => DonHang::where('trang_thai', OrderStatus::CONFIRMED)->count(),
            OrderStatus::PREPARING => DonHang::where('trang_thai', OrderStatus::PREPARING)->count(),
            OrderStatus::READY_TO_SHIP => DonHang::where('trang_thai', OrderStatus::READY_TO_SHIP)->count(),
            OrderStatus::HANDED_TO_CARRIER => DonHang::where('trang_thai', OrderStatus::HANDED_TO_CARRIER)->count(),
            OrderStatus::COMPLETED => DonHang::where('trang_thai', OrderStatus::COMPLETED)->count(),
            OrderStatus::RETURNING => DonHang::where('trang_thai', OrderStatus::RETURNING)->count(),
            OrderStatus::RETURNED => DonHang::where('trang_thai', OrderStatus::RETURNED)->count(),
            OrderStatus::SHIPPING => DonHang::where('trang_thai', OrderStatus::SHIPPING)->count(),
            OrderStatus::DELIVERED => DonHang::where('trang_thai', OrderStatus::DELIVERED)->count(),
            OrderStatus::CANCELLED => DonHang::where('trang_thai', OrderStatus::CANCELLED)->count(),
        ];
    }

    private function formatListOrder(DonHang $order): array
    {
        $shipment = $order->vanDonVanChuyen;
        $itemCount = $order->chiTiets->count();
        $totalQuantity = (int) $order->chiTiets->sum('so_luong');

        return [
            'id' => $order->ma_dh,
            'customer' => $order->khachHang?->ten_kh ?? 'N/A',
            'customer_name' => $order->khachHang?->ten_kh ?? 'N/A',
            'customer_email' => $order->khachHang?->email,
            'customer_id' => $order->ma_kh,
            'date' => $order->ngay_dat?->format('d/m/Y H:i'),
            'created_at' => $order->ngay_dat?->toISOString(),
            'created_at_formatted' => $order->ngay_dat?->format('d/m/Y H:i'),
            'total' => (float) $order->tong_tien,
            'status' => $order->trang_thai,
            'payment' => $order->phuong_thuc_tt,
            'payment_method' => $order->phuong_thuc_tt,
            'payment_provider' => $order->payment_provider,
            'payment_status' => $order->trang_thai_thanh_toan,
            'bank_transfer_content' => $order->noi_dung_chuyen_khoan,
            'customer_paid_at' => $order->khach_bao_da_chuyen_at?->toISOString(),
            'payment_confirmed_at' => $order->thanh_toan_xac_nhan_at?->toISOString(),
            'shipping_fee' => (float) ($order->phi_van_chuyen ?? 0),
            'shipping_area_type' => $order->loai_khu_vuc_giao,
            'address' => $order->dia_chi_giao,
            'item_count' => $itemCount,
            'items_count' => $totalQuantity,
            'total_quantity' => $totalQuantity,
            'last_processed_by' => $order->xuLyGanNhat?->nguoiXuLy?->ten_kh,
            'last_processed_at' => $order->xuLyGanNhat?->thoi_gian_xu_ly?->toISOString(),
            'shipping' => $this->formatShipment($shipment),
        ];
    }

    private function formatDetailOrder(DonHang $order): array
    {
        $items = $order->chiTiets->map(fn ($item) => [
            'variant_id' => $item->ma_bien_the,
            'sku' => $item->bienThe?->sku,
            'product' => [
                'id' => $item->bienThe?->sanPham?->ma_sp,
                'name' => $item->bienThe?->sanPham?->ten_sp,
                'image' => $item->bienThe?->sanPham?->anhChinh?->url,
            ],
            'quantity' => (int) $item->so_luong,
            'price' => (float) $item->don_gia,
            'attributes' => $item->bienThe?->giaTriThuocTinhs->map(fn ($attributeValue) => [
                'name' => $attributeValue->thuocTinh?->ten_tt,
                'value' => $attributeValue->gia_tri,
            ])->values(),
        ])->values();

        return array_merge($this->formatListOrder($order), [
            'subtotal' => (float) ($order->tam_tinh ?? $order->tong_tien),
            'discount' => (float) ($order->so_tien_giam ?? 0),
            'coupon_code' => $order->ma_khuyen_mai,
            'note' => $order->ghi_chu,
            'items' => $items,
            'shipping_info' => $this->recipientInfo($order),
            'shipping' => $this->formatShipment($order->vanDonVanChuyen, true),
            'internal_history' => $order->lichSuXuLy
                ->filter(fn (LichSuXuLyDonHang $history) => ($history->nguon ?? 'noi_bo') === 'noi_bo')
                ->sortBy('thoi_gian_xu_ly')
                ->map(fn (LichSuXuLyDonHang $history) => [
                    'id' => $history->ma_ls_xl_dh,
                    'from_status' => $history->trang_thai_cu,
                    'to_status' => $history->trang_thai_moi,
                    'source' => $history->nguon ?? 'noi_bo',
                    'actor' => $history->nguoiXuLy?->ten_kh,
                    'at' => $history->thoi_gian_xu_ly?->toISOString(),
                    'note' => $history->ghi_chu,
                ])->values(),
        ]);
    }

    private function formatShipment(?VanDonVanChuyen $shipment, bool $includeEvents = false): array
    {
        if (!$shipment) {
            return [
                'mode' => 'legacy',
                'provider' => 'ghn',
                'tracking_code' => null,
                'status' => null,
                'raw_status' => null,
                'creation_state' => 'chua_tao',
                'events' => [],
            ];
        }

        return [
            'mode' => 'ghn',
            'provider' => 'ghn',
            'environment' => $shipment->moi_truong,
            'tracking_code' => $shipment->ma_van_don_ghn,
            'status' => $shipment->trang_thai_van_chuyen,
            'raw_status' => $shipment->trang_thai_ghn_goc,
            'status_updated_at' => $shipment->ngay_cap_nhat_ghn?->toISOString(),
            'shipping_fee' => $shipment->phi_van_chuyen !== null ? (float) $shipment->phi_van_chuyen : null,
            'expected_delivery_at' => $shipment->thoi_gian_giao_du_kien?->toISOString(),
            'creation_state' => $shipment->trang_thai_tao,
            'attempts' => (int) $shipment->so_lan_tao,
            'last_error' => $shipment->loi_dong_bo_cuoi,
            'synced_at' => $shipment->ngay_dong_bo?->toISOString(),
            'events' => $includeEvents
                ? $shipment->suKiens
                    ->filter(fn ($event) => in_array($event->nguon, ['ghn_create', 'ghn_sync', 'ghn_webhook'], true))
                    ->map(fn ($event) => [
                    'id' => $event->ma_su_kien,
                    'source' => $event->nguon,
                    'raw_status' => $event->trang_thai_ghn_goc,
                    'status' => $event->trang_thai_van_chuyen,
                    'at' => $event->thoi_gian_su_kien?->toISOString(),
                    'note' => $event->ghi_chu,
                    'ignored' => (bool) $event->da_bo_qua,
                    ])->values()
                : [],
        ];
    }

    private function recipientInfo(DonHang $order): array
    {
        $parts = array_map('trim', explode('|', (string) $order->dia_chi_giao));

        return [
            'name' => $parts[0] ?? '',
            'phone' => $parts[1] ?? '',
            'address' => $parts[2] ?? $order->dia_chi_giao,
            'province' => $order->tinh_thanh,
            'district' => $order->quan_huyen,
            'ward' => $order->phuong_xa,
            'detail' => $order->dia_chi_chi_tiet,
        ];
    }

    private function recordStatusHistory(DonHang $order, ?string $oldStatus, string $newStatus, ?string $actorId, ?string $note = null): void
    {
        LichSuXuLyDonHang::create([
            'ma_dh' => $order->ma_dh,
            'trang_thai_cu' => $oldStatus,
            'trang_thai_moi' => $newStatus,
            'ma_nguoi_xu_ly' => $actorId,
            'thoi_gian_xu_ly' => now(),
            'ghi_chu' => $note,
            'nguon' => 'noi_bo',
        ]);
    }
}
