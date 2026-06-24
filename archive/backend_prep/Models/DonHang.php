<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class DonHang extends Model
{
    use GeneratesCustomId;

    protected $table = 'don_hang';
    protected $primaryKey = 'ma_dh';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'DH';

    protected $fillable = [
        'ma_dh', 'ma_kh', 'ngay_dat', 'tong_tien',
        'phuong_thuc_tt', 'dia_chi_giao', 'trang_thai', 'ghi_chu',
    ];

    protected $casts = [
        'ngay_dat' => 'datetime',
        'tong_tien' => 'decimal:2',
    ];

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'ma_kh', 'ma_kh');
    }

    public function chiTiets()
    {
        return $this->hasMany(ChiTietDonHang::class, 'ma_dh', 'ma_dh');
    }

    public function yeuCauTraHangs()
    {
        return $this->hasMany(YeuCauTraHang::class, 'ma_dh', 'ma_dh');
    }
}


class ChiTietDonHang extends Model
{
    protected $table = 'chi_tiet_don_hang';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['ma_dh', 'ma_bien_the', 'so_luong', 'don_gia'];

    protected $casts = ['don_gia' => 'decimal:2'];

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'ma_dh', 'ma_dh');
    }

    public function bienThe()
    {
        return $this->belongsTo(BienTheSanPham::class, 'ma_bien_the', 'ma_bt')
            ->with(['sanPham.anhChinh', 'giaTriThuocTinhs']);
    }
}
