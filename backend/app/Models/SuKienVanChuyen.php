<?php

namespace App\Models;

use App\Traits\GeneratesCustomId;
use Illuminate\Database\Eloquent\Model;

class SuKienVanChuyen extends Model
{
    use GeneratesCustomId;

    protected $table = 'su_kien_van_chuyen';
    protected $primaryKey = 'ma_su_kien';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'SK';

    protected $fillable = [
        'ma_su_kien', 'ma_van_chuyen', 'ma_dh', 'nguon', 'trang_thai_ghn_goc',
        'trang_thai_van_chuyen', 'thoi_gian_su_kien', 'ma_bam_payload', 'du_lieu_payload',
        'ghi_chu', 'da_bo_qua', 'ngay_tao',
    ];

    protected $casts = [
        'thoi_gian_su_kien' => 'datetime',
        'du_lieu_payload' => 'array',
        'da_bo_qua' => 'boolean',
        'ngay_tao' => 'datetime',
    ];

    public function vanDon()
    {
        return $this->belongsTo(VanDonVanChuyen::class, 'ma_van_chuyen', 'ma_van_chuyen');
    }

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'ma_dh', 'ma_dh');
    }
}
