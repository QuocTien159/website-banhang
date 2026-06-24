<?php

namespace App\Models;

use App\Traits\GeneratesCustomId;
use Illuminate\Database\Eloquent\Model;

class HinhAnhTraHang extends Model
{
    use GeneratesCustomId;

    protected $table = 'hinh_anh_tra_hang';
    protected $primaryKey = 'ma_anh_th';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'RT';

    protected $fillable = ['ma_anh_th', 'ma_yeu_cau', 'ma_bien_the', 'url_anh', 'ngay_tao'];

    protected $casts = ['ngay_tao' => 'datetime'];

    public function yeuCauTraHang()
    {
        return $this->belongsTo(YeuCauTraHang::class, 'ma_yeu_cau', 'ma_yeu_cau');
    }
}
