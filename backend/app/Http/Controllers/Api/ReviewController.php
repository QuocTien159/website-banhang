<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DanhGia;
use App\Models\DonHang;
use App\Models\HinhAnhDanhGia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ReviewController extends Controller
{
    public function index(string $productId)
    {
        $reviews = DanhGia::with(['khachHang', 'hinhAnhs'])->where('ma_sp', $productId)
            ->where('trang_thai', 'approved')->orderByDesc('ngay_danh_gia')->paginate(10);
        return response()->json([
            'data' => $reviews->getCollection()->map(fn ($review) => $this->format($review)),
            'meta' => ['current_page' => $reviews->currentPage(), 'last_page' => $reviews->lastPage(),
                'total' => $reviews->total(), 'avg_rating' => round(DanhGia::where('ma_sp', $productId)->where('trang_thai', 'approved')->avg('so_sao') ?? 0, 1)],
        ]);
    }

    public function mine(Request $request)
    {
        return response()->json(DanhGia::with(['sanPham.anhChinh', 'hinhAnhs'])->where('ma_kh', $request->user()->ma_kh)
            ->orderByDesc('ngay_danh_gia')->get()->map(fn ($review) => array_merge($this->format($review), [
                'product' => ['id' => $review->ma_sp, 'name' => $review->sanPham?->ten_sp, 'image' => $review->sanPham?->anhChinh?->url],
                'order_id' => $review->ma_dh, 'status' => $review->trang_thai,
            ])));
    }

    public function eligible(Request $request)
    {
        $orders = DonHang::with('chiTiets.bienThe.sanPham.anhChinh')->where('ma_kh', $request->user()->ma_kh)
            ->where('trang_thai', 'delivered')->get();
        return response()->json($orders->flatMap(fn ($order) => $order->chiTiets->map(function ($item) use ($order, $request) {
            $product = $item->bienThe?->sanPham;
            if (!$product) return null;
            $reviewed = DanhGia::where('ma_dh', $order->ma_dh)->where('ma_sp', $product->ma_sp)->exists();
            return $reviewed ? null : ['order_id' => $order->ma_dh, 'product_id' => $product->ma_sp, 'product_name' => $product->ten_sp, 'image' => $product->anhChinh?->url];
        }))->filter()->unique(fn ($item) => $item['order_id'].'-'.$item['product_id'])->values());
    }

    public function uploadImages(Request $request)
    {
        $data = $request->validate(['images' => 'required|array|max:5', 'images.*' => 'image|mimes:jpg,jpeg,png,webp|max:5120|dimensions:min_width=200,min_height=200']);
        return response()->json(['images' => collect($data['images'])->map(fn ($image) => Storage::disk('public')->url($image->store('reviews', 'public')))], 201);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => 'required|exists:don_hang,ma_dh', 'product_id' => 'required|exists:san_pham,ma_sp',
            'rating' => 'required|integer|min:1|max:5', 'comment' => 'required|string|min:10|max:1000',
            'images' => 'nullable|array|max:5', 'images.*' => 'url|max:500',
        ]);
        $user = $request->user();
        $eligible = DonHang::where('ma_dh', $data['order_id'])->where('ma_kh', $user->ma_kh)->where('trang_thai', 'delivered')
            ->whereHas('chiTiets.bienThe', fn ($query) => $query->where('ma_sp', $data['product_id']))->exists();
        abort_unless($eligible, 403, 'Chỉ sản phẩm trong đơn đã giao mới được đánh giá.');
        if (DanhGia::where('ma_dh', $data['order_id'])->where('ma_sp', $data['product_id'])->exists()) abort(409, 'Sản phẩm trong đơn hàng này đã được đánh giá.');

        $review = DB::transaction(function () use ($data, $user) {
            $review = DanhGia::create(['ma_kh' => $user->ma_kh, 'ma_sp' => $data['product_id'], 'ma_dh' => $data['order_id'],
                'so_sao' => $data['rating'], 'noi_dung' => $data['comment'], 'trang_thai' => 'pending', 'ngay_danh_gia' => now()]);
            foreach ($data['images'] ?? [] as $url) HinhAnhDanhGia::create(['ma_danh_gia' => $review->ma_danh_gia, 'url_anh' => $url, 'ngay_tao' => now()]);
            return $review;
        });
        return response()->json(['message' => 'Đánh giá đã được gửi và đang chờ duyệt.', 'review' => $this->format($review->load(['khachHang', 'hinhAnhs']))], 201);
    }

    private function format(DanhGia $review): array
    {
        return ['id' => $review->ma_danh_gia, 'name' => $review->khachHang?->ten_kh ?? 'Ẩn danh', 'rating' => $review->so_sao,
            'date' => $review->ngay_danh_gia?->format('d/m/Y'), 'comment' => $review->noi_dung, 'images' => $review->hinhAnhs->pluck('url_anh'),
            'admin_reply' => $review->phan_hoi_admin, 'admin_replied_at' => $review->ngay_phan_hoi?->toISOString()];
    }
}
