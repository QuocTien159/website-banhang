<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\KhachHang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminRevenueReportTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private KhachHang $admin;
    private KhachHang $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = KhachHang::where('vai_tro', true)->firstOrFail();
        $this->customer = KhachHang::where('vai_tro', false)->firstOrFail();
        Sanctum::actingAs($this->admin);
    }

    public function test_dashboard_revenue_counts_only_delivered_orders(): void
    {
        $this->createOrder('pending', 100000);
        $this->createOrder('confirmed', 200000);
        $this->createOrder('shipping', 300000);
        $this->createOrder('delivered', 400000);
        $this->createOrder('cancelled', 500000);

        $this->getJson('/api/admin/reports/summary')
            ->assertOk()
            ->assertJsonPath('stats.total_revenue', 400000)
            ->assertJsonPath('stats.total_orders', 5)
            ->assertJsonPath('stats.status_pending', 1)
            ->assertJsonPath('stats.status_confirmed', 1)
            ->assertJsonPath('stats.status_shipping', 1)
            ->assertJsonPath('stats.status_delivered', 1)
            ->assertJsonPath('stats.status_cancelled', 1);
    }

    public function test_monthly_revenue_counts_only_delivered_orders_and_updates_when_status_changes(): void
    {
        $pending = $this->createOrder('pending', 150000);
        $this->createOrder('delivered', 250000);
        $this->createOrder('cancelled', 350000);

        $monthIndex = now()->month - 1;

        $response = $this->getJson('/api/admin/reports/revenue?year='.now()->year)
            ->assertOk();
        $this->assertEquals(250000, $response->json("monthly.{$monthIndex}.revenue"));
        $this->assertSame(1, $response->json("monthly.{$monthIndex}.orders"));
        $this->assertEquals(250000, $response->json('total_revenue'));

        $pending->update(['trang_thai' => 'delivered']);

        $response = $this->getJson('/api/admin/reports/revenue?year='.now()->year)
            ->assertOk();
        $this->assertEquals(400000, $response->json("monthly.{$monthIndex}.revenue"));
        $this->assertSame(2, $response->json("monthly.{$monthIndex}.orders"));
        $this->assertEquals(400000, $response->json('total_revenue'));

        $pending->update(['trang_thai' => 'cancelled']);

        $response = $this->getJson('/api/admin/reports/revenue?year='.now()->year)
            ->assertOk();
        $this->assertEquals(250000, $response->json("monthly.{$monthIndex}.revenue"));
        $this->assertSame(1, $response->json("monthly.{$monthIndex}.orders"));
        $this->assertEquals(250000, $response->json('total_revenue'));
    }

    public function test_operational_dashboard_filters_by_period_compares_previous_period_and_excludes_cancelled_revenue(): void
    {
        $today = now()->startOfDay();
        $this->createOrder('delivered', 400000, $today->copy()->addHours(10));
        $this->createOrder('cancelled', 900000, $today->copy()->addHours(11));
        $this->createOrder('pending', 200000, $today->copy()->addHours(12));
        $this->createOrder('delivered', 100000, $today->copy()->subDay()->addHours(10));

        $this->getJson('/api/admin/reports/dashboard?from='.$today->toDateString().'&to='.$today->toDateString())
            ->assertOk()
            ->assertJsonPath('kpis.revenue.value', 400000)
            ->assertJsonPath('kpis.revenue.previous_value', 100000)
            ->assertJsonPath('kpis.revenue.change_percent', 300)
            ->assertJsonPath('kpis.orders.value', 3)
            ->assertJsonPath('actions.0.count', 1)
            ->assertJsonPath('period.from', $today->toDateString())
            ->assertJsonPath('period.previous_to', $today->copy()->subDay()->toDateString());
    }

    public function test_operational_dashboard_returns_empty_data_for_a_valid_period_without_orders(): void
    {
        $date = now()->addYears(2)->toDateString();

        $this->getJson('/api/admin/reports/dashboard?from='.$date.'&to='.$date)
            ->assertOk()
            ->assertJsonPath('kpis.revenue.value', 0)
            ->assertJsonPath('kpis.orders.value', 0)
            ->assertJsonCount(0, 'recent_orders')
            ->assertJsonCount(0, 'top_products');
    }

    public function test_staff_cannot_access_operational_dashboard(): void
    {
        $staff = KhachHang::create([
            'ten_kh' => 'Nhân viên Dashboard',
            'email' => 'staff-dashboard@example.com',
            'mat_khau' => Hash::make('staff123'),
            'dien_thoai' => '0912222222',
            'vai_tro' => false,
            'role' => 'staff',
            'trang_thai' => true,
            'ngay_tao' => now(),
        ]);
        Sanctum::actingAs($staff);

        $this->getJson('/api/admin/reports/dashboard?from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertForbidden();
    }

    private function createOrder(string $status, int $total, $date = null): DonHang
    {
        return DonHang::create([
            'ma_kh' => $this->customer->ma_kh,
            'ngay_dat' => $date ?? now(),
            'tong_tien' => $total,
            'phuong_thuc_tt' => 'cod',
            'dia_chi_giao' => 'Khách Test | 0909123456 | TP HCM',
            'trang_thai' => $status,
        ]);
    }
}
