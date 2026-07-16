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
        'shipping_provider', 'shipping_service_id', 'shipping_service_type_id', 'shipping_service_name',
        'shipping_order_code', 'shipping_status', 'shipping_fee_breakdown', 'shipping_expected_delivery_at',
        'phuong_thuc_tt', 'payment_provider', 'payos_order_code', 'payment_link_id',
        'payment_checkout_url', 'trang_thai_thanh_toan', 'noi_dung_chuyen_khoan', 'qr_code_url',
        'khach_bao_da_chuyen_at', 'thanh_toan_xac_nhan_at', 'paid_at', 'thanh_toan_xac_nhan_boi',
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
        'shipping_fee_breakdown' => 'array',
        'shipping_expected_delivery_at' => 'datetime',
        'khach_bao_da_chuyen_at' => 'datetime',
        'thanh_toan_xac_nhan_at' => 'datetime',
        'paid_at' => 'datetime',
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

    public function lichSuXuLy()
    {
        return $this->hasMany(LichSuXuLyDonHang::class, 'ma_dh', 'ma_dh');
    }

    public function paymentLogs()
    {
        return $this->hasMany(PaymentLog::class, 'ma_dh', 'ma_dh');
    }

    public function vanDonVanChuyen()
    {
        return $this->hasOne(VanDonVanChuyen::class, 'ma_dh', 'ma_dh');
    }

    public function xuLyGanNhat()
    {
        return $this->hasOne(LichSuXuLyDonHang::class, 'ma_dh', 'ma_dh')->latestOfMany('thoi_gian_xu_ly');
    }
}
