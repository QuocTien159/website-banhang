<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

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
        'da_nhap_kho', 'ngay_yeu_cau', 'ngay_cap_nhat',
    ];

    protected $casts = [
        'ngay_yeu_cau' => 'datetime',
        'ngay_cap_nhat' => 'datetime',
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
}
