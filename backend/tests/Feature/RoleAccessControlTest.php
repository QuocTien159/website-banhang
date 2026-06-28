<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RoleAccessControlTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private function createStaff(): KhachHang
    {
        return KhachHang::create([
            'ten_kh' => 'Nhân viên Test',
            'email' => 'staff-test@example.com',
            'mat_khau' => Hash::make('staff123'),
            'dien_thoai' => '0911111111',
            'vai_tro' => false,
            'role' => 'staff',
            'trang_thai' => true,
            'ngay_tao' => now(),
        ]);
    }

    public function test_staff_can_access_operational_admin_apis_but_not_sensitive_apis(): void
    {
        Sanctum::actingAs($this->createStaff());

        $this->getJson('/api/admin/products')->assertOk();
        $this->getJson('/api/admin/orders')->assertOk();
        $this->getJson('/api/admin/inventory/alerts')->assertOk();
        $this->getJson('/api/admin/reviews')->assertOk();

        $this->getJson('/api/admin/customers')->assertForbidden();
        $this->getJson('/api/admin/staff')->assertForbidden();
        $this->getJson('/api/admin/reports/summary')->assertForbidden();
        $this->getJson('/api/admin/payment-shipping-settings')->assertForbidden();
        $this->getJson('/api/admin/promotions')->assertForbidden();
        $this->getJson('/api/admin/announcements')->assertForbidden();
    }

    public function test_customer_cannot_access_admin_apis(): void
    {
        Sanctum::actingAs(KhachHang::where('email', 'user@example.com')->firstOrFail());

        $this->getJson('/api/admin/products')->assertForbidden();
    }

    public function test_admin_can_manage_staff_accounts(): void
    {
        $admin = KhachHang::where('vai_tro', true)->firstOrFail();
        Sanctum::actingAs($admin);

        $created = $this->postJson('/api/admin/staff', [
            'name' => 'Nhân viên mới',
            'email' => 'new-staff@example.com',
            'phone' => '0922222222',
            'password' => 'staff123',
            'role' => 'staff',
            'active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('role', 'staff')
            ->json();

        $this->putJson("/api/admin/staff/{$created['id']}", [
            'name' => 'Nhân viên đã sửa',
            'email' => 'new-staff@example.com',
            'phone' => '0922222222',
            'role' => 'staff',
            'active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('name', 'Nhân viên đã sửa');

        $this->putJson("/api/admin/staff/{$created['id']}/status")
            ->assertOk()
            ->assertJsonPath('active', false);
    }
}
