<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GioHang;
use App\Models\ChiTietGioHang;
use App\Models\BienTheSanPham;
use Illuminate\Http\Request;

class CartController extends Controller
{
    /** GET /api/cart — Lấy giỏ hàng */
    public function index(Request $request)
    {
        $user = $request->user();
        $cart = $this->getOrCreateCart($user->ma_kh);
        return response()->json($this->formatCart($cart));
    }

    /** POST /api/cart/items — Thêm item vào giỏ */
    public function addItem(Request $request)
    {
        $data = $request->validate([
            'variant_id' => 'required|exists:bien_the_san_pham,ma_bt',
            'quantity'   => 'required|integer|min:1',
        ]);

        $variant = BienTheSanPham::where('ma_bt', $data['variant_id'])
            ->where('trang_thai', true)
            ->firstOrFail();

        if ($variant->so_luong_ton < $data['quantity']) {
            return response()->json([
                'message' => "Chỉ còn {$variant->so_luong_ton} sản phẩm trong kho.",
            ], 422);
        }

        $cart = $this->getOrCreateCart($request->user()->ma_kh);

        $existing = ChiTietGioHang::where('ma_gio_hang', $cart->ma_gio_hang)
            ->where('ma_bien_the', $data['variant_id'])
            ->first();

        if ($existing) {
            $newQty = $existing->so_luong + $data['quantity'];
            if ($newQty > $variant->so_luong_ton) {
                return response()->json([
                    'message' => "Không thể thêm. Tổng số lượng vượt quá tồn kho ({$variant->so_luong_ton}).",
                ], 422);
            }
            $existing->update(['so_luong' => $newQty]);
        } else {
            ChiTietGioHang::create([
                'ma_gio_hang' => $cart->ma_gio_hang,
                'ma_bien_the' => $data['variant_id'],
                'so_luong'    => $data['quantity'],
            ]);
        }

        $cart->load('chiTiets.bienThe.sanPham.anhChinh', 'chiTiets.bienThe.giaTriThuocTinhs.thuocTinh');
        return response()->json($this->formatCart($cart));
    }

    /** PUT /api/cart/items/{variantId} — Cập nhật số lượng */
    public function updateItem(Request $request, string $variantId)
    {
        $data = $request->validate([
            'quantity' => 'required|integer|min:0',
        ]);

        $cart = $this->getOrCreateCart($request->user()->ma_kh);
        $item = ChiTietGioHang::where('ma_gio_hang', $cart->ma_gio_hang)
            ->where('ma_bien_the', $variantId)
            ->first();

        if (!$item) {
            return response()->json(['message' => 'Sản phẩm không có trong giỏ hàng.'], 404);
        }

        if ($data['quantity'] === 0) {
            $item->delete();
        } else {
            $variant = BienTheSanPham::find($variantId);
            if ($data['quantity'] > $variant->so_luong_ton) {
                return response()->json([
                    'message' => "Chỉ còn {$variant->so_luong_ton} sản phẩm trong kho.",
                ], 422);
            }
            $item->update(['so_luong' => $data['quantity']]);
        }

        $cart->load('chiTiets.bienThe.sanPham.anhChinh', 'chiTiets.bienThe.giaTriThuocTinhs.thuocTinh');
        return response()->json($this->formatCart($cart));
    }

    /** DELETE /api/cart/items/{variantId} — Xóa item */
    public function removeItem(Request $request, string $variantId)
    {
        $cart = $this->getOrCreateCart($request->user()->ma_kh);
        ChiTietGioHang::where('ma_gio_hang', $cart->ma_gio_hang)
            ->where('ma_bien_the', $variantId)
            ->delete();

        $cart->load('chiTiets.bienThe.sanPham.anhChinh', 'chiTiets.bienThe.giaTriThuocTinhs.thuocTinh');
        return response()->json($this->formatCart($cart));
    }

    /** DELETE /api/cart — Xóa toàn bộ giỏ */
    public function clear(Request $request)
    {
        $cart = $this->getOrCreateCart($request->user()->ma_kh);
        ChiTietGioHang::where('ma_gio_hang', $cart->ma_gio_hang)->delete();
        return response()->json($this->formatCart($cart->fresh('chiTiets')));
    }

    // ── Helpers ─────────────────────────────────────
    private function getOrCreateCart(string $maKh): GioHang
    {
        $cart = GioHang::with([
            'chiTiets.bienThe.sanPham.anhChinh',
            'chiTiets.bienThe.giaTriThuocTinhs.thuocTinh',
        ])->where('ma_kh', $maKh)->first();

        if (!$cart) {
            $cart = GioHang::create(['ma_kh' => $maKh]);
            $cart->load('chiTiets');
        }

        return $cart;
    }

    private function formatCart(GioHang $cart): array
    {
        $items = $cart->chiTiets->map(function ($ct) {
            $bt = $ct->bienThe;
            $sp = $bt?->sanPham;
            return [
                'variant_id' => $ct->ma_bien_the,
                'product' => [
                    'id'    => $sp?->ma_sp,
                    'name'  => $sp?->ten_sp,
                    'image' => $sp?->anhChinh?->url,
                ],
                'attributes' => $bt?->giaTriThuocTinhs?->map(fn($gt) => [
                    'name'  => $gt->thuocTinh?->ten_tt,
                    'value' => $gt->gia_tri,
                ])->values() ?? [],
                'price'    => (float)$bt?->gia_ban,
                'quantity' => $ct->so_luong,
                'subtotal' => (float)$bt?->gia_ban * $ct->so_luong,
                'stock'    => $bt?->so_luong_ton,
            ];
        });

        $subtotal = $items->sum('subtotal');
        $shipping = $subtotal >= 500000 ? 0 : 30000;

        return [
            'cart_id'  => $cart->ma_gio_hang,
            'items'    => $items->values(),
            'subtotal' => $subtotal,
            'shipping' => $shipping,
            'total'    => $subtotal + $shipping,
        ];
    }
}
