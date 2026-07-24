<?php

namespace App\Models;

use App\Traits\GeneratesCustomId;
use Illuminate\Database\Eloquent\Model;

class CuocTroChuyenHoTro extends Model
{
    use GeneratesCustomId;

    protected $table = 'cuoc_tro_chuyen_ho_tro';
    protected $primaryKey = 'ma_ct';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;
    public static string $idPrefix = 'CT';

    protected $fillable = [
        'ma_ct', 'ma_kh', 'ma_nv_phu_trach', 'trang_thai',
        'khach_hang_da_doc_luc', 'nhan_vien_da_doc_luc',
        'tin_nhan_cuoi_luc', 'ngay_tao', 'ngay_cap_nhat',
    ];

    protected $casts = [
        'khach_hang_da_doc_luc' => 'datetime',
        'nhan_vien_da_doc_luc' => 'datetime',
        'tin_nhan_cuoi_luc' => 'datetime',
        'ngay_tao' => 'datetime',
        'ngay_cap_nhat' => 'datetime',
    ];

    public function customer()
    {
        return $this->belongsTo(KhachHang::class, 'ma_kh', 'ma_kh');
    }

    public function assignee()
    {
        return $this->belongsTo(KhachHang::class, 'ma_nv_phu_trach', 'ma_kh');
    }

    public function messages()
    {
        return $this->hasMany(TinNhanHoTro::class, 'ma_ct', 'ma_ct')->orderBy('ngay_gui');
    }
}
