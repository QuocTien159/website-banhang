<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChiTietDonHang;
use App\Models\ChiTietGioHang;
use App\Models\DonHang;
use App\Models\GioHang;
use App\Services\InventoryService;
use App\Services\PromotionService;
use App\Services\ShippingPaymentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $orders = DonHang::with(['chiTiets.bienThe.sanPham.anhChinh'])
            ->where('ma_kh', $request->user()->ma_kh)
            ->orderBy('ngay_dat', 'desc')
            ->get();

        return response()->json($orders->map(fn ($order) => $this->formatOrder($order)));
    }

    public function show(Request $request, string $id)
    {
        $order = DonHang::with(['chiTiets.bienThe.sanPham.anhChinh', 'chiTiets.bienThe.giaTriThuocTinhs.thuocTinh'])
            ->where('ma_dh', $id)
            ->where('ma_kh', $request->user()->ma_kh)
            ->firstOrFail();

        return response()->json($this->formatOrder($order, true));
    }

    public function store(
        Request $request,
        PromotionService $promotionService,
        InventoryService $inventoryService,
        ShippingPaymentService $shippingPaymentService
    ) {
        $data = $request->validate([
            'ten_nguoi_nhan' => ['required', 'string', 'max:100'],
            'so_dien_thoai' => ['required', 'string', 'regex:/^[0-9]{10,11}$/'],
            'province_type' => ['required', 'in:hcm,hanoi,other'],
            'district_code' => ['nullable', 'string', 'max:20'],
            'ward_code' => ['nullable', 'string', 'max:20'],
            'address_detail' => ['required', 'string', 'max:255'],
            'phuong_thuc_tt' => ['required', 'in:cod,banking,bank_transfer_qr'],
            'ghi_chu' => ['nullable', 'string', 'max:500'],
            'coupon_code' => ['nullable', 'string', 'max:50'],
        ]);

        $user = $request->user();
        $cart = GioHang::with(['chiTiets.bienThe.sanPham'])
            ->where('ma_kh', $user->ma_kh)
            ->first();

        if (!$cart || $cart->chiTiets->isEmpty()) {
            return response()->json(['message' => 'Giỏ hàng trống.'], 422);
        }

        foreach ($cart->chiTiets as $item) {
            $variant = $item->bienThe;
            if (!$variant || !$variant->trang_thai) {
                return response()->json(['message' => 'Biến thể sản phẩm không hợp lệ.'], 422);
            }
            if ($variant->so_luong_ton < $item->so_luong) {
                return response()->json([
                    'message' => "Sản phẩm '{$variant->sanPham?->ten_sp}' chỉ còn {$variant->so_luong_ton} trong kho.",
                ], 422);
            }
        }

        $subtotal = (float) $cart->chiTiets->sum(fn ($item) => $item->bienThe->gia_ban * $item->so_luong);
        $shippingResult = $shippingPaymentService->calculateShipping(
            $subtotal,
            $data['province_type'],
            $data['district_code'] ?? null,
            $data['ward_code'] ?? null,
            $data['address_detail']
        );

        if (!$shippingResult['valid']) {
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
            $shippingPaymentService
        ) {
            $promotion = null;
            $discount = 0;
            if (!empty($data['coupon_code'])) {
                $result = $promotionService->validate($data['coupon_code'], $user->ma_kh, $subtotal, true);
                $promotion = $result['promotion'];
                $discount = $result['discount'];
            }

            $paymentMethod = $data['phuong_thuc_tt'] === 'banking' ? 'bank_transfer_qr' : $data['phuong_thuc_tt'];
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
                'loai_khu_vuc_giao' => $shippingResult['shipping_zone'],
                'shipping_zone' => $shippingResult['shipping_zone'],
                'ma_km' => $promotion?->ma_km,
                'ma_khuyen_mai' => $promotion?->code,
                'so_tien_giam' => $discount,
                'tong_tien' => max(0, $subtotal + $shipping - $discount),
                'phuong_thuc_tt' => $paymentMethod,
                'trang_thai_thanh_toan' => $paymentMethod === 'cod' ? 'cod_pending' : 'pending_payment',
                'dia_chi_giao' => "{$data['ten_nguoi_nhan']} | {$data['so_dien_thoai']} | {$fullAddress}",
                'province_type' => $address['province_type'],
                'ma_tinh_thanh' => $address['province_code'],
                'ma_quan_huyen' => $address['district_code'],
                'ma_phuong_xa' => $address['ward_code'],
                'tinh_thanh' => $address['province_name'],
                'quan_huyen' => $address['district_name'],
                'phuong_xa' => $address['ward_name'],
                'dia_chi_chi_tiet' => $addressDetail,
                'trang_thai' => 'pending',
                'ghi_chu' => $data['ghi_chu'] ?? null,
            ]);

            if ($paymentMethod === 'bank_transfer_qr') {
                $order->noi_dung_chuyen_khoan = $shippingPaymentService->transferContent($order);
                $order->qr_code_url = $shippingPaymentService->qrUrl($order);
                $order->save();
            }

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

        if (!in_array($order->trang_thai_thanh_toan, ['pending_payment', 'payment_not_received'], true)) {
            return response()->json(['message' => 'Trạng thái thanh toán hiện tại không cho phép báo đã chuyển khoản.'], 422);
        }

        $order->update([
            'trang_thai_thanh_toan' => 'waiting_admin_confirmation',
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
            'total' => (float) $order->tong_tien,
            'subtotal' => (float) ($order->tam_tinh ?? $order->tong_tien),
            'shipping' => (float) ($order->phi_van_chuyen ?? 0),
            'shipping_area_type' => $order->loai_khu_vuc_giao,
            'shipping_zone' => $order->shipping_zone ?? $order->loai_khu_vuc_giao,
            'coupon_code' => $order->ma_khuyen_mai,
            'discount' => (float) ($order->so_tien_giam ?? 0),
            'payment_method' => $order->phuong_thuc_tt,
            'payment_status' => $order->trang_thai_thanh_toan,
            'bank_transfer_content' => $order->noi_dung_chuyen_khoan,
            'qr_code_url' => $order->qr_code_url,
            'customer_paid_at' => $order->khach_bao_da_chuyen_at?->toISOString(),
            'payment_confirmed_at' => $order->thanh_toan_xac_nhan_at?->toISOString(),
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
}
