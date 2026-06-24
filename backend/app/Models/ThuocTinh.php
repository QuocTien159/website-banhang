<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class ThuocTinh extends Model
{
    use GeneratesCustomId;

    protected $table = 'thuoc_tinh';
    protected $primaryKey = 'ma_tt';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'TT';

    protected $fillable = ['ma_tt', 'ten_tt'];

    public function giaTriThuocTinhs()
    {
        return $this->hasMany(GiaTriThuocTinh::class, 'ma_tt', 'ma_tt');
    }
}
