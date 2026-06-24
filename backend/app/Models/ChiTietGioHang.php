<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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
