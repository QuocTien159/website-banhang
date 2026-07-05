<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DanhGia;
use App\Models\LichSuXuLyDanhGia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminReviewController extends Controller
{
    public function index(Request $request)
    {
        $query = DanhGia::with(['khachHang', 'sanPham', 'hinhAnhs', 'xuLyGanNhat.nguoiXuLy']);
        if ($status = $request->input('status')) $query->where('trang_thai', $status);
        if ($rating = $request->input('rating')) $query->where('so_sao', $rating);
        if ($search = $request->input('search')) $query->whereHas('sanPham', fn ($q) => $q->where('ten_sp', 'like', "%{$search}%"));

        return response()->json($query->orderByDesc('ngay_danh_gia')->get()->map(fn (DanhGia $r) => [
            'id' => $r->ma_danh_gia,
            'customer' => $r->khachHang?->ten_kh,
            'product' => $r->sanPham?->ten_sp,
            'rating' => $r->so_sao,
            'comment' => $r->noi_dung,
            'images' => $r->hinhAnhs->pluck('url_anh'),
            'status' => $r->trang_thai,
            'reply' => $r->phan_hoi_admin,
            'created_at' => $r->ngay_danh_gia?->toISOString(),
            'last_processed_by' => $r->xuLyGanNhat?->nguoiXuLy?->ten_kh,
            'last_processed_at' => $r->xuLyGanNhat?->thoi_gian_xu_ly?->toISOString(),
        ]));
    }

    public function moderate(Request $request, string $id)
    {
        $data = $request->validate(['status' => ['required', Rule::in(['approved', 'rejected'])]]);
        $review = DanhGia::findOrFail($id);
        $oldStatus = $review->trang_thai;

        $review->update(['trang_thai' => $data['status']]);
        $this->recordHistory(
            $review->ma_danh_gia,
            $data['status'] === 'approved' ? 'duyet' : 'tu_choi',
            $oldStatus,
            $data['status'],
            $request->user()->ma_kh
        );

        return response()->json(['message' => 'ÄÃ£ cáº­p nháº­t tráº¡ng thÃ¡i Ä‘Ã¡nh giÃ¡.']);
    }

    public function reply(Request $request, string $id)
    {
        $data = $request->validate(['reply' => 'required|string|max:1000']);
        $review = DanhGia::findOrFail($id);
        $oldReply = $review->phan_hoi_admin;

        $review->update(['phan_hoi_admin' => $data['reply'], 'ngay_phan_hoi' => now()]);
        $this->recordHistory($review->ma_danh_gia, 'phan_hoi', $oldReply, $data['reply'], $request->user()->ma_kh);

        return response()->json(['message' => 'ÄÃ£ lÆ°u pháº£n há»“i.']);
    }

    public function deleteReply(Request $request, string $id)
    {
        $review = DanhGia::findOrFail($id);
        $oldReply = $review->phan_hoi_admin;

        $review->update(['phan_hoi_admin' => null, 'ngay_phan_hoi' => null]);
        $this->recordHistory($review->ma_danh_gia, 'xoa_phan_hoi', $oldReply, null, $request->user()->ma_kh);

        return response()->json(['message' => 'ÄÃ£ xÃ³a pháº£n há»“i.']);
    }

    public function destroy(Request $request, string $id)
    {
        $review = DanhGia::with('hinhAnhs')->findOrFail($id);
        foreach ($review->hinhAnhs as $image) {
            $path = parse_url($image->url_anh, PHP_URL_PATH);
            if (str_contains($path, '/storage/')) Storage::disk('public')->delete(str($path)->after('/storage/')->toString());
        }

        $this->recordHistory($review->ma_danh_gia, 'xoa_danh_gia', $review->trang_thai, null, $request->user()->ma_kh);
        $review->delete();

        return response()->json(['message' => 'ÄÃ£ xÃ³a Ä‘Ã¡nh giÃ¡.']);
    }

    private function recordHistory(?string $reviewId, string $action, mixed $oldValue, mixed $newValue, ?string $actorId): void
    {
        LichSuXuLyDanhGia::create([
            'ma_danh_gia' => $reviewId,
            'loai_thao_tac' => $action,
            'gia_tri_cu' => is_scalar($oldValue) || $oldValue === null ? $oldValue : json_encode($oldValue),
            'gia_tri_moi' => is_scalar($newValue) || $newValue === null ? $newValue : json_encode($newValue),
            'ma_nguoi_xu_ly' => $actorId,
            'thoi_gian_xu_ly' => now(),
        ]);
    }
}
