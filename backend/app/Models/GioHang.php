<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

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
