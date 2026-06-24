<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Models\ChiTietDonHang;
use App\Models\GioHang;
use App\Models\ChiTietGioHang;
use App\Models\BienTheSanPham;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrderController extends Controller
{
    /** GET /api/orders — Lịch sử đơn hàng của user */
    public function index(Request $request)
    {
        $orders = DonHang::with(['chiTiets.bienThe.sanPham.anhChinh'])
            ->where('ma_kh', $request->user()->ma_kh)
            ->orderBy('ngay_dat', 'desc')
            ->get();

        return response()->json($orders->map(fn($dh) => $this->formatOrder($dh)));
    }

    /** GET /api/orders/{id} — Chi tiết đơn hàng */
    public function show(Request $request, string $id)
    {
        $order = DonHang::with(['chiTiets.bienThe.sanPham.anhChinh', 'chiTiets.bienThe.giaTriThuocTinhs.thuocTinh'])
            ->where('ma_dh', $id)
            ->where('ma_kh', $request->user()->ma_kh)
            ->firstOrFail();

        return response()->json($this->formatOrder($order, true));
    }

    /** POST /api/orders — Đặt hàng từ giỏ hàng */
    public function store(Request $request)
    {
        $data = $request->validate([
            'ten_nguoi_nhan'  => 'required|string|max:100',
            'so_dien_thoai'   => 'required|string|regex:/^[0-9]{10,11}$/',
            'dia_chi_giao'    => 'required|string|max:255',
            'phuong_thuc_tt'  => 'required|in:cod,banking',
            'ghi_chu'         => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $cart = GioHang::with(['chiTiets.bienThe'])
            ->where('ma_kh', $user->ma_kh)
            ->first();

        if (!$cart || $cart->chiTiets->isEmpty()) {
            return response()->json(['message' => 'Giỏ hàng trống.'], 422);
        }

        // Validate stock
        foreach ($cart->chiTiets as $ct) {
            $bt = $ct->bienThe;
            if (!$bt || !$bt->trang_thai) {
                return response()->json(['message' => "Biến thể sản phẩm không hợp lệ."], 422);
            }
            if ($bt->so_luong_ton < $ct->so_luong) {
                return response()->json([
                    'message' => "Sản phẩm '{$bt->sanPham->ten_sp}' chỉ còn {$bt->so_luong_ton} trong kho.",
                ], 422);
            }
        }

        // Calculate totals
        $subtotal = $cart->chiTiets->sum(fn($ct) => $ct->bienThe->gia_ban * $ct->so_luong);
        $shipping = $subtotal >= 500000 ? 0 : 30000;
        $total = $subtotal + $shipping;

        // Create order in transaction
        $order = DB::transaction(function () use ($data, $user, $cart, $total) {
            $order = DonHang::create([
                'ma_kh'          => $user->ma_kh,
                'ngay_dat'       => now(),
                'tong_tien'      => $total,
                'phuong_thuc_tt' => $data['phuong_thuc_tt'],
                'dia_chi_giao'   => "{$data['ten_nguoi_nhan']} | {$data['so_dien_thoai']} | {$data['dia_chi_giao']}",
                'trang_thai'     => 'pending',
                'ghi_chu'        => $data['ghi_chu'] ?? null,
            ]);

            foreach ($cart->chiTiets as $ct) {
                ChiTietDonHang::create([
                    'ma_dh'       => $order->ma_dh,
                    'ma_bien_the' => $ct->ma_bien_the,
                    'so_luong'    => $ct->so_luong,
                    'don_gia'     => $ct->bienThe->gia_ban,
                ]);

                // Decrease stock
                $ct->bienThe->decrement('so_luong_ton', $ct->so_luong);
            }

            // Clear cart
            ChiTietGioHang::where('ma_gio_hang', $cart->ma_gio_hang)->delete();

            return $order;
        });

        $order->load('chiTiets.bienThe.sanPham.anhChinh');
        return response()->json([
            'message' => 'Đặt hàng thành công!',
            'order'   => $this->formatOrder($order),
        ], 201);
    }

    private function formatOrder(DonHang $dh, bool $detail = false): array
    {
        $addressParts = explode(' | ', $dh->dia_chi_giao);
        $result = [
            'id'             => $dh->ma_dh,
            'status'         => $dh->trang_thai,
            'created_at'     => $dh->ngay_dat?->toISOString(),
            'total'          => (float)$dh->tong_tien,
            'payment_method' => $dh->phuong_thuc_tt,
            'shipping_info'  => [
                'name'    => $addressParts[0] ?? '',
                'phone'   => $addressParts[1] ?? '',
                'address' => $addressParts[2] ?? $dh->dia_chi_giao,
            ],
            'note'    => $dh->ghi_chu,
            'items'   => $dh->chiTiets->map(fn($ct) => [
                'variant_id' => $ct->ma_bien_the,
                'product' => [
                    'id'    => $ct->bienThe?->sanPham?->ma_sp,
                    'name'  => $ct->bienThe?->sanPham?->ten_sp,
                    'image' => $ct->bienThe?->sanPham?->anhChinh?->url,
                ],
                'price'    => (float)$ct->don_gia,
                'quantity' => $ct->so_luong,
                'subtotal' => (float)$ct->don_gia * $ct->so_luong,
            ])->values(),
        ];

        if ($detail) {
            $result['items'] = $dh->chiTiets->map(fn($ct) => array_merge(
                $result['items']->firstWhere('variant_id', $ct->ma_bien_the) ?? [],
                [
                    'attributes' => $ct->bienThe?->giaTriThuocTinhs?->map(fn($gt) => [
                        'name'  => $gt->thuocTinh?->ten_tt,
                        'value' => $gt->gia_tri,
                    ])->values() ?? [],
                ]
            ))->values();
        }

        return $result;
    }
}
