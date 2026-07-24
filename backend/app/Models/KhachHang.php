<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use App\Traits\GeneratesCustomId;
use App\Support\UserRole;
use App\Notifications\CustomerResetPasswordNotification;

class KhachHang extends Authenticatable
{
    use HasApiTokens, GeneratesCustomId, Notifiable;

    protected $table = 'khach_hang';
    protected $primaryKey = 'ma_kh';
    public $incrementing = false;
    protected $keyType = 'string';
    public $timestamps = false;

    public static string $idPrefix = 'KH';

    protected $fillable = [
        'ma_kh', 'ten_kh', 'email', 'google_id', 'google_avatar', 'google_linked_at', 'mat_khau',
        'dien_thoai', 'vai_tro', 'role', 'trang_thai', 'ngay_tao',
    ];

    protected $hidden = ['mat_khau', 'remember_token'];

    protected $casts = [
        'vai_tro' => 'boolean',
        'trang_thai' => 'boolean',
        'ngay_tao' => 'datetime',
        'google_linked_at' => 'datetime',
    ];

    public function getAuthPassword(): string
    {
        return $this->mat_khau;
    }

    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new CustomerResetPasswordNotification($token));
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
        return $this->role === UserRole::ADMIN || $this->vai_tro === true;
    }

    public function isStaff(): bool
    {
        return $this->role === UserRole::STAFF;
    }

    public function hasRole(string ...$roles): bool
    {
        return in_array($this->roleName(), $roles, true);
    }

    public function roleName(): string
    {
        if ($this->vai_tro === true) {
            return UserRole::ADMIN;
        }

        if ($this->role && $this->role !== UserRole::ADMIN) {
            return $this->role;
        }

        return UserRole::CUSTOMER;
    }
}
