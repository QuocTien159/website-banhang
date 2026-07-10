<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentShippingSetting extends Model
{
    protected $table = 'cau_hinh_thanh_toan_van_chuyen';

    protected $fillable = [
        'shipping_provider',
        'ghn_enabled',
        'ghn_environment',
        'ghn_shop_id',
        'pickup_name',
        'pickup_phone',
        'pickup_province_id',
        'pickup_province_name',
        'pickup_district_id',
        'pickup_district_name',
        'pickup_ward_code',
        'pickup_ward_name',
        'pickup_address',
        'default_weight_gram',
        'default_length_cm',
        'default_width_cm',
        'default_height_cm',
        'phi_noi_thanh',
        'phi_ngoai_thanh',
        'phi_tinh_khac',
        'mien_phi_ship_bat',
        'nguong_mien_phi_ship',
        'tinh_thanh_shop',
        'ma_tinh_thanh_shop',
        'quan_huyen_noi_thanh',
        'ma_quan_huyen_noi_thanh',
        'ma_ngan_hang',
        'ten_ngan_hang',
        'so_tai_khoan',
        'ten_chu_tai_khoan',
        'mau_noi_dung_chuyen_khoan',
    ];

    protected $casts = [
        'phi_noi_thanh' => 'decimal:2',
        'phi_ngoai_thanh' => 'decimal:2',
        'phi_tinh_khac' => 'decimal:2',
        'mien_phi_ship_bat' => 'boolean',
        'ghn_enabled' => 'boolean',
        'nguong_mien_phi_ship' => 'decimal:2',
        'quan_huyen_noi_thanh' => 'array',
        'ma_quan_huyen_noi_thanh' => 'array',
        'pickup_province_id' => 'integer',
        'pickup_district_id' => 'integer',
        'default_weight_gram' => 'integer',
        'default_length_cm' => 'integer',
        'default_width_cm' => 'integer',
        'default_height_cm' => 'integer',
    ];

    public static function current(): self
    {
        return self::query()->first() ?? self::query()->create([]);
    }
}
