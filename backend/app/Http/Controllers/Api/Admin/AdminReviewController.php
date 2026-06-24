<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DanhGia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = DanhGia::with(['khachHang', 'sanPham', 'hinhAnhs']);
        if ($status = $request->input('status')) $query->where('trang_thai', $status);
        if ($rating = $request->input('rating')) $query->where('so_sao', $rating);
        if ($search = $request->input('search')) $query->whereHas('sanPham', fn ($q) => $q->where('ten_sp', 'like', "%{$search}%"));
        return response()->json($query->orderByDesc('ngay_danh_gia')->get()->map(fn ($r) => [
            'id' => $r->ma_danh_gia, 'customer' => $r->khachHang?->ten_kh, 'product' => $r->sanPham?->ten_sp,
            'rating' => $r->so_sao, 'comment' => $r->noi_dung, 'images' => $r->hinhAnhs->pluck('url_anh'),
            'status' => $r->trang_thai, 'reply' => $r->phan_hoi_admin, 'created_at' => $r->ngay_danh_gia?->toISOString(),
        ]));
    }
    public function moderate(Request $request, string $id)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])]]);
        $review = DanhGia::findOrFail($id); $review->update(['trang_thai' => $data['status']]);
        return response()->json(['message' => 'Đã cập nhật trạng thái đánh giá.']);
    }
    public function reply(Request $request, string $id)
    {
        $data = $request->validate(['reply' => 'required|string|max:1000']);
        DanhGia::findOrFail($id)->update(['phan_hoi_admin' => $data['reply'], 'ngay_phan_hoi' => now()]);
        return response()->json(['message' => 'Đã lưu phản hồi.']);
    }
    public function deleteReply(string $id)
    {
        DanhGia::findOrFail($id)->update(['phan_hoi_admin' => null, 'ngay_phan_hoi' => null]);
        return response()->json(['message' => 'Đã xóa phản hồi.']);
    }
    public function destroy(string $id)
    {
        $review = DanhGia::with('hinhAnhs')->findOrFail($id);
        foreach ($review->hinhAnhs as $image) {
            $path = parse_url($image->url_anh, PHP_URL_PATH);
            if (str_contains($path, '/storage/')) Storage::disk('public')->delete(str($path)->after('/storage/')->toString());
        }
        $review->delete();
        return response()->json(['message' => 'Đã xóa đánh giá.']);
    }
}
