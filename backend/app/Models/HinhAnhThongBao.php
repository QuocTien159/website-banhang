<?php

namespace App\Models;

use App\Traits\GeneratesCustomId;
use Illuminate\Database\Eloquent\Model;

class HinhAnhThongBao extends Model
{
    use GeneratesCustomId;

    protected $table = 'hinh_anh_thong_bao';
    protected $primaryKey = 'ma_anh_tb';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    public static string $idPrefix = 'AT';

    protected $fillable = ['ma_anh_tb', 'ma_tb', 'url', 'duong_dan', 'thu_tu', 'ngay_tao'];
    protected $casts = ['thu_tu' => 'integer', 'ngay_tao' => 'datetime'];

    public function thongBao()
    {
        return $this->belongsTo(ThongBao::class, 'ma_tb', 'ma_tb');
    }
}
