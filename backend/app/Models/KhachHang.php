<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\GeneratesCustomId;

class KhachHang extends Authenticatable
{
    use HasApiTokens, GeneratesCustomId;

    protected $table = 'khach_hang';
    protected $primaryKey = 'ma_kh';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'KH';

    protected $fillable = [
        'ma_kh', 'ten_kh', 'email', 'mat_khau',
        'dien_thoai', 'vai_tro', 'role', 'trang_thai', 'ngay_tao',
    ];

    protected $hidden = ['mat_khau', 'remember_token'];

    protected $casts = [
        'vai_tro' => 'boolean',
        'trang_thai' => 'boolean',
        'ngay_tao' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return $this->mat_khau;
    }

    // Relationships
    public function gioHang()
    {
        return $this->hasOne(GioHang::class, 'ma_kh', 'ma_kh');
    }

    public function donHangs()
    {
        return $this->hasMany(DonHang::class, 'ma_kh', 'ma_kh');
    }

    public function danhGias()
    {
        return $this->hasMany(DanhGia::class, 'ma_kh', 'ma_kh');
    }

    public function sanPhamYeuThichs()
    {
        return $this->belongsToMany(
            SanPham::class,
            'danh_sach_yeu_thich',
            'ma_kh',
            'ma_sp'
        );
    }

    // Helper
    public function isAdmin(): bool
    {
        return $this->role === 'admin' || $this->vai_tro === true;
    }

    public function isStaff(): bool
    {
        return $this->role === 'staff';
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->roleName(), $roles, true);
    }

    public function roleName(): string
    {
        if ($this->vai_tro === true) {
            return 'admin';
        }

        if ($this->role && $this->role !== 'admin') {
            return $this->role;
        }

        return 'customer';
    }
}
