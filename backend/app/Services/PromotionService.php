<?php

namespace App\Services;

use App\Models\MaKhuyenMai;
use Illuminate\Validation\ValidationException;

class PromotionService
{
    public function validate(string $code, string $customerId, float $subtotal, bool $lock = false): array
    {
        $query = MaKhuyenMai::whereRaw('UPPER(code) = ?', [mb_strtoupper(trim($code))]);
        if ($lock) $query->lockForUpdate();
        $promotion = $query->first();

        if (!$promotion || !$promotion->trang_thai) $this->fail('Mã khuyến mãi không hợp lệ.');
        if (now()->lt($promotion->bat_dau) || now()->gt($promotion->ket_thuc)) $this->fail('Mã khuyến mãi chưa bắt đầu hoặc đã hết hạn.');
        if ($subtotal < (float) $promotion->don_toi_thieu) $this->fail('Đơn hàng chưa đạt giá trị tối thiểu.');
        if ($promotion->gioi_han_su_dung !== null && $promotion->da_su_dung >= $promotion->gioi_han_su_dung) $this->fail('Mã khuyến mãi đã hết lượt sử dụng.');
        if (\DB::table('lich_su_khuyen_mai')->where('ma_km', $promotion->ma_km)->where('ma_kh', $customerId)->exists()) {
            $this->fail('Bạn đã sử dụng mã khuyến mãi này.');
        }

        return ['promotion' => $promotion, 'discount' => $promotion->calculateDiscount($subtotal)];
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['coupon_code' => $message]);
    }
}
