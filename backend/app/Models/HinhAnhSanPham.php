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

    protected $fillable = ['ma_anh', 'ma_sp', 'ma_bt', 'url', 'anh_chinh'];

    protected $casts = ['anh_chinh' => 'boolean'];

    public function sanPham()
    {
        return $this->belongsTo(SanPham::class, 'ma_sp', 'ma_sp');
    }

    public function bienThe()
    {
        return $this->belongsTo(BienTheSanPham::class, 'ma_bt', 'ma_bt');
    }
}
