<?php

namespace App\Models;

use App\Traits\GeneratesCustomId;
use Illuminate\Database\Eloquent\Model;

class PhieuNhapKho extends Model
{
    use GeneratesCustomId;

    protected $table = 'phieu_nhap_kho';
    protected $primaryKey = 'ma_pnk';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'PN';

    protected $fillable = [
        'ma_pnk',
        'ma_phieu',
        'ngay_nhap',
        'ma_nguoi_nhap',
        'ghi_chu',
        'ngay_tao',
    ];

    protected $casts = [
        'ngay_nhap' => 'date',
        'ngay_tao' => 'datetime',
    ];

    public function chiTiets()
    {
        return $this->hasMany(ChiTietPhieuNhapKho::class, 'ma_pnk', 'ma_pnk');
    }

    public function nguoiNhap()
    {
        return $this->belongsTo(KhachHang::class, 'ma_nguoi_nhap', 'ma_kh');
    }
}
