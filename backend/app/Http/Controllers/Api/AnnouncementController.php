<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ThongBao;
use App\Services\CloudinaryMediaService;

class AnnouncementController extends Controller
{
    public function index()
    {
        $media = app(CloudinaryMediaService::class);

        return response()->json(ThongBao::with('hinhAnhs')->where('trang_thai', 'published')->where('ngay_xuat_ban', '<=', now())
            ->orderByDesc('ngay_xuat_ban')->take(10)->get()->map(function ($item) use ($media) {
                return [
                'id' => $item->ma_tb, 'title' => $item->tieu_de, 'content' => $item->noi_dung,
                'type' => $item->loai, 'published_at' => $item->ngay_xuat_ban?->toISOString(),
                'cover_image' => $this->formatImage($item->hinhAnhs->firstWhere('vai_tro_anh', 'announcement_cover'), $media),
                'images' => $item->hinhAnhs->where('vai_tro_anh', '!=', 'announcement_cover')->map(function ($image) use ($media) {
                    $urls = $media->urls($image->original_url ?? $image->url, $image->provider, $this->crop($image));
                    return [
                        'id' => $image->ma_anh_tb,
                        'url' => $urls['original_url'],
                        ...$urls,
                        'width' => $image->chieu_rong,
                        'height' => $image->chieu_cao,
                        'order' => $image->thu_tu,
                    ];
                })->values(),
            ];
            }));
    }

    private function formatImage($image, CloudinaryMediaService $media): ?array
    {
        if (!$image) return null;
        $urls = $media->urls($image->original_url ?? $image->url, $image->provider, $this->crop($image));
        return ['id' => $image->ma_anh_tb, 'url' => $urls['original_url'], ...$urls];
    }

    private function crop($image): array
    {
        return ['x' => $image->crop_x, 'y' => $image->crop_y, 'width' => $image->crop_width, 'height' => $image->crop_height, 'rotation' => $image->goc_xoay];
    }
}
