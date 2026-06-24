<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Traits\GeneratesCustomId;

class DanhMuc extends Model
{
    use GeneratesCustomId;

    protected $table = 'danh_muc';
    protected $primaryKey = 'ma_dm';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'DM';

    protected $fillable = ['ma_dm', 'ten_dm', 'trang_thai'];

    protected $casts = ['trang_thai' => 'boolean'];

    public function sanPhams()
    {
        return $this->hasMany(SanPham::class, 'ma_dm', 'ma_dm');
    }
}
