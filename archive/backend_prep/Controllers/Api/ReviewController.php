<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DanhGia;
use App\Models\DonHang;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /** GET /api/products/{productId}/reviews */
    public function index(string $productId)
    {
        $reviews = DanhGia::with(['khachHang', 'hinhAnhs'])
            ->where('ma_sp', $productId)
            ->orderBy('ngay_danh_gia', 'desc')
            ->paginate(10);

        return response()->json([
            'data' => $reviews->getCollection()->map(fn($dg) => [
                'id'      => $dg->ma_danh_gia,
                'name'    => $dg->khachHang?->ten_kh ?? 'Ẩn danh',
                'rating'  => $dg->so_sao,
                'date'    => $dg->ngay_danh_gia?->format('d/m/Y'),
                'comment' => $dg->noi_dung,
                'images'  => $dg->hinhAnhs->pluck('url_anh'),
            ]),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page'    => $reviews->lastPage(),
                'total'        => $reviews->total(),
                'avg_rating'   => round(DanhGia::where('ma_sp', $productId)->avg('so_sao') ?? 0, 1),
            ],
        ]);
    }

    /** POST /api/reviews — Gửi đánh giá (chỉ khi đã mua) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'product_id' => 'required|exists:san_pham,ma_sp',
            'so_sao'     => 'required|integer|min:1|max:5',
            'noi_dung'   => 'required|string|min:10|max:1000',
        ]);

        $user = $request->user();

        // Check if user purchased this product
        $hasBought = DonHang::where('ma_kh', $user->ma_kh)
            ->where('trang_thai', 'delivered')
            ->whereHas('chiTiets.bienThe', fn($q) => $q->where('ma_sp', $data['product_id']))
            ->exists();

        if (!$hasBought) {
            return response()->json([
                'message' => 'Bạn chỉ có thể đánh giá sản phẩm sau khi đã nhận hàng.',
            ], 403);
        }

        // Prevent duplicate reviews
        $alreadyReviewed = DanhGia::where('ma_kh', $user->ma_kh)
            ->where('ma_sp', $data['product_id'])
            ->exists();

        if ($alreadyReviewed) {
            return response()->json(['message' => 'Bạn đã đánh giá sản phẩm này rồi.'], 409);
        }

        $review = DanhGia::create([
            'ma_kh'          => $user->ma_kh,
            'ma_sp'          => $data['product_id'],
            'so_sao'         => $data['so_sao'],
            'noi_dung'       => $data['noi_dung'],
            'ngay_danh_gia'  => now(),
        ]);

        return response()->json([
            'message' => 'Cảm ơn bạn đã đánh giá sản phẩm!',
            'review'  => [
                'id'      => $review->ma_danh_gia,
                'name'    => $user->ten_kh,
                'rating'  => $review->so_sao,
                'date'    => $review->ngay_danh_gia->format('d/m/Y'),
                'comment' => $review->noi_dung,
            ],
        ], 201);
    }
}
