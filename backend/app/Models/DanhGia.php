<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class DanhGia extends Model
{
    use GeneratesCustomId;

    protected $table = 'danh_gia';
    protected $primaryKey = 'ma_danh_gia';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'DG';

    protected $fillable = [
        'ma_danh_gia', 'ma_kh', 'ma_sp', 'ma_dh', 'so_sao', 'noi_dung',
        'trang_thai', 'phan_hoi_admin', 'ngay_phan_hoi', 'ngay_danh_gia', 'ngay_cap_nhat',
    ];

    protected $casts = [
        'ngay_danh_gia' => 'datetime',
        'ngay_cap_nhat' => 'datetime',
        'so_sao' => 'integer',
        'ngay_phan_hoi' => 'datetime',
    ];

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'ma_kh', 'ma_kh');
    }

    public function sanPham()
    {
        return $this->belongsTo(SanPham::class, 'ma_sp', 'ma_sp');
    }

    public function hinhAnhs()
    {
        return $this->hasMany(HinhAnhDanhGia::class, 'ma_danh_gia', 'ma_danh_gia');
    }

    public function lichSuXuLy()
    {
        return $this->hasMany(LichSuXuLyDanhGia::class, 'ma_danh_gia', 'ma_danh_gia');
    }

    public function xuLyGanNhat()
    {
        return $this->hasOne(LichSuXuLyDanhGia::class, 'ma_danh_gia', 'ma_danh_gia')->latestOfMany('thoi_gian_xu_ly');
    }
}
