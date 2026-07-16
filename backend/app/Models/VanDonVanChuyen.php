<?php

namespace App\Models;

use App\Traits\GeneratesCustomId;
use Illuminate\Database\Eloquent\Model;

class VanDonVanChuyen extends Model
{
    use GeneratesCustomId;

    protected $table = 'van_don_van_chuyen';
    protected $primaryKey = 'ma_van_chuyen';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'VC';

    protected $fillable = [
        'ma_van_chuyen', 'ma_dh', 'nha_van_chuyen', 'moi_truong', 'ma_don_khach_hang',
        'ma_van_don_ghn', 'trang_thai_ghn_goc', 'trang_thai_van_chuyen', 'ngay_cap_nhat_ghn',
        'phi_van_chuyen', 'thoi_gian_giao_du_kien', 'du_lieu_gui', 'du_lieu_phan_hoi',
        'loi_dong_bo_cuoi', 'ngay_dong_bo', 'trang_thai_tao', 'so_lan_tao', 'lan_tao_cuoi',
        'ngay_tao', 'ngay_cap_nhat',
    ];

    protected $casts = [
        'phi_van_chuyen' => 'decimal:2',
        'du_lieu_gui' => 'array',
        'du_lieu_phan_hoi' => 'array',
        'ngay_cap_nhat_ghn' => 'datetime',
        'thoi_gian_giao_du_kien' => 'datetime',
        'ngay_dong_bo' => 'datetime',
        'lan_tao_cuoi' => 'datetime',
        'ngay_tao' => 'datetime',
        'ngay_cap_nhat' => 'datetime',
    ];

    public function donHang()
    {
        return $this->belongsTo(DonHang::class, 'ma_dh', 'ma_dh');
    }

    public function suKiens()
    {
        return $this->hasMany(SuKienVanChuyen::class, 'ma_van_chuyen', 'ma_van_chuyen')
            ->orderBy('thoi_gian_su_kien');
    }

    public function isTerminal(): bool
    {
        return in_array($this->trang_thai_van_chuyen, ['delivered', 'cancelled', 'returned'], true);
    }

    public function canRetryCreation(): bool
    {
        return !$this->ma_van_don_ghn
            && in_array($this->trang_thai_tao, ['chua_tao', 'that_bai'], true);
    }
}
