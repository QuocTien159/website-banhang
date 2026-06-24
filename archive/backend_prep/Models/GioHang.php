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

    protected $fillable = ['ma_anh', 'ma_sp', 'url', 'anh_chinh'];

    protected $casts = ['anh_chinh' => 'boolean'];

    public function sanPham()
    {
        return $this->belongsTo(SanPham::class, 'ma_sp', 'ma_sp');
    }
}


class GioHang extends Model
{
    use GeneratesCustomId;

    protected $table = 'gio_hang';
    protected $primaryKey = 'ma_gio_hang';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'GH';

    protected $fillable = ['ma_gio_hang', 'ma_kh'];

    public function khachHang()
    {
        return $this->belongsTo(KhachHang::class, 'ma_kh', 'ma_kh');
    }

    public function chiTiets()
    {
        return $this->hasMany(ChiTietGioHang::class, 'ma_gio_hang', 'ma_gio_hang');
    }
}


class ChiTietGioHang extends Model
{
    protected $table = 'chi_tiet_gio_hang';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['ma_gio_hang', 'ma_bien_the', 'so_luong'];

    public function gioHang()
    {
        return $this->belongsTo(GioHang::class, 'ma_gio_hang', 'ma_gio_hang');
    }

    public function bienThe()
    {
        return $this->belongsTo(BienTheSanPham::class, 'ma_bien_the', 'ma_bt')
            ->with(['sanPham.anhChinh', 'giaTriThuocTinhs']);
    }
}
