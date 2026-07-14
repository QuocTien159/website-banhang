<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UuDaiTrangChu;
use Carbon\Carbon;

class HomepagePromotionController extends Controller
{
    public function show()
    {
        $promotion = UuDaiTrangChu::with('khuyenMai')->where('kich_hoat', true)->orderBy('thu_tu')->first();
        if (!$promotion || !$promotion->khuyenMai?->isAvailableForHomepage() || !$this->isWithinDisplayWindow($promotion)) return response()->noContent();
        return response()->json(['label' => $promotion->nhan, 'title' => $promotion->tieu_de, 'description' => $promotion->mo_ta, 'cta_text' => $promotion->cta_text, 'cta_url' => $promotion->cta_url, 'voucher' => ['code' => $promotion->khuyenMai->code, 'type' => $promotion->khuyenMai->loai_giam, 'value' => (float) $promotion->khuyenMai->gia_tri, 'minimum_order' => (float) $promotion->khuyenMai->don_toi_thieu, 'ends_at' => $promotion->khuyenMai->ket_thuc?->toISOString()]]);
    }

    private function isWithinDisplayWindow(UuDaiTrangChu $promotion): bool
    {
        // HTML datetime-local values are entered in Vietnam time and stored without an offset.
        $now = now('Asia/Ho_Chi_Minh');
        $startsAt = $promotion->getRawOriginal('bat_dau_hien_thi')
            ? Carbon::parse($promotion->getRawOriginal('bat_dau_hien_thi'), 'Asia/Ho_Chi_Minh')
            : null;
        $endsAt = $promotion->getRawOriginal('ket_thuc_hien_thi')
            ? Carbon::parse($promotion->getRawOriginal('ket_thuc_hien_thi'), 'Asia/Ho_Chi_Minh')
            : null;

        return (!$startsAt || $now->gte($startsAt)) && (!$endsAt || $now->lte($endsAt));
    }
}
