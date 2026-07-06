<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DanhGia;
use App\Models\DonHang;
use App\Models\HinhAnhDanhGia;
use App\Models\LichSuXuLyDanhGia;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    public function index(string $productId)
    {
        $reviews = DanhGia::with(['khachHang', 'hinhAnhs'])
            ->where('ma_sp', $productId)
            ->where('trang_thai', 'approved')
            ->orderByDesc('ngay_danh_gia')
            ->paginate(10);

        return response()->json([
            'data' => $reviews->getCollection()->map(fn (DanhGia $review) => $this->format($review)),
            'meta' => [
                'current_page' => $reviews->currentPage(),
                'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(),
                'avg_rating' => round(DanhGia::where('ma_sp', $productId)->where('trang_thai', 'approved')->avg('so_sao') ?? 0, 1),
            ],
        ]);
    }

    public function mine(Request $request)
    {
        return response()->json(DanhGia::with(['sanPham.anhChinh', 'hinhAnhs'])
            ->where('ma_kh', $request->user()->ma_kh)
            ->orderByDesc(DB::raw('COALESCE(ngay_cap_nhat, ngay_danh_gia)'))
            ->get()
            ->map(fn (DanhGia $review) => array_merge($this->format($review), [
                'product' => [
                    'id' => $review->ma_sp,
                    'name' => $review->sanPham?->ten_sp,
                    'image' => $review->sanPham?->anhChinh?->url,
                ],
                'order_id' => $review->ma_dh,
            ])));
    }

    public function eligible(Request $request)
    {
        $reviewedProductIds = DanhGia::where('ma_kh', $request->user()->ma_kh)->pluck('ma_sp')->all();

        $orders = DonHang::with('chiTiets.bienThe.sanPham.anhChinh')
            ->where('ma_kh', $request->user()->ma_kh)
            ->where('trang_thai', 'delivered')
            ->get();

        return response()->json($orders->flatMap(fn (DonHang $order) => $order->chiTiets->map(function ($item) use ($order, $reviewedProductIds) {
            $product = $item->bienThe?->sanPham;
            if (!$product || in_array($product->ma_sp, $reviewedProductIds, true)) {
                return null;
            }

            return [
                'order_id' => $order->ma_dh,
                'product_id' => $product->ma_sp,
                'product_name' => $product->ten_sp,
                'image' => $product->anhChinh?->url,
            ];
        }))->filter()->unique(fn ($item) => $item['product_id'])->values());
    }

    public function uploadImages(Request $request)
    {
        $data = $request->validate([
            'images' => 'required|array|max:5',
            'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:min_width=200,min_height=200',
        ]);

        return response()->json([
            'images' => collect($data['images'])->map(fn ($image) => Storage::disk('public')->url($image->store('reviews', 'public'))),
        ], 201);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|exists:don_hang,ma_dh',
            'product_id' => 'required|exists:san_pham,ma_sp',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:1000',
            'images' => 'nullable|array|max:5',
            'images.*' => 'url|max:500',
        ]);

        $user = $request->user();
        $eligible = DonHang::where('ma_dh', $data['order_id'])
            ->where('ma_kh', $user->ma_kh)
            ->where('trang_thai', 'delivered')
            ->whereHas('chiTiets.bienThe', fn ($query) => $query->where('ma_sp', $data['product_id']))
            ->exists();

        abort_unless($eligible, 403, 'Only delivered purchased products can be reviewed.');

        if (DanhGia::where('ma_kh', $user->ma_kh)->where('ma_sp', $data['product_id'])->exists()) {
            return response()->json([
                'message' => 'You already reviewed this product. Please edit your existing review to update it.',
            ], 409);
        }

        try {
            $review = DB::transaction(function () use ($data, $user) {
                $review = DanhGia::create([
                    'ma_kh' => $user->ma_kh,
                    'ma_sp' => $data['product_id'],
                    'ma_dh' => $data['order_id'],
                    'so_sao' => $data['rating'],
                    'noi_dung' => $data['comment'],
                    'trang_thai' => 'pending',
                    'ngay_danh_gia' => now(),
                ]);

                $this->syncImages($review, $data['images'] ?? []);

                return $review;
            });
        } catch (QueryException $exception) {
            if (str_contains($exception->getMessage(), 'review_customer_product_unique')) {
                return response()->json([
                    'message' => 'You already reviewed this product. Please edit your existing review to update it.',
                ], 409);
            }

            throw $exception;
        }

        return response()->json([
            'message' => 'Review submitted and waiting for approval.',
            'review' => $this->format($review->load(['khachHang', 'hinhAnhs'])),
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $data = $request->validate([
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:10|max:1000',
            'images' => 'nullable|array|max:5',
            'images.*' => 'url|max:500',
        ]);

        $review = DanhGia::with('hinhAnhs')->findOrFail($id);
        abort_unless($review->ma_kh === $request->user()->ma_kh, 403, 'Cannot edit another customer review.');

        DB::transaction(function () use ($review, $data, $request) {
            $oldValue = json_encode([
                'rating' => $review->so_sao,
                'comment' => $review->noi_dung,
                'status' => $review->trang_thai,
            ]);

            $review->update([
                'so_sao' => $data['rating'],
                'noi_dung' => $data['comment'],
                'trang_thai' => 'pending',
                'phan_hoi_admin' => null,
                'ngay_phan_hoi' => null,
                'ngay_cap_nhat' => now(),
            ]);

            $this->syncImages($review, $data['images'] ?? []);

            if (class_exists(LichSuXuLyDanhGia::class)) {
                LichSuXuLyDanhGia::create([
                    'ma_danh_gia' => $review->ma_danh_gia,
                    'loai_thao_tac' => 'khach_hang_chinh_sua',
                    'gia_tri_cu' => $oldValue,
                    'gia_tri_moi' => json_encode(['rating' => $data['rating'], 'comment' => $data['comment'], 'status' => 'pending']),
                    'ma_nguoi_xu_ly' => $request->user()->ma_kh,
                    'thoi_gian_xu_ly' => now(),
                ]);
            }
        });

        return response()->json([
            'message' => 'Review resubmitted and waiting for approval.',
            'review' => $this->format($review->fresh(['khachHang', 'hinhAnhs'])),
        ]);
    }

    private function syncImages(DanhGia $review, array $images): void
    {
        $review->hinhAnhs()->delete();

        foreach ($images as $url) {
            HinhAnhDanhGia::create([
                'ma_danh_gia' => $review->ma_danh_gia,
                'url_anh' => $url,
                'ngay_tao' => now(),
            ]);
        }
    }

    private function format(DanhGia $review): array
    {
        return [
            'id' => $review->ma_danh_gia,
            'name' => $review->khachHang?->ten_kh ?? 'An danh',
            'rating' => $review->so_sao,
            'date' => $review->ngay_danh_gia?->format('d/m/Y'),
            'created_at' => $review->ngay_danh_gia?->toISOString(),
            'updated_at' => $review->ngay_cap_nhat?->toISOString(),
            'comment' => $review->noi_dung,
            'images' => $review->hinhAnhs->pluck('url_anh'),
            'status' => $review->trang_thai,
            'admin_reply' => $review->phan_hoi_admin,
            'admin_replied_at' => $review->ngay_phan_hoi?->toISOString(),
        ];
    }
}
