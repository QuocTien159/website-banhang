<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class SanPham extends Model
{
    use GeneratesCustomId;

    protected $table = 'san_pham';
    protected $primaryKey = 'ma_sp';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'SP';

    protected $fillable = [
        'ma_sp', 'ma_dm', 'ten_sp', 'mo_ta',
        'gia_co_ban', 'trang_thai', 'ngay_tao',
    ];

    protected $casts = [
        'gia_co_ban' => 'decimal:2',
        'ngay_tao' => 'datetime',
    ];

    // Relationships
    public function danhMuc()
    {
        return $this->belongsTo(DanhMuc::class, 'ma_dm', 'ma_dm');
    }

    public function bienThes()
    {
        return $this->hasMany(BienTheSanPham::class, 'ma_sp', 'ma_sp');
    }

    public function hinhAnhs()
    {
        return $this->hasMany(HinhAnhSanPham::class, 'ma_sp', 'ma_sp');
    }

    public function anhChinh()
    {
        return $this->hasOne(HinhAnhSanPham::class, 'ma_sp', 'ma_sp')
            ->where('anh_chinh', true);
    }

    public function danhGias()
    {
        return $this->hasMany(DanhGia::class, 'ma_sp', 'ma_sp');
    }

    public function khachHangYeuThichs()
    {
        return $this->belongsToMany(
            KhachHang::class,
            'danh_sach_yeu_thich',
            'ma_sp',
            'ma_kh'
        );
    }

    // Computed
    public function getTongTonKhoAttribute(): int
    {
        return $this->bienThes()->where('trang_thai', true)->sum('so_luong_ton');
    }

    public function getRatingTrungBinhAttribute(): float
    {
        $avg = $this->danhGias()->avg('so_sao');
        return round($avg ?? 0, 1);
    }

    public function getSoLuongDanhGiaAttribute(): int
    {
        return $this->danhGias()->count();
    }

    public function getSoDaBanAttribute(): int
    {
        return \DB::table('chi_tiet_don_hang')
            ->join('bien_the_san_pham', 'chi_tiet_don_hang.ma_bien_the', '=', 'bien_the_san_pham.ma_bt')
            ->where('bien_the_san_pham.ma_sp', $this->ma_sp)
            ->sum('chi_tiet_don_hang.so_luong');
    }
}
