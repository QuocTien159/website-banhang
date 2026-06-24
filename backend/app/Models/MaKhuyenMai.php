<?php

namespace App\Models;

use App\Traits\GeneratesCustomId;
use Illuminate\Database\Eloquent\Model;

class MaKhuyenMai extends Model
{
    use GeneratesCustomId;

    protected $table = 'ma_khuyen_mai';
    protected $primaryKey = 'ma_km';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    public static string $idPrefix = 'KM';

    protected $fillable = ['ma_km', 'code', 'loai_giam', 'gia_tri', 'don_toi_thieu', 'giam_toi_da', 'bat_dau', 'ket_thuc', 'gioi_han_su_dung', 'da_su_dung', 'trang_thai'];
    protected $casts = ['gia_tri' => 'decimal:2', 'don_toi_thieu' => 'decimal:2', 'giam_toi_da' => 'decimal:2', 'bat_dau' => 'datetime', 'ket_thuc' => 'datetime', 'trang_thai' => 'boolean'];

    public function calculateDiscount(float $subtotal): float
    {
        $discount = $this->loai_giam === 'percent' ? $subtotal * ((float) $this->gia_tri / 100) : (float) $this->gia_tri;
        if ($this->giam_toi_da !== null) $discount = min($discount, (float) $this->giam_toi_da);
        return round(min($discount, $subtotal), 2);
    }
}
