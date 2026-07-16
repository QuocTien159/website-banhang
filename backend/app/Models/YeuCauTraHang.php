<?php

namespace App\Models;

use App\Traits\GeneratesCustomId;
use Illuminate\Database\Eloquent\Model;

class YeuCauTraHang extends Model
{
    use GeneratesCustomId;

    protected $table = 'yeu_cau_tra_hang';

    protected $primaryKey = 'ma_yeu_cau';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    public static string $idPrefix = 'TC';

    protected $fillable = [
        'ma_yeu_cau', 'ma_dh', 'ma_kh', 'ly_do', 'mo_ta', 'trang_thai',
        'ghi_chu_admin', 'ly_do_tu_choi', 'trang_thai_hoan_tien',
        'da_nhap_kho', 'ngay_yeu_cau', 'ngay_cap_nhat', 'ngay_nhan_hang', 'ngay_hoan_tien',
    ];

    protected $casts = [
        'ngay_yeu_cau' => 'datetime',
        'ngay_cap_nhat' => 'datetime',
        'ngay_nhan_hang' => 'datetime',
        'ngay_hoan_tien' => 'datetime',
        'da_nhap_kho' => 'boolean',
    ];

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'ma_dh', 'ma_dh');
    }

    public function chiTiets()
    {
        return $this->hasMany(ChiTietTraHang::class, 'ma_yeu_cau', 'ma_yeu_cau');
    }

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'ma_kh', 'ma_kh');
    }

    public function hinhAnhs()
    {
        return $this->hasMany(HinhAnhTraHang::class, 'ma_yeu_cau', 'ma_yeu_cau');
    }

    public function lichSuXuLy()
    {
        return $this->hasMany(LichSuXuLyTraHang::class, 'ma_yeu_cau', 'ma_yeu_cau');
    }

    public function xuLyGanNhat()
    {
        return $this->hasOne(LichSuXuLyTraHang::class, 'ma_yeu_cau', 'ma_yeu_cau')->latestOfMany('thoi_gian_xu_ly');
    }
}
