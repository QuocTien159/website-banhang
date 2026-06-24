<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class BienTheSanPham extends Model
{
    use GeneratesCustomId;

    protected $table = 'bien_the_san_pham';
    protected $primaryKey = 'ma_bt';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'BT';

    protected $fillable = [
        'ma_bt', 'ma_sp', 'sku', 'variant_signature',
        'gia_ban', 'so_luong_ton', 'nguong_canh_bao_ton', 'trang_thai',
    ];

    public static function signatureFor(array $attributes): string
    {
        $parts = collect($attributes)
            ->map(fn (array $attribute) => [
                'name' => mb_strtolower(trim($attribute['name'])),
                'value' => mb_strtolower(trim($attribute['value'])),
            ])
            ->sortBy(fn (array $attribute) => $attribute['name'].'='.$attribute['value'])
            ->map(fn (array $attribute) => $attribute['name'].'='.$attribute['value'])
            ->values()
            ->all();

        return hash('sha256', implode('|', $parts));
    }

    protected $casts = [
        'gia_ban' => 'decimal:2',
        'nguong_canh_bao_ton' => 'integer',
        'trang_thai' => 'boolean',
    ];

    public function sanPham()
    {
        return $this->belongsTo(SanPham::class, 'ma_sp', 'ma_sp');
    }

    public function giaTriThuocTinhs()
    {
        return $this->belongsToMany(
            GiaTriThuocTinh::class,
            'lien_ket_bien_the_gia_tri',
            'ma_bt',
            'ma_gt'
        )->with('thuocTinh');
    }

    public function chiTietGioHangs()
    {
        return $this->hasMany(ChiTietGioHang::class, 'ma_bien_the', 'ma_bt');
    }

    public function chiTietDonHangs()
    {
        return $this->hasMany(ChiTietDonHang::class, 'ma_bien_the', 'ma_bt');
    }

    public function lichSuKho()
    {
        return $this->hasMany(LichSuBienDongKho::class, 'ma_bien_the', 'ma_bt');
    }
}
