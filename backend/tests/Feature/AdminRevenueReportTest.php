<?php

namespace Tests\Feature;

use App\Models\DonHang;
use App\Models\KhachHang;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    private function createOrder(string $status, int $total): DonHang
    {
        return DonHang::create([
            'ma_kh' => $this->customer->ma_kh,
            'ngay_dat' => now(),
            'tong_tien' => $total,
            'phuong_thuc_tt' => 'cod',
            'dia_chi_giao' => 'Khách Test | 0909123456 | TP HCM',
            'trang_thai' => $status,
        ]);
    }
}
