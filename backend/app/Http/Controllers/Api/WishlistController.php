<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    /** GET /api/wishlist */
    public function index(Request $request)
    {
        $user = $request->user();
        $wishlist = $user->sanPhamYeuThichs()
            ->with(['anhChinh', 'danhMuc', 'bienThes'])
            ->get();

        return response()->json($wishlist->map(fn($sp) => [
            'id'       => $sp->ma_sp,
            'name'     => $sp->ten_sp,
            'category' => $sp->danhMuc?->ten_dm,
            'price'    => (float)($sp->bienThes->where('trang_thai', true)->min('gia_ban') ?? $sp->gia_co_ban),
            'image'    => $sp->anhChinh?->url,
            'stock'    => $sp->bienThes->where('trang_thai', true)->sum('so_luong_ton'),
            'variant_count' => $sp->bienThes->where('trang_thai', true)->count(),
            'available' => $sp->trang_thai === 'active' && $sp->bienThes->where('trang_thai', true)->sum('so_luong_ton') > 0,
            'status' => $sp->trang_thai,
        ]));
    }

    /** POST /api/wishlist/{productId} — Toggle yêu thích */
    public function toggle(Request $request, string $productId)
    {
        $user = $request->user();
        $isWishlisted = $user->sanPhamYeuThichs()
            ->where('san_pham.ma_sp', $productId)
            ->exists();

        if ($isWishlisted) {
            $user->sanPhamYeuThichs()->detach($productId);
            return response()->json(['wishlisted' => false, 'message' => 'Đã xóa khỏi yêu thích.']);
        } else {
            $user->sanPhamYeuThichs()->attach($productId);
            return response()->json(['wishlisted' => true, 'message' => 'Đã thêm vào yêu thích.']);
        }
    }

    public function status(Request $request, string $productId)
    {
        return response()->json(['wishlisted' => $request->user()->sanPhamYeuThichs()->where('san_pham.ma_sp', $productId)->exists()]);
    }
}
