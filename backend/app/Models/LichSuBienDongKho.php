<?php

namespace App\Models;

use App\Traits\GeneratesCustomId;
use Illuminate\Database\Eloquent\Model;

class LichSuBienDongKho extends Model
{
    use GeneratesCustomId;

    protected $table = 'lich_su_bien_dong_kho';
    protected $primaryKey = 'ma_ls_kho';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'LK';

    protected $fillable = [
        'ma_ls_kho',
        'ma_bien_the',
        'loai_bien_dong',
        'so_luong_thay_doi',
        'ton_kho_truoc',
        'ton_kho_sau',
        'ma_nguoi_thuc_hien',
        'thoi_gian',
        'ghi_chu',
        'ma_tham_chieu',
    ];

    protected $casts = [
        'thoi_gian' => 'datetime',
    ];

    public function bienThe()
    {
        return $this->belongsTo(BienTheSanPham::class, 'ma_bien_the', 'ma_bt')
            ->with(['sanPham.anhChinh', 'giaTriThuocTinhs.thuocTinh']);
    }

    public function nguoiThucHien()
    {
        return $this->belongsTo(KhachHang::class, 'ma_nguoi_thuc_hien', 'ma_kh');
    }
}
