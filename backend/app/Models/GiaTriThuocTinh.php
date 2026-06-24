<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class GiaTriThuocTinh extends Model
{
    use GeneratesCustomId;

    protected $table = 'gia_tri_thuoc_tinh';
    protected $primaryKey = 'ma_gt';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

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
