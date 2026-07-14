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
                'images' => $item->hinhAnhs->map(function ($image) use ($media) {
                    $urls = $media->urls($image->url, $image->provider);
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
}
