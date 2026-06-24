<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ThongBao;

class AnnouncementController extends Controller
{
    public function index()
    {
        return response()->json(ThongBao::with('hinhAnhs')->where('trang_thai', 'published')->where('ngay_xuat_ban', '<=', now())
            ->orderByDesc('ngay_xuat_ban')->take(10)->get()->map(fn ($item) => [
                'id' => $item->ma_tb, 'title' => $item->tieu_de, 'content' => $item->noi_dung,
                'type' => $item->loai, 'published_at' => $item->ngay_xuat_ban?->toISOString(),
                'images' => $item->hinhAnhs->map(fn ($image) => [
                    'id' => $image->ma_anh_tb, 'url' => $image->url, 'order' => $image->thu_tu,
                ])->values(),
            ]));
    }
}
