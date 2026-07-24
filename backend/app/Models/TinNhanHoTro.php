<?php

namespace App\Models;

use App\Traits\GeneratesCustomId;
use Illuminate\Database\Eloquent\Model;

class TinNhanHoTro extends Model
{
    use GeneratesCustomId;

    protected $table = 'tin_nhan_ho_tro';
    protected $primaryKey = 'ma_tn';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    public static string $idPrefix = 'TN';

    protected $fillable = ['ma_tn', 'ma_ct', 'ma_nguoi_gui', 'vai_tro_nguoi_gui', 'noi_dung', 'ngay_gui'];

    protected $casts = ['ngay_gui' => 'datetime'];

    public function conversation()
    {
        return $this->belongsTo(CuocTroChuyenHoTro::class, 'ma_ct', 'ma_ct');
    }

    public function sender()
    {
        return $this->belongsTo(KhachHang::class, 'ma_nguoi_gui', 'ma_kh');
    }
}
