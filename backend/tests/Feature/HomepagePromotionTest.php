<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use App\Models\MaKhuyenMai;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class HomepagePromotionTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_admin_can_configure_valid_homepage_promotion_and_public_hides_it_when_voucher_is_disabled(): void
    {
        $admin = KhachHang::where('vai_tro', true)->firstOrFail();
        $voucher = MaKhuyenMai::create([
            'code' => 'HOME10', 'loai_giam' => 'percent', 'gia_tri' => 10, 'don_toi_thieu' => 100000,
            'bat_dau' => now()->subHour(), 'ket_thuc' => now()->addDay(), 'trang_thai' => true,
        ]);
        Sanctum::actingAs($admin);

        $this->putJson('/api/admin/homepage-promotion', [
            'enabled' => true, 'voucher_id' => $voucher->ma_km, 'label' => 'Ưu đãi', 'title' => 'Giảm giá',
            'description' => 'Áp dụng cho đơn đủ điều kiện.', 'cta_text' => 'Mua ngay', 'cta_url' => '/products',
        ])->assertOk()->assertJsonPath('voucher.code', 'HOME10');

        $this->getJson('/api/homepage-promotion')->assertOk()->assertJsonPath('voucher.code', 'HOME10');
        $voucher->update(['trang_thai' => false]);
        $this->get('/api/homepage-promotion')->assertNoContent();
    }

    public function test_staff_cannot_manage_homepage_promotion(): void
    {
        $staff = KhachHang::create([
            'ten_kh' => 'Nhân viên ưu đãi', 'email' => 'staff-home-promo@example.com', 'mat_khau' => bcrypt('staff123'),
            'dien_thoai' => '0915555555', 'vai_tro' => false, 'role' => 'staff', 'trang_thai' => true, 'ngay_tao' => now(),
        ]);
        Sanctum::actingAs($staff);
        $this->getJson('/api/admin/homepage-promotion')->assertForbidden();
        $this->putJson('/api/admin/homepage-promotion', ['enabled' => false])->assertForbidden();
    }
}
