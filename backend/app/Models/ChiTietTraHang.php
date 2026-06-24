<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChiTietTraHang extends Model
{
    protected $table = 'chi_tiet_tra_hang';
    protected $primaryKey = null;
    public $incrementing = false;
    public $timestamps = false;

    protected $fillable = ['ma_yeu_cau', 'ma_bien_the', 'ma_sp', 'so_luong', 'ly_do', 'mo_ta', 'ghi_chu'];

    public function yeuCauTraHang()
    {
        return $this->belongsTo(YeuCauTraHang::class, 'ma_yeu_cau', 'ma_yeu_cau');
    }

    public function bienThe()
    {
        return $this->belongsTo(BienTheSanPham::class, 'ma_bien_the', 'ma_bt')
            ->with(['sanPham.anhChinh', 'giaTriThuocTinhs.thuocTinh']);
    }

    public function sanPham()
    {
        return $this->belongsTo(SanPham::class, 'ma_sp', 'ma_sp');
    }

}
