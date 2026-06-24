<?php

namespace App\Models;

use App\Traits\GeneratesCustomId;
use Illuminate\Database\Eloquent\Model;

class ThongBao extends Model
{
    use GeneratesCustomId;
    protected $table = 'thong_bao';
    protected $primaryKey = 'ma_tb';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    public static string $idPrefix = 'TB';
    protected $fillable = ['ma_tb', 'tieu_de', 'noi_dung', 'loai', 'trang_thai', 'ngay_xuat_ban', 'ngay_tao', 'ngay_cap_nhat'];
    protected $casts = ['ngay_xuat_ban' => 'datetime', 'ngay_tao' => 'datetime', 'ngay_cap_nhat' => 'datetime'];

    public function hinhAnhs()
    {
        return $this->hasMany(HinhAnhThongBao::class, 'ma_tb', 'ma_tb')->orderBy('thu_tu');
    }
}
