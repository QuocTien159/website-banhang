<?php

namespace App\Models;

use App\Traits\GeneratesCustomId;
use Illuminate\Database\Eloquent\Model;

class LichSuXuLyTraHang extends Model
{
    use GeneratesCustomId;

    protected $table = 'lich_su_xu_ly_tra_hang';
    protected $primaryKey = 'ma_ls_xl_th';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'HT';

    protected $fillable = [
        'ma_ls_xl_th',
        'ma_yeu_cau',
        'loai_thao_tac',
        'gia_tri_cu',
        'gia_tri_moi',
        'ma_nguoi_xu_ly',
        'thoi_gian_xu_ly',
        'ghi_chu',
    ];

    protected $casts = [
        'thoi_gian_xu_ly' => 'datetime',
    ];

    public function nguoiXuLy()
    {
        return $this->belongsTo(KhachHang::class, 'ma_nguoi_xu_ly', 'ma_kh');
    }
}
