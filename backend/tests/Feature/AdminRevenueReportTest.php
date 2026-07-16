<?php

namespace Tests\Feature;

use App\Models\BienTheSanPham;
use App\Models\ChiTietDonHang;
use App\Models\ChiTietTraHang;
use App\Models\DanhMuc;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\SanPham;
use App\Models\YeuCauTraHang;
use App\Support\PaymentStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

        $pending->update(['trang_thai' => 'delivered', 'ngay_giao_thanh_cong' => now()]);

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
            ->assertJsonCount(0, 'top_products')
            ->assertJsonCount(0, 'top_categories')
            ->assertJsonCount(0, 'top_customers');
    }

    public function test_revenue_uses_successful_delivery_date_instead_of_order_date(): void
    {
        $placedYesterday = now()->subDay()->startOfDay()->addHours(9);
        $deliveredToday = now()->startOfDay()->addHours(15);
        $order = $this->createOrder('completed', 275000, $placedYesterday);
        $order->update(['ngay_giao_thanh_cong' => $deliveredToday]);

        $today = now()->toDateString();
        $yesterday = now()->subDay()->toDateString();

        $this->getJson("/api/admin/reports/dashboard?from={$today}&to={$today}")
            ->assertOk()
            ->assertJsonPath('kpis.revenue.value', 275000);

        $this->getJson("/api/admin/reports/dashboard?from={$yesterday}&to={$yesterday}")
            ->assertOk()
            ->assertJsonPath('kpis.revenue.value', 0);
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

    public function test_dashboard_aggregates_categories_without_double_counting_and_accounts_for_returns(): void
    {
        $alpha = DanhMuc::create(['ten_dm' => 'Analytics Alpha', 'trang_thai' => true]);
        $beta = DanhMuc::create(['ten_dm' => 'Analytics Beta', 'trang_thai' => true]);
        $alphaFirst = $this->createVariant($alpha, 'Analytics Alpha One', 'AN-ALPHA-ONE');
        $alphaSecond = $this->createVariant($alpha, 'Analytics Alpha Two', 'AN-ALPHA-TWO');
        $betaVariant = $this->createVariant($beta, 'Analytics Beta One', 'AN-BETA-ONE');
        $otherCustomer = $this->createCustomer('Analytics Other Customer');

        $this->createOrderWithLines($this->customer, [
            ['variant' => $alphaFirst, 'quantity' => 3, 'unit_price' => 100000],
            ['variant' => $alphaSecond, 'quantity' => 2, 'unit_price' => 100000],
        ], 500000, 'delivered');

        $partiallyReturned = $this->createOrderWithLines($this->customer, [
            ['variant' => $alphaFirst, 'quantity' => 4, 'unit_price' => 100000],
        ], 400000, 'completed');
        $this->recordReturn($partiallyReturned, $alphaFirst, 1, 'received');

        $this->createOrderWithLines($otherCustomer, [
            ['variant' => $betaVariant, 'quantity' => 2, 'unit_price' => 250000],
        ], 500000, 'completed');

        $this->createOrderWithLines($otherCustomer, [
            ['variant' => $betaVariant, 'quantity' => 4, 'unit_price' => 250000],
        ], 1000000, 'cancelled');

        $this->createOrderWithLines($otherCustomer, [
            ['variant' => $betaVariant, 'quantity' => 4, 'unit_price' => 250000],
        ], 1000000, 'completed', null, 'bank_transfer_qr', PaymentStatus::PENDING_PAYMENT);

        $fullyReturned = $this->createOrderWithLines($otherCustomer, [
            ['variant' => $betaVariant, 'quantity' => 1, 'unit_price' => 200000],
        ], 200000, 'delivered');
        $this->recordReturn($fullyReturned, $betaVariant, 1, 'received');

        $response = $this->getJson('/api/admin/reports/dashboard?from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('kpis.revenue.value', 1300000)
            ->assertJsonPath('top_categories.0.category_id', $alpha->ma_dm)
            ->assertJsonPath('top_categories.0.quantity_sold', 8)
            ->assertJsonPath('top_categories.0.completed_order_count', 2)
            ->assertJsonPath('top_categories.0.net_revenue', 800000)
            ->assertJsonPath('top_categories.1.category_id', $beta->ma_dm)
            ->assertJsonPath('top_categories.1.quantity_sold', 2)
            ->assertJsonPath('top_categories.1.completed_order_count', 1)
            ->assertJsonPath('top_categories.1.net_revenue', 500000);

        $this->assertSame(3, $response->json('charts.revenue_and_completed_orders.0.completed_orders'));
    }

    public function test_dashboard_uses_quantity_as_the_category_tiebreaker(): void
    {
        $higherQuantity = DanhMuc::create(['ten_dm' => 'Analytics Quantity First', 'trang_thai' => true]);
        $lowerQuantity = DanhMuc::create(['ten_dm' => 'Analytics Quantity Second', 'trang_thai' => true]);
        $highVariant = $this->createVariant($higherQuantity, 'Analytics High Quantity', 'AN-HIGH-QUANTITY');
        $lowVariant = $this->createVariant($lowerQuantity, 'Analytics Low Quantity', 'AN-LOW-QUANTITY');

        $this->createOrderWithLines($this->customer, [
            ['variant' => $highVariant, 'quantity' => 2, 'unit_price' => 100000],
        ], 200000, 'completed');
        $this->createOrderWithLines($this->customer, [
            ['variant' => $lowVariant, 'quantity' => 1, 'unit_price' => 200000],
        ], 200000, 'completed');

        $this->getJson('/api/admin/reports/dashboard?from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('top_categories.0.category_id', $higherQuantity->ma_dm)
            ->assertJsonPath('top_categories.1.category_id', $lowerQuantity->ma_dm);
    }

    public function test_dashboard_ranks_customers_masks_contact_and_filters_dates(): void
    {
        $category = DanhMuc::create(['ten_dm' => 'Analytics Customers', 'trang_thai' => true]);
        $variant = $this->createVariant($category, 'Analytics Customer Product', 'AN-CUSTOMER-PRODUCT');
        $otherCustomer = $this->createCustomer('Analytics Ranked Customer');

        $this->createOrderWithLines($this->customer, [
            ['variant' => $variant, 'quantity' => 1, 'unit_price' => 300000],
        ], 300000, 'completed');
        $this->createOrderWithLines($this->customer, [
            ['variant' => $variant, 'quantity' => 1, 'unit_price' => 300000],
        ], 300000, 'delivered');
        $this->createOrderWithLines($otherCustomer, [
            ['variant' => $variant, 'quantity' => 2, 'unit_price' => 300000],
        ], 600000, 'completed');
        $this->createOrderWithLines($otherCustomer, [
            ['variant' => $variant, 'quantity' => 5, 'unit_price' => 300000],
        ], 1500000, 'completed', now()->subDays(5));

        $response = $this->getJson('/api/admin/reports/dashboard?from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertOk()
            ->assertJsonPath('top_customers.0.customer_id', $this->customer->ma_kh)
            ->assertJsonPath('top_customers.0.net_spent', 600000)
            ->assertJsonPath('top_customers.0.completed_order_count', 2)
            ->assertJsonPath('top_customers.0.customer_type', 'returning')
            ->assertJsonPath('top_customers.1.customer_id', $otherCustomer->ma_kh)
            ->assertJsonPath('top_customers.1.net_spent', 600000)
            ->assertJsonPath('top_customers.1.completed_order_count', 1)
            ->assertJsonPath('top_customers.1.customer_type', 'returning');

        $payload = $response->getContent();
        $this->assertStringNotContainsString($this->customer->email, $payload);
        $this->assertStringNotContainsString((string) $this->customer->dien_thoai, $payload);
    }

    public function test_dashboard_applies_the_selected_date_range_to_category_and_customer_rankings(): void
    {
        $inRangeCategory = DanhMuc::create(['ten_dm' => 'Analytics In Range', 'trang_thai' => true]);
        $outOfRangeCategory = DanhMuc::create(['ten_dm' => 'Analytics Out Of Range', 'trang_thai' => true]);
        $inRangeVariant = $this->createVariant($inRangeCategory, 'Analytics In Range Product', 'AN-IN-RANGE');
        $outOfRangeVariant = $this->createVariant($outOfRangeCategory, 'Analytics Out Range Product', 'AN-OUT-RANGE');
        $otherCustomer = $this->createCustomer('Analytics Date Customer');
        $today = now()->startOfDay();

        $this->createOrderWithLines($this->customer, [
            ['variant' => $inRangeVariant, 'quantity' => 1, 'unit_price' => 100000],
        ], 100000, 'completed', $today->copy()->addHours(10));
        $this->createOrderWithLines($otherCustomer, [
            ['variant' => $outOfRangeVariant, 'quantity' => 1, 'unit_price' => 900000],
        ], 900000, 'completed', $today->copy()->subDays(2)->addHours(10));

        $this->getJson('/api/admin/reports/dashboard?from='.$today->toDateString().'&to='.$today->toDateString())
            ->assertOk()
            ->assertJsonPath('top_categories.0.category_id', $inRangeCategory->ma_dm)
            ->assertJsonPath('top_customers.0.customer_id', $this->customer->ma_kh)
            ->assertJsonPath('kpis.revenue.value', 100000);
    }

    public function test_dashboard_query_count_is_bounded_for_multiple_orders(): void
    {
        $variant = BienTheSanPham::firstOrFail();
        for ($index = 0; $index < 12; $index++) {
            $this->createOrderWithLines($this->customer, [
                ['variant' => $variant, 'quantity' => 1, 'unit_price' => 100000],
            ], 100000, 'completed');
        }

        DB::flushQueryLog();
        DB::enableQueryLog();
        try {
            $this->getJson('/api/admin/reports/dashboard?from='.now()->toDateString().'&to='.now()->toDateString())
                ->assertOk();
            $queryCount = count(DB::getQueryLog());
        } finally {
            DB::disableQueryLog();
        }

        $this->assertLessThan(30, $queryCount, 'Dashboard analytics should not issue a query per order.');
    }

    public function test_customer_cannot_access_operational_dashboard(): void
    {
        Sanctum::actingAs($this->customer);

        $this->getJson('/api/admin/reports/dashboard?from='.now()->toDateString().'&to='.now()->toDateString())
            ->assertForbidden();
    }

    public function test_customer_list_can_use_dashboard_period_and_total_spent_sort(): void
    {
        $category = DanhMuc::create(['ten_dm' => 'Analytics Customer List', 'trang_thai' => true]);
        $variant = $this->createVariant($category, 'Analytics Customer List Product', 'AN-CUSTOMER-LIST');
        $rankedCustomer = $this->createCustomer('Analytics Customer List Winner');
        $today = now()->toDateString();

        $this->createOrderWithLines($rankedCustomer, [
            ['variant' => $variant, 'quantity' => 2, 'unit_price' => 250000],
        ], 500000, 'completed');

        $this->getJson("/api/admin/customers?sort=total_spent&from={$today}&to={$today}")
            ->assertOk()
            ->assertJsonPath('data.0.id', $rankedCustomer->ma_kh)
            ->assertJsonPath('data.0.order_count', 1)
            ->assertJsonPath('data.0.total_spent', 500000)
            ->assertJsonPath('meta.period.from', $today)
            ->assertJsonPath('meta.period.to', $today);
    }

    private function createOrder(string $status, int $total, $date = null): DonHang
    {
        $orderedAt = $date ?? now();

        return DonHang::create([
            'ma_kh' => $this->customer->ma_kh,
            'ngay_dat' => $orderedAt,
            'ngay_giao_thanh_cong' => in_array($status, ['completed', 'delivered'], true) ? $orderedAt : null,
            'tong_tien' => $total,
            'phuong_thuc_tt' => 'cod',
            'dia_chi_giao' => 'Khách Test | 0909123456 | TP HCM',
            'trang_thai' => $status,
        ]);
    }

    private function createCustomer(string $name): KhachHang
    {
        $suffix = KhachHang::count() + 100;

        return KhachHang::create([
            'ten_kh' => $name,
            'email' => "analytics-{$suffix}@example.test",
            'mat_khau' => Hash::make('customer123'),
            'dien_thoai' => '091'.str_pad((string) $suffix, 7, '0', STR_PAD_LEFT),
            'vai_tro' => false,
            'role' => 'customer',
            'trang_thai' => true,
            'ngay_tao' => now(),
        ]);
    }

    private function createVariant(DanhMuc $category, string $name, string $sku): BienTheSanPham
    {
        $product = SanPham::create([
            'ma_dm' => $category->ma_dm,
            'ten_sp' => $name,
            'mo_ta' => 'Analytics test product.',
            'gia_co_ban' => 100000,
            'trang_thai' => 'active',
            'ngay_tao' => now(),
            'ngay_cap_nhat' => now(),
        ]);

        return BienTheSanPham::create([
            'ma_sp' => $product->ma_sp,
            'sku' => $sku,
            'variant_signature' => hash('sha256', $sku),
            'gia_ban' => 100000,
            'gia_niem_yet' => 100000,
            'so_luong_ton' => 50,
            'trang_thai' => true,
            'trang_thai_ban' => 'active',
            'ngay_cap_nhat' => now(),
        ]);
    }

    private function createOrderWithLines(
        KhachHang $customer,
        array $lines,
        int $total,
        string $status,
        $date = null,
        string $paymentMethod = 'cod',
        ?string $paymentStatus = null,
    ): DonHang {
        $orderedAt = $date ?? now();
        $attributes = [
            'ma_kh' => $customer->ma_kh,
            'ngay_dat' => $orderedAt,
            'ngay_giao_thanh_cong' => in_array($status, ['completed', 'delivered'], true) ? $orderedAt : null,
            'tong_tien' => $total,
            'phuong_thuc_tt' => $paymentMethod,
            'dia_chi_giao' => 'Analytics Test | 0909123456 | Ho Chi Minh City',
            'trang_thai' => $status,
        ];
        if ($paymentStatus !== null) {
            $attributes['trang_thai_thanh_toan'] = $paymentStatus;
        }

        $order = DonHang::create($attributes);
        foreach ($lines as $line) {
            ChiTietDonHang::create([
                'ma_dh' => $order->ma_dh,
                'ma_bien_the' => $line['variant']->ma_bt,
                'so_luong' => $line['quantity'],
                'don_gia' => $line['unit_price'],
            ]);
        }

        return $order;
    }

    private function recordReturn(
        DonHang $order,
        BienTheSanPham $variant,
        int $quantity,
        string $status,
        ?string $refundStatus = null,
    ): void {
        $attributes = [
            'ma_dh' => $order->ma_dh,
            'ma_kh' => $order->ma_kh,
            'ly_do' => 'Analytics return.',
            'trang_thai' => $status,
            'ngay_yeu_cau' => now(),
        ];
        if ($refundStatus !== null) {
            $attributes['trang_thai_hoan_tien'] = $refundStatus;
        }
        $return = YeuCauTraHang::create($attributes);

        ChiTietTraHang::create([
            'ma_yeu_cau' => $return->ma_yeu_cau,
            'ma_bien_the' => $variant->ma_bt,
            'ma_sp' => $variant->ma_sp,
            'so_luong' => $quantity,
        ]);
    }
}
