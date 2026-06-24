<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class HinhAnhDanhGia extends Model
{
    use GeneratesCustomId;

    protected $table = 'hinh_anh_danh_gia';
    protected $primaryKey = 'ma_anh_dg';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'AG';

    protected $fillable = ['ma_anh_dg', 'ma_danh_gia', 'url_anh', 'ngay_tao'];

    protected $casts = ['ngay_tao' => 'datetime'];

    public function danhGia()
    {
        return $this->belongsTo(DanhGia::class, 'ma_danh_gia', 'ma_danh_gia');
    }
}
