<?php

namespace App\Models;

use App\Traits\GeneratesCustomId;
use Illuminate\Database\Eloquent\Model;

class UuDaiTrangChu extends Model
{
    use GeneratesCustomId;

    protected $table = 'uu_dai_trang_chu';
    protected $primaryKey = 'ma_uu_dai';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    public static string $idPrefix = 'UD';

    protected $fillable = [
        'ma_uu_dai', 'kich_hoat', 'ma_km', 'nhan', 'tieu_de', 'mo_ta', 'cta_text', 'cta_url',
        'bat_dau_hien_thi', 'ket_thuc_hien_thi', 'thu_tu', 'banner_url', 'banner_original_url',
        'banner_provider', 'banner_cloudinary_public_id', 'banner_width', 'banner_height',
        'banner_kich_thuoc_byte', 'banner_dinh_dang', 'banner_crop_x', 'banner_crop_y',
        'banner_crop_width', 'banner_crop_height', 'banner_goc_xoay', 'banner_ty_le_khung_hinh',
        'ngay_tao', 'ngay_cap_nhat',
    ];

    protected $casts = [
        'kich_hoat' => 'boolean', 'bat_dau_hien_thi' => 'datetime', 'ket_thuc_hien_thi' => 'datetime',
        'ngay_tao' => 'datetime', 'ngay_cap_nhat' => 'datetime',
    ];

    public function khuyenMai()
    {
        return $this->belongsTo(MaKhuyenMai::class, 'ma_km', 'ma_km');
    }
}
