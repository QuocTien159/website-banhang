<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChiTietDonHang;
use App\Models\ChiTietGioHang;
use App\Models\DonHang;
use App\Models\GioHang;
use App\Services\InventoryService;
use App\Services\PayOsService;
use App\Services\PromotionService;
use App\Services\ShippingPaymentService;
use App\Support\OrderStatus;
use App\Support\PaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request, PayOsService $payOsService)
    {
        $orders = DonHang::with(['chiTiets.bienThe.sanPham.anhChinh', 'vanDonVanChuyen'])
            ->where('ma_kh', $request->user()->ma_kh)
            ->orderBy('ngay_dat', 'desc')
            ->get()
            ->map(fn (DonHang $order) => $this->syncPayOsOrder($order, $payOsService, ['chiTiets.bienThe.sanPham.anhChinh', 'vanDonVanChuyen']));

        return response()->json($orders->map(fn ($order) => $this->formatOrder($order)));
    }

    public function show(Request $request, string $id, PayOsService $payOsService)
    {
        $order = DonHang::with([
            'chiTiets.bienThe.sanPham.anhChinh',
            'chiTiets.bienThe.giaTriThuocTinhs.thuocTinh',
            'vanDonVanChuyen.suKiens',
        ])
            ->where('ma_dh', $id)
            ->where('ma_kh', $request->user()->ma_kh)
            ->firstOrFail();

        $order = $this->syncPayOsOrder($order, $payOsService, [
            'chiTiets.bienThe.sanPham.anhChinh',
            'chiTiets.bienThe.giaTriThuocTinhs.thuocTinh',
            'vanDonVanChuyen.suKiens',
        ]);

        return response()->json($this->formatOrder($order, true));
    }

    public function payosStatus(Request $request, string $orderCode, PayOsService $payOsService)
    {
        $order = DonHang::with(['chiTiets.bienThe.sanPham.anhChinh', 'vanDonVanChuyen'])
            ->where('payos_order_code', (int) $orderCode)
            ->where('payment_provider', 'payos')
            ->where('ma_kh', $request->user()->ma_kh)
            ->firstOrFail();

        $order = $this->syncPayOsOrder($order, $payOsService, ['chiTiets.bienThe.sanPham.anhChinh', 'vanDonVanChuyen']);

        return response()->json($this->formatOrder($order));
    }

    public function store(
        Request $request,
        PromotionService $promotionService,
        InventoryService $inventoryService,
        ShippingPaymentService $shippingPaymentService,
        PayOsService $payOsService
    ) {
        $data = $request->validate([
            'ten_nguoi_nhan' => ['required', 'string', 'max:100'],
            'so_dien_thoai' => ['required', 'string', 'regex:/^[0-9]{10,11}$/'],
            'province_id' => ['required', 'string', 'max:20'],
            'district_code' => ['required', 'string', 'max:20'],
            'ward_code' => ['required', 'string', 'max:20'],
            'address_detail' => ['required', 'string', 'max:255'],
            'phuong_thuc_tt' => ['required', 'in:cod,bank_transfer_qr,payos'],
            'ghi_chu' => ['nullable', 'string', 'max:500'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user();
        $cart = GioHang::with(['chiTiets.bienThe.sanPham'])
            ->where('ma_kh', $user->ma_kh)
            ->first();

        if (! $cart || $cart->chiTiets->isEmpty()) {
            return response()->json(['message' => 'Giỏ hàng trống.'], 422);
        }

        foreach ($cart->chiTiets as $item) {
            $variant = $item->bienThe;
            if (! $variant || ! $variant->isSellable()) {
                return response()->json(['message' => 'Biến thể sản phẩm không hợp lệ.'], 422);
            }
            if ($variant->so_luong_ton < $item->so_luong) {
                return response()->json([
                    'message' => "Sản phẩm '{$variant->sanPham?->ten_sp}' chỉ còn {$variant->so_luong_ton} trong kho.",
                ], 422);
            }
        }

        $subtotal = (float) $cart->chiTiets->sum(fn ($item) => $item->bienThe->gia_ban * $item->so_luong);
        // Backend luôn tự tính phí ship từ địa chỉ hành chính hợp lệ, không tin phí gửi từ frontend.
        $shippingResult = $shippingPaymentService->calculateShipping(
            $subtotal,
            $data['district_code'] ?? null,
            $data['ward_code'] ?? null,
            $data['address_detail'],
            $data['province_id'],
            $cart->chiTiets->map(fn ($item) => [
                'name' => $item->bienThe?->sanPham?->ten_sp ?? $item->ma_bien_the,
                'quantity' => (int) $item->so_luong,
                'price' => (int) round((float) ($item->bienThe?->gia_ban ?? 0)),
            ])->values()->all()
        );

        if (! $shippingResult['valid']) {
            return response()->json(['message' => $shippingResult['message']], 422);
        }

        $shipping = (float) $shippingResult['shipping_fee'];

        $order = DB::transaction(function () use (
            $data,
            $user,
            $cart,
            $subtotal,
            $shipping,
            $shippingResult,
            $promotionService,
            $inventoryService,
            $payOsService
        ) {
            $promotion = null;
            $discount = 0;
            if (! empty($data['coupon_code'])) {
                $result = $promotionService->validate($data['coupon_code'], $user->ma_kh, $subtotal, true);
                $promotion = $result['promotion'];
                $discount = $result['discount'];
            }

            $paymentMethod = $data['phuong_thuc_tt'];
            $address = $shippingResult['address'];
            $addressDetail = trim($data['address_detail']);
            $fullAddress = implode(', ', array_filter([
                $addressDetail,
                $address['ward_name'],
                $address['district_name'],
                $address['province_name'],
            ]));

            $order = DonHang::create([
                'ma_kh' => $user->ma_kh,
                'ngay_dat' => now(),
                'tam_tinh' => $subtotal,
                'phi_van_chuyen' => $shipping,
                'loai_khu_vuc_giao' => 'ghn',
                'shipping_zone' => 'ghn',
                'shipping_provider' => 'ghn',
                'shipping_service_id' => $shippingResult['service_id'] ?? null,
                'shipping_service_type_id' => isset($shippingResult['service_type_id']) ? (string) $shippingResult['service_type_id'] : null,
                'shipping_service_name' => $shippingResult['service_name'] ?? null,
                'shipping_status' => 'fee_calculated',
                'shipping_fee_breakdown' => $shippingResult['fee_breakdown'] ?? null,
                'ma_km' => $promotion?->ma_km,
                'ma_khuyen_mai' => $promotion?->code,
                'so_tien_giam' => $discount,
                'tong_tien' => max(0, $subtotal + $shipping - $discount),
                'phuong_thuc_tt' => $paymentMethod,
                'payment_provider' => $paymentMethod === 'payos' ? 'payos' : null,
                'trang_thai_thanh_toan' => $paymentMethod === 'cod' ? PaymentStatus::COD_PENDING : PaymentStatus::PENDING_PAYMENT,
                'dia_chi_giao' => "{$data['ten_nguoi_nhan']} | {$data['so_dien_thoai']} | {$fullAddress}",
                'province_type' => $address['province_type'],
                'ma_tinh_thanh' => $address['province_code'],
                'ma_quan_huyen' => $address['district_code'],
                'ma_phuong_xa' => $address['ward_code'],
                'tinh_thanh' => $address['province_name'],
                'quan_huyen' => $address['district_name'],
                'phuong_xa' => $address['ward_name'],
                'dia_chi_chi_tiet' => $addressDetail,
                'trang_thai' => OrderStatus::PENDING,
                'ghi_chu' => $data['ghi_chu'] ?? null,
            ]);

            foreach ($cart->chiTiets as $item) {
                ChiTietDonHang::create([
                    'ma_dh' => $order->ma_dh,
                    'ma_bien_the' => $item->ma_bien_the,
                    'so_luong' => $item->so_luong,
                    'don_gia' => $item->bienThe->gia_ban,
                ]);

                $inventoryService->changeStock(
                    $item->ma_bien_the,
                    -1 * (int) $item->so_luong,
                    'sale',
                    $user->ma_kh,
                    'Trừ tồn khi đặt hàng',
                    $order->ma_dh
                );
            }

            if ($promotion) {
                DB::table('lich_su_khuyen_mai')->insert([
                    'ma_km' => $promotion->ma_km,
                    'ma_kh' => $user->ma_kh,
                    'ma_dh' => $order->ma_dh,
                    'so_tien_giam' => $discount,
                    'ngay_su_dung' => now(),
                ]);
                $promotion->increment('da_su_dung');
            }

            if ($paymentMethod === 'bank_transfer_qr') {
                $order->noi_dung_chuyen_khoan = app(ShippingPaymentService::class)->transferContent($order);
                $order->qr_code_url = app(ShippingPaymentService::class)->qrUrl($order);
                $order->save();
            }

            if ($paymentMethod === 'payos') {
                $payOsService->createPaymentLink($order);
            }

            ChiTietGioHang::where('ma_gio_hang', $cart->ma_gio_hang)->delete();

            return $order;
        });

        $order->load('chiTiets.bienThe.sanPham.anhChinh');

        return response()->json([
            'message' => 'Đặt hàng thành công!',
            'order' => $this->formatOrder($order),
        ], 201);
    }

    public function markBankTransferPaid(Request $request, string $id)
    {
        $order = DonHang::where('ma_dh', $id)
            ->where('ma_kh', $request->user()->ma_kh)
            ->firstOrFail();

        if ($order->phuong_thuc_tt !== 'bank_transfer_qr') {
            return response()->json(['message' => 'Đơn hàng này không dùng thanh toán QR chuyển khoản.'], 422);
        }

        if ($order->payment_provider === 'payos') {
            return response()->json(['message' => 'Thanh toán payOS sẽ được cập nhật tự động sau khi ngân hàng xác nhận.'], 422);
        }

        if (! in_array($order->trang_thai_thanh_toan, [PaymentStatus::PENDING_PAYMENT, PaymentStatus::PAYMENT_NOT_RECEIVED], true)) {
            return response()->json(['message' => 'Trạng thái thanh toán hiện tại không cho phép báo đã chuyển khoản.'], 422);
        }

        $order->update([
            'trang_thai_thanh_toan' => PaymentStatus::WAITING_ADMIN_CONFIRMATION,
            'khach_bao_da_chuyen_at' => now(),
        ]);

        return response()->json([
            'message' => 'Đã ghi nhận thông báo chuyển khoản. Admin sẽ kiểm tra và xác nhận.',
            'order' => $this->formatOrder($order->fresh(['chiTiets.bienThe.sanPham.anhChinh'])),
        ]);
    }

    private function formatOrder(DonHang $order, bool $detail = false): array
    {
        $addressParts = explode(' | ', $order->dia_chi_giao);
        $items = $order->chiTiets->map(fn ($item) => [
            'variant_id' => $item->ma_bien_the,
            'product' => [
                'id' => $item->bienThe?->sanPham?->ma_sp,
                'name' => $item->bienThe?->sanPham?->ten_sp,
                'image' => $item->bienThe?->sanPham?->anhChinh?->url,
            ],
            'price' => (float) $item->don_gia,
            'quantity' => $item->so_luong,
            'subtotal' => (float) $item->don_gia * $item->so_luong,
        ])->values();

        $result = [
            'id' => $order->ma_dh,
            'status' => $order->trang_thai,
            'created_at' => $order->ngay_dat?->toISOString(),
            'successful_delivery_at' => $order->ngay_giao_thanh_cong?->toISOString(),
            'total' => (float) $order->tong_tien,
            'subtotal' => (float) ($order->tam_tinh ?? $order->tong_tien),
            'shipping' => (float) ($order->phi_van_chuyen ?? 0),
            'shipping_area_type' => $order->loai_khu_vuc_giao,
            'shipping_zone' => $order->shipping_zone ?? $order->loai_khu_vuc_giao,
            'shipping_provider' => $order->shipping_provider,
            'shipping_service_id' => $order->shipping_service_id,
            'shipping_service_type_id' => $order->shipping_service_type_id,
            'shipping_service_name' => $order->shipping_service_name,
            'shipping_order_code' => $order->shipping_order_code,
            'shipping_status' => $order->shipping_status,
            'shipping_tracking' => $this->formatShippingTracking($order, $detail),
            'coupon_code' => $order->ma_khuyen_mai,
            'discount' => (float) ($order->so_tien_giam ?? 0),
            'payment_method' => $order->phuong_thuc_tt,
            'payment_provider' => $order->payment_provider,
            'payos_order_code' => $order->payos_order_code,
            'payment_link_id' => $order->payment_link_id,
            'payment_checkout_url' => $order->payment_checkout_url,
            'payment_status' => $order->trang_thai_thanh_toan,
            'bank_transfer_content' => $order->noi_dung_chuyen_khoan,
            'qr_code_url' => $order->qr_code_url,
            'customer_paid_at' => $order->khach_bao_da_chuyen_at?->toISOString(),
            'payment_confirmed_at' => $order->thanh_toan_xac_nhan_at?->toISOString(),
            'paid_at' => $order->paid_at?->toISOString(),
            'shipping_info' => [
                'name' => $addressParts[0] ?? '',
                'phone' => $addressParts[1] ?? '',
                'address' => $addressParts[2] ?? $order->dia_chi_giao,
                'province' => $order->tinh_thanh,
                'province_type' => $order->province_type,
                'district' => $order->quan_huyen,
                'ward' => $order->phuong_xa,
                'province_code' => $order->ma_tinh_thanh,
                'district_code' => $order->ma_quan_huyen,
                'ward_code' => $order->ma_phuong_xa,
                'detail' => $order->dia_chi_chi_tiet,
            ],
            'note' => $order->ghi_chu,
            'items' => $items,
        ];

        if ($order->phuong_thuc_tt === 'bank_transfer_qr') {
            $result['bank'] = app(ShippingPaymentService::class)->bankInfo();
        }

        if ($detail) {
            $result['items'] = $order->chiTiets->map(fn ($item) => array_merge(
                $items->firstWhere('variant_id', $item->ma_bien_the) ?? [],
                [
                    'attributes' => $item->bienThe?->giaTriThuocTinhs?->map(fn ($attributeValue) => [
                        'name' => $attributeValue->thuocTinh?->ten_tt,
                        'value' => $attributeValue->gia_tri,
                    ])->values() ?? [],
                ]
            ))->values();
        }

        return $result;
    }

    private function syncPayOsOrder(DonHang $order, PayOsService $payOsService, array $relations): DonHang
    {
        if ($order->payment_provider === 'payos' && $order->trang_thai_thanh_toan !== PaymentStatus::PAID) {
            $order = $payOsService->syncPaymentStatus($order);
            $order->load($relations);
        }

        return $order;
    }

    private function formatShippingTracking(DonHang $order, bool $detail): array
    {
        $shipment = $order->vanDonVanChuyen;
        if (! $shipment) {
            return [
                'mode' => 'legacy',
                'provider' => $order->shipping_provider ?? 'manual',
                'tracking_code' => $order->shipping_order_code,
                'status' => null,
                'raw_status' => $order->shipping_status,
                'status_updated_at' => null,
                'expected_delivery_at' => $order->shipping_expected_delivery_at?->toISOString(),
                'events' => [],
            ];
        }

        return [
            'mode' => 'ghn',
            'provider' => 'ghn',
            'tracking_code' => $shipment->ma_van_don_ghn,
            'status' => $shipment->trang_thai_van_chuyen,
            'raw_status' => $shipment->trang_thai_ghn_goc,
            'status_updated_at' => $shipment->ngay_cap_nhat_ghn?->toISOString(),
            'expected_delivery_at' => $shipment->thoi_gian_giao_du_kien?->toISOString(),
            'events' => $detail
                ? $shipment->suKiens
                    ->filter(fn ($event) => ! $event->da_bo_qua && in_array($event->nguon, ['ghn_create', 'ghn_sync', 'ghn_webhook'], true))
                    ->map(fn ($event) => [
                        'status' => $event->trang_thai_van_chuyen,
                        'raw_status' => $event->trang_thai_ghn_goc,
                        'at' => $event->thoi_gian_su_kien?->toISOString(),
                        'note' => $this->customerShippingNote($event->trang_thai_van_chuyen),
                    ])->values()
                : [],
        ];
    }

    private function customerShippingNote(?string $status): ?string
    {
        return match ($status) {
            'delivery_failed' => 'GHN chưa giao được hàng. Vui lòng theo dõi thêm hoặc liên hệ cửa hàng khi cần hỗ trợ.',
            'returning' => 'Đơn đang được GHN hoàn về kho.',
            'returned' => 'Đơn đã được GHN hoàn về kho.',
            'exception' => 'GHN đang xử lý một sự cố giao hàng. Cửa hàng sẽ cập nhật sớm nhất.',
            default => null,
        };
    }
}
