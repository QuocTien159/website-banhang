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
        'ma_dh', 'ma_kh', 'ngay_dat', 'tam_tinh', 'phi_van_chuyen',
        'loai_khu_vuc_giao', 'shipping_zone', 'ma_km', 'ma_khuyen_mai', 'so_tien_giam', 'tong_tien',
        'phuong_thuc_tt', 'trang_thai_thanh_toan', 'noi_dung_chuyen_khoan', 'qr_code_url',
        'khach_bao_da_chuyen_at', 'thanh_toan_xac_nhan_at', 'thanh_toan_xac_nhan_boi',
        'dia_chi_giao', 'province_type', 'ma_tinh_thanh', 'ma_quan_huyen', 'ma_phuong_xa',
        'tinh_thanh', 'quan_huyen', 'phuong_xa', 'dia_chi_chi_tiet',
        'trang_thai', 'ghi_chu',
    ];

    protected $casts = [
        'ngay_dat' => 'datetime',
        'tong_tien' => 'decimal:2',
        'tam_tinh' => 'decimal:2',
        'phi_van_chuyen' => 'decimal:2',
        'so_tien_giam' => 'decimal:2',
        'khach_bao_da_chuyen_at' => 'datetime',
        'thanh_toan_xac_nhan_at' => 'datetime',
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
