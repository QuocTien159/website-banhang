<?php

namespace App\Models;

use App\Traits\GeneratesCustomId;
use Illuminate\Database\Eloquent\Model;

class YeuCauDieuChinhKho extends Model
{
    use GeneratesCustomId;

    protected $table = 'yeu_cau_dieu_chinh_kho';

    protected $primaryKey = 'ma_ycdck';

    public $incrementing = false;

    protected $keyType = 'string';

    public $timestamps = false;

    public static string $idPrefix = 'DC';

    protected $fillable = [
        'ma_ycdck',
        'ma_bien_the',
        'ton_kho_tai_luc_tao',
        'ton_kho_de_xuat',
        'ly_do',
        'trang_thai',
        'ma_nguoi_tao',
        'ma_nguoi_duyet',
        'ghi_chu_duyet',
        'ngay_tao',
        'ngay_duyet',
    ];

    protected $casts = [
        'ngay_tao' => 'datetime',
        'ngay_duyet' => 'datetime',
    ];

    public function bienThe()
    {
        return $this->belongsTo(BienTheSanPham::class, 'ma_bien_the', 'ma_bt');
    }

    public function nguoiTao()
    {
        return $this->belongsTo(KhachHang::class, 'ma_nguoi_tao', 'ma_kh');
    }

    public function nguoiDuyet()
    {
        return $this->belongsTo(KhachHang::class, 'ma_nguoi_duyet', 'ma_kh');
    }
}
