<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class HinhAnhSanPham extends Model
{
    use GeneratesCustomId;

    protected $table = 'hinh_anh_san_pham';
    protected $primaryKey = 'ma_anh';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'AN';

    protected $fillable = ['ma_anh', 'ma_sp', 'ma_bt', 'url', 'original_url', 'provider', 'cloudinary_public_id', 'chieu_rong', 'chieu_cao', 'kich_thuoc_byte', 'dinh_dang', 'crop_x', 'crop_y', 'crop_width', 'crop_height', 'goc_xoay', 'ty_le_khung_hinh', 'vai_tro_anh', 'anh_chinh', 'thu_tu'];

    protected $casts = ['anh_chinh' => 'boolean', 'thu_tu' => 'integer'];

    public function sanPham()
    {
        return $this->belongsTo(SanPham::class, 'ma_sp', 'ma_sp');
    }

    public function bienThe()
    {
        return $this->belongsTo(BienTheSanPham::class, 'ma_bt', 'ma_bt');
    }
}
