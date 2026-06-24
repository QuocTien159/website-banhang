<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChiTietPhieuNhapKho extends Model
{
    protected $table = 'chi_tiet_phieu_nhap_kho';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['ma_pnk', 'ma_bien_the', 'so_luong', 'ghi_chu'];

    public function phieuNhap()
    {
        return $this->belongsTo(PhieuNhapKho::class, 'ma_pnk', 'ma_pnk');
    }

    public function bienThe()
    {
        return $this->belongsTo(BienTheSanPham::class, 'ma_bien_the', 'ma_bt')
            ->with(['sanPham.anhChinh', 'giaTriThuocTinhs.thuocTinh']);
    }
}
