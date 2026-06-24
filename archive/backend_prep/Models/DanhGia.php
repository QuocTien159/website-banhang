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
        'ma_danh_gia', 'ma_kh', 'ma_sp', 'so_sao', 'noi_dung', 'ngay_danh_gia',
    ];

    protected $casts = [
        'ngay_danh_gia' => 'datetime',
        'so_sao' => 'integer',
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
}


class HinhAnhDanhGia extends Model
{
    use GeneratesCustomId;

    protected $table = 'hinh_anh_danh_gia';
    protected $primaryKey = 'ma_anh_dg';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'AG';

    protected $fillable = ['ma_anh_dg', 'ma_danh_gia', 'url_anh', 'ngay_tao'];

    protected $casts = ['ngay_tao' => 'datetime'];

    public function danhGia()
    {
        return $this->belongsTo(DanhGia::class, 'ma_danh_gia', 'ma_danh_gia');
    }
}


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
        'ma_yeu_cau', 'ma_dh', 'ly_do', 'trang_thai', 'ngay_yeu_cau',
    ];

    protected $casts = ['ngay_yeu_cau' => 'datetime'];

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'ma_dh', 'ma_dh');
    }

    public function chiTiets()
    {
        return $this->hasMany(ChiTietTraHang::class, 'ma_yeu_cau', 'ma_yeu_cau');
    }
}


class ChiTietTraHang extends Model
{
    protected $table = 'chi_tiet_tra_hang';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['ma_yeu_cau', 'ma_bien_the', 'so_luong', 'ghi_chu'];

    public function yeuCauTraHang()
    {
        return $this->belongsTo(YeuCauTraHang::class, 'ma_yeu_cau', 'ma_yeu_cau');
    }

    public function bienThe()
    {
        return $this->belongsTo(BienTheSanPham::class, 'ma_bien_the', 'ma_bt');
    }
}
