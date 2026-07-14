<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaKhuyenMai;
use App\Models\UuDaiTrangChu;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminHomepagePromotionController extends Controller
{
    public function show(Request $request)
    {
        $this->abortUnlessAdmin($request);
        return response()->json($this->format(UuDaiTrangChu::with('khuyenMai')->orderByDesc('ngay_cap_nhat')->first()));
    }

    public function voucherOptions(Request $request)
    {
        $this->abortUnlessAdmin($request);
        return response()->json(MaKhuyenMai::orderByDesc('bat_dau')->get()->filter->isAvailableForHomepage()->map(fn ($voucher) => $this->voucher($voucher))->values());
    }

    public function update(Request $request)
    {
        $this->abortUnlessAdmin($request);
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'voucher_id' => ['nullable', Rule::exists('ma_khuyen_mai', 'ma_km')],
            'label' => ['nullable', 'string', 'max:80'],
            'title' => ['nullable', 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:500'],
            'cta_text' => ['nullable', 'string', 'max:80'],
            'cta_url' => ['nullable', 'string', 'max:255', 'regex:/^\/(?!\/)/'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);

        $voucher = !empty($data['voucher_id']) ? MaKhuyenMai::findOrFail($data['voucher_id']) : null;
        if ($data['enabled'] && (!$voucher || !$voucher->isAvailableForHomepage() || blank($data['title'] ?? null) || blank($data['cta_text'] ?? null) || blank($data['cta_url'] ?? null))) {
            return response()->json(['message' => 'Chỉ được chọn voucher đang hiệu lực, còn lượt dùng và đang bật.'], 422);
        }

        $promotion = UuDaiTrangChu::with('khuyenMai')->orderByDesc('ngay_cap_nhat')->first() ?? new UuDaiTrangChu(['ngay_tao' => now()]);
        DB::transaction(function () use ($promotion, $data, $voucher) {
            if ($data['enabled']) UuDaiTrangChu::where('ma_uu_dai', '!=', $promotion->ma_uu_dai)->update(['kich_hoat' => false]);
            $promotion->fill([
                'kich_hoat' => $data['enabled'], 'ma_km' => $voucher?->ma_km, 'nhan' => $data['label'] ?? null,
                'tieu_de' => $data['title'] ?? null, 'mo_ta' => $data['description'] ?? null,
                'cta_text' => $data['cta_text'] ?? null, 'cta_url' => $data['cta_url'] ?? null,
                'bat_dau_hien_thi' => $data['starts_at'] ?? null, 'ket_thuc_hien_thi' => $data['ends_at'] ?? null,
                'ngay_cap_nhat' => now(),
            ]);

            $promotion->save();
        });
        return response()->json($this->format($promotion->fresh('khuyenMai')));
    }

    private function format(?UuDaiTrangChu $promotion): ?array
    {
        if (!$promotion) return null;
        return ['id' => $promotion->ma_uu_dai, 'enabled' => $promotion->kich_hoat, 'voucher' => $this->voucher($promotion->khuyenMai), 'voucher_id' => $promotion->ma_km, 'label' => $promotion->nhan, 'title' => $promotion->tieu_de, 'description' => $promotion->mo_ta, 'cta_text' => $promotion->cta_text, 'cta_url' => $promotion->cta_url, 'starts_at' => $promotion->bat_dau_hien_thi?->toISOString(), 'ends_at' => $promotion->ket_thuc_hien_thi?->toISOString()];
    }

    private function voucher(?MaKhuyenMai $voucher): ?array
    {
        if (!$voucher) return null;
        return ['id' => $voucher->ma_km, 'code' => $voucher->code, 'type' => $voucher->loai_giam, 'value' => (float) $voucher->gia_tri, 'minimum_order' => (float) $voucher->don_toi_thieu, 'maximum_discount' => $voucher->giam_toi_da ? (float) $voucher->giam_toi_da : null, 'starts_at' => $voucher->bat_dau?->toISOString(), 'ends_at' => $voucher->ket_thuc?->toISOString(), 'available' => $voucher->isAvailableForHomepage()];
    }

    private function abortUnlessAdmin(Request $request): void { abort_unless($request->user()?->isAdmin(), 403); }
}
