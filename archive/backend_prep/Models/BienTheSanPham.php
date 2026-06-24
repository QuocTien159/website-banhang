<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class BienTheSanPham extends Model
{
    use GeneratesCustomId;

    protected $table = 'bien_the_san_pham';
    protected $primaryKey = 'ma_bt';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'BT';

    protected $fillable = [
        'ma_bt', 'ma_sp', 'sku', 'gia_ban', 'so_luong_ton', 'trang_thai',
    ];

    protected $casts = [
        'gia_ban' => 'decimal:2',
        'trang_thai' => 'boolean',
    ];

    public function sanPham()
    {
        return $this->belongsTo(SanPham::class, 'ma_sp', 'ma_sp');
    }

    public function giaTriThuocTinhs()
    {
        return $this->belongsToMany(
            GiaTriThuocTinh::class,
            'lien_ket_bien_the_gia_tri',
            'ma_bt',
            'ma_gt'
        )->with('thuocTinh');
    }

    public function chiTietGioHangs()
    {
        return $this->hasMany(ChiTietGioHang::class, 'ma_bien_the', 'ma_bt');
    }

    public function chiTietDonHangs()
    {
        return $this->hasMany(ChiTietDonHang::class, 'ma_bien_the', 'ma_bt');
    }
}


// ──────────────────────────────────────
class ThuocTinh extends Model
{
    protected $table = 'thuoc_tinh';
    protected $primaryKey = 'ma_tt';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    use GeneratesCustomId;
    public static string $idPrefix = 'TT';

    protected $fillable = ['ma_tt', 'ten_tt'];

    public function giaTriThuocTinhs()
    {
        return $this->hasMany(GiaTriThuocTinh::class, 'ma_tt', 'ma_tt');
    }
}


class GiaTriThuocTinh extends Model
{
    protected $table = 'gia_tri_thuoc_tinh';
    protected $primaryKey = 'ma_gt';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    use GeneratesCustomId;
    public static string $idPrefix = 'GT';

    protected $fillable = ['ma_gt', 'ma_tt', 'gia_tri'];

    public function thuocTinh()
    {
        return $this->belongsTo(ThuocTinh::class, 'ma_tt', 'ma_tt');
    }

    public function bienThes()
    {
        return $this->belongsToMany(
            BienTheSanPham::class,
            'lien_ket_bien_the_gia_tri',
            'ma_gt',
            'ma_bt'
        );
    }
}
