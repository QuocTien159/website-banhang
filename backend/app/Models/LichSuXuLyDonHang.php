<?php

namespace App\Models;

use App\Traits\GeneratesCustomId;
use Illuminate\Database\Eloquent\Model;

class LichSuXuLyDonHang extends Model
{
    use GeneratesCustomId;

    protected $table = 'lich_su_xu_ly_don_hang';
    protected $primaryKey = 'ma_ls_xl_dh';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'HD';

    protected $fillable = [
        'ma_ls_xl_dh',
        'ma_dh',
        'trang_thai_cu',
        'trang_thai_moi',
        'ma_nguoi_xu_ly',
        'thoi_gian_xu_ly',
        'ghi_chu',
        'nguon',
        'ma_van_chuyen',
    ];

    protected $casts = [
        'thoi_gian_xu_ly' => 'datetime',
    ];

    public function nguoiXuLy()
    {
        return $this->belongsTo(KhachHang::class, 'ma_nguoi_xu_ly', 'ma_kh');
    }

    public function vanDonVanChuyen()
    {
        return $this->belongsTo(VanDonVanChuyen::class, 'ma_van_chuyen', 'ma_van_chuyen');
    }
}
