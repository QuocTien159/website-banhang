<?php

namespace Tests\Feature;

use App\Models\BienTheSanPham;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\LichSuBienDongKho;
use App\Models\PaymentShippingSetting;
use App\Models\SuKienVanChuyen;
use App\Models\VanDonVanChuyen;
use App\Support\OrderStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class GhnFulfillmentTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();

        PaymentShippingSetting::current()->update([
            'shipping_provider' => 'ghn',
            'ghn_enabled' => true,
            'ghn_shop_id' => '201036',
            'pickup_name' => 'TienProSport Kho',
            'pickup_phone' => '0909000000',
            'pickup_province_id' => 202,
            'pickup_district_id' => 1442,
            'pickup_ward_code' => '20101',
            'pickup_address' => '1 Test Street',
            'default_weight_gram' => 500,
            'default_length_cm' => 25,
            'default_width_cm' => 20,
            'default_height_cm' => 10,
        ]);
        config(['services.ghn.webhook_secret' => 'test-webhook-secret']);
        $this->fakeGhn();
    }

    public function test_fulfillment_schema_contains_separate_shipment_and_event_tables(): void
    {
        $this->assertTrue(Schema::hasColumns('van_don_van_chuyen', [
            'ma_dh', 'ma_van_don_ghn', 'trang_thai_ghn_goc', 'trang_thai_van_chuyen',
            'du_lieu_gui', 'du_lieu_phan_hoi', 'loi_dong_bo_cuoi',
        ]));
        $this->assertTrue(Schema::hasColumns('su_kien_van_chuyen', ['ma_van_chuyen', 'nguon', 'ma_bam_payload']));
    }

    public function test_ghn_shipment_is_created_only_after_internal_preparation_and_does_not_change_stock_again(): void
    {
        $admin = KhachHang::where('vai_tro', true)->firstOrFail();
        $variant = BienTheSanPham::where('so_luong_ton', '>', 5)->firstOrFail();
        $order = $this->createOrder($variant, OrderStatus::PENDING);
        $stockBefore = (int) $variant->so_luong_ton;
        $historyBefore = LichSuBienDongKho::count();

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/orders/{$order->ma_dh}/shipping/handoff")
            ->assertUnprocessable();
        Http::assertNothingSent();

        $this->putJson("/api/admin/orders/{$order->ma_dh}/status", ['status' => 'confirmed'])->assertOk();
        $this->putJson("/api/admin/orders/{$order->ma_dh}/status", ['status' => 'preparing'])->assertOk();
        $this->postJson("/api/admin/orders/{$order->ma_dh}/shipping/handoff")
            ->assertOk()
            ->assertJsonPath('shipping.tracking_code', 'GHN-TEST-1001');

        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/v2/shipping-order/create'));
        $shipment = VanDonVanChuyen::where('ma_dh', $order->ma_dh)->firstOrFail();
        $this->assertSame('GHN-TEST-1001', $shipment->ma_van_don_ghn);
        $this->assertSame('waiting_pickup', $shipment->trang_thai_van_chuyen);
        $this->assertSame(OrderStatus::HANDED_TO_CARRIER, $order->fresh()->trang_thai);
        $this->assertSame($stockBefore, (int) $variant->fresh()->so_luong_ton);
        $this->assertSame($historyBefore, LichSuBienDongKho::count());

        $this->postJson("/api/admin/orders/{$order->ma_dh}/shipping/handoff")->assertUnprocessable();
        $this->assertSame(1, VanDonVanChuyen::where('ma_dh', $order->ma_dh)->count());
    }

    public function test_missing_ghn_recipient_data_does_not_call_provider(): void
    {
        $admin = KhachHang::where('vai_tro', true)->firstOrFail();
        $order = $this->createOrder(BienTheSanPham::where('so_luong_ton', '>', 5)->firstOrFail(), OrderStatus::PREPARING, [
            'ma_phuong_xa' => null,
        ]);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/orders/{$order->ma_dh}/shipping/handoff")
            ->assertUnprocessable()
            ->assertJsonValidationErrors('ward_code');
        Http::assertNothingSent();
        $this->assertDatabaseMissing('van_don_van_chuyen', ['ma_dh' => $order->ma_dh]);
    }

    public function test_signed_ghn_webhook_updates_shipping_once_and_ignores_duplicate_or_stale_events(): void
    {
        $admin = KhachHang::where('vai_tro', true)->firstOrFail();
        $variant = BienTheSanPham::where('so_luong_ton', '>', 5)->firstOrFail();
        $order = $this->createOrder($variant, OrderStatus::PREPARING);
        $stockBefore = (int) $variant->so_luong_ton;

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/orders/{$order->ma_dh}/shipping/handoff")->assertOk();
        $shipment = VanDonVanChuyen::where('ma_dh', $order->ma_dh)->firstOrFail();

        $payload = [
            'order_code' => $shipment->ma_van_don_ghn,
            'client_order_code' => $order->ma_dh,
            'status' => 'delivered',
            'updated_date' => '2026-07-16 10:30:00',
            'total_fee' => 30000,
        ];
        $this->postJson('/api/webhooks/ghn/order-status?token=wrong-secret', $payload)->assertForbidden();
        $this->postJson('/api/webhooks/ghn/order-status?token=test-webhook-secret', $payload)
            ->assertOk()
            ->assertJsonPath('accepted', true);

        $this->assertSame('delivered', $shipment->fresh()->trang_thai_van_chuyen);
        $this->assertSame(OrderStatus::COMPLETED, $order->fresh()->trang_thai);
        $this->assertSame('2026-07-16 10:30:00', $order->fresh()->ngay_giao_thanh_cong?->format('Y-m-d H:i:s'));
        $this->assertSame('paid', $order->fresh()->trang_thai_thanh_toan);
        $eventCount = SuKienVanChuyen::where('ma_van_chuyen', $shipment->ma_van_chuyen)->count();
        $this->assertSame($stockBefore, (int) $variant->fresh()->so_luong_ton);

        $this->postJson('/api/webhooks/ghn/order-status?token=test-webhook-secret', $payload)->assertOk();
        $this->assertSame($eventCount, SuKienVanChuyen::where('ma_van_chuyen', $shipment->ma_van_chuyen)->count());

        $stalePayload = array_merge($payload, [
            'status' => 'delivering',
            'updated_date' => '2026-07-16 09:30:00',
        ]);
        $this->postJson('/api/webhooks/ghn/order-status?token=test-webhook-secret', $stalePayload)->assertOk();
        $this->assertSame('delivered', $shipment->fresh()->trang_thai_van_chuyen);
        $this->assertTrue((bool) SuKienVanChuyen::where('ma_van_chuyen', $shipment->ma_van_chuyen)
            ->where('trang_thai_ghn_goc', 'delivering')
            ->firstOrFail()
            ->da_bo_qua);
    }

    public function test_signed_webhook_cannot_replace_a_known_ghn_code_with_a_mismatched_code(): void
    {
        $admin = KhachHang::where('vai_tro', true)->firstOrFail();
        $order = $this->createOrder(BienTheSanPham::where('so_luong_ton', '>', 5)->firstOrFail(), OrderStatus::PREPARING);

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/orders/{$order->ma_dh}/shipping/handoff")->assertOk();
        $shipment = VanDonVanChuyen::where('ma_dh', $order->ma_dh)->firstOrFail();

        $this->postJson('/api/webhooks/ghn/order-status?token=test-webhook-secret', [
            'order_code' => 'GHN-OTHER-ORDER',
            'client_order_code' => $order->ma_dh,
            'status' => 'delivering',
            'updated_date' => '2026-07-16 11:00:00',
        ])->assertUnprocessable()->assertJsonValidationErrors('shipping');

        $this->assertSame('GHN-TEST-1001', $shipment->fresh()->ma_van_don_ghn);
        $this->assertSame('waiting_pickup', $shipment->fresh()->trang_thai_van_chuyen);
    }

    public function test_provider_error_keeps_the_order_and_retry_reuses_the_same_shipment_record(): void
    {
        $admin = KhachHang::where('vai_tro', true)->firstOrFail();
        $variant = BienTheSanPham::where('so_luong_ton', '>', 5)->firstOrFail();
        $order = $this->createOrder($variant, OrderStatus::PREPARING);
        $stockBefore = (int) $variant->so_luong_ton;

        // Replace the setup fake so the existing successful create stub cannot
        // mask this explicit provider failure.
        Http::swap(new HttpFactory);
        Http::fake(function () {
            return Http::response([
                'code' => 500,
                'message' => 'Sandbox unavailable',
            ], 500);
        });

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/orders/{$order->ma_dh}/shipping/handoff")->assertStatus(502);

        $shipment = VanDonVanChuyen::where('ma_dh', $order->ma_dh)->firstOrFail();
        $this->assertSame('that_bai', $shipment->trang_thai_tao);
        $this->assertNull($shipment->ma_van_don_ghn);
        $this->assertSame(OrderStatus::READY_TO_SHIP, $order->fresh()->trang_thai);
        $this->assertSame($stockBefore, (int) $variant->fresh()->so_luong_ton);

        Http::swap(new HttpFactory);
        $this->fakeGhn();
        $this->postJson("/api/admin/orders/{$order->ma_dh}/shipping/retry")->assertOk();

        $this->assertSame(1, VanDonVanChuyen::where('ma_dh', $order->ma_dh)->count());
        $this->assertSame('GHN-TEST-1001', $shipment->fresh()->ma_van_don_ghn);
    }

    public function test_returned_callback_does_not_restore_inventory_without_a_receiving_workflow(): void
    {
        $admin = KhachHang::where('vai_tro', true)->firstOrFail();
        $variant = BienTheSanPham::where('so_luong_ton', '>', 5)->firstOrFail();
        $order = $this->createOrder($variant, OrderStatus::PREPARING);
        $stockBefore = (int) $variant->so_luong_ton;
        $movementHistoryBefore = LichSuBienDongKho::count();

        Sanctum::actingAs($admin);
        $this->postJson("/api/admin/orders/{$order->ma_dh}/shipping/handoff")->assertOk();
        $shipment = VanDonVanChuyen::where('ma_dh', $order->ma_dh)->firstOrFail();

        $this->postJson('/api/webhooks/ghn/order-status?token=test-webhook-secret', [
            'order_code' => $shipment->ma_van_don_ghn,
            'client_order_code' => $order->ma_dh,
            'status' => 'returned',
            'updated_date' => '2026-07-16 12:00:00',
        ])->assertOk();

        $this->assertSame(OrderStatus::RETURNED, $order->fresh()->trang_thai);
        $this->assertSame($stockBefore, (int) $variant->fresh()->so_luong_ton);
        $this->assertSame($movementHistoryBefore, LichSuBienDongKho::count());
    }

    public function test_manual_shipping_status_is_rejected_and_customers_cannot_operate_ghn(): void
    {
        $staff = KhachHang::create([
            'ten_kh' => 'Nhân viên GHN',
            'email' => 'ghn-staff@example.com',
            'mat_khau' => Hash::make('secret123'),
            'dien_thoai' => '0911111111',
            'vai_tro' => false,
            'role' => 'staff',
            'trang_thai' => true,
            'ngay_tao' => now(),
        ]);
        $order = $this->createOrder(BienTheSanPham::where('so_luong_ton', '>', 5)->firstOrFail(), OrderStatus::PREPARING);

        Sanctum::actingAs($staff);
        $this->putJson("/api/admin/orders/{$order->ma_dh}/status", ['status' => 'shipping'])->assertUnprocessable();

        Sanctum::actingAs(KhachHang::where('email', 'user@example.com')->firstOrFail());
        $this->postJson("/api/admin/orders/{$order->ma_dh}/shipping/handoff")->assertForbidden();
        $this->postJson("/api/admin/orders/{$order->ma_dh}/shipping/sync")->assertForbidden();
    }

    private function createOrder(BienTheSanPham $variant, string $status, array $overrides = []): DonHang
    {
        $customer = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $payload = array_merge([
            'ma_kh' => $customer->ma_kh,
            'ngay_dat' => now(),
            'tam_tinh' => 100000,
            'phi_van_chuyen' => 22000,
            'tong_tien' => 122000,
            'phuong_thuc_tt' => 'cod',
            'trang_thai_thanh_toan' => 'cod_pending',
            'shipping_provider' => 'ghn',
            'shipping_service_type_id' => '2',
            'dia_chi_giao' => 'Khách GHN | 0909123456 | 123 Nguyễn Huệ, Bến Nghé, Quận 1, Hồ Chí Minh',
            'ma_tinh_thanh' => '202',
            'ma_quan_huyen' => '1442',
            'ma_phuong_xa' => '20101',
            'tinh_thanh' => 'Ho Chi Minh',
            'quan_huyen' => 'Quan 1',
            'phuong_xa' => 'Ben Nghe',
            'dia_chi_chi_tiet' => '123 Nguyễn Huệ',
            'trang_thai' => $status,
        ], $overrides);
        $order = DonHang::create($payload);
        ChiTietDonHang::create([
            'ma_dh' => $order->ma_dh,
            'ma_bien_the' => $variant->ma_bt,
            'so_luong' => 1,
            'don_gia' => 100000,
        ]);

        return $order;
    }

    private function fakeGhn(): void
    {
        Http::fake(array_merge($this->ghnFakes(), [
            'https://ghn.test/shiip/public-api/v2/shipping-order/create' => Http::response([
                'code' => 200,
                'data' => ['order_code' => 'GHN-TEST-1001', 'total_fee' => 30000],
            ]),
            'https://ghn.test/shiip/public-api/v2/shipping-order/leadtime' => Http::response([
                'code' => 200,
                'data' => ['leadtime' => now()->addDays(2)->timestamp],
            ]),
            'https://ghn.test/shiip/public-api/v2/shipping-order/detail' => Http::response([
                'code' => 200,
                'data' => ['order_code' => 'GHN-TEST-1001', 'status' => 'delivering'],
            ]),
            'https://ghn.test/shiip/public-api/v2/switch-status/cancel' => Http::response([
                'code' => 200,
                'data' => ['order_code' => 'GHN-TEST-1001'],
            ]),
        ]));
    }
}
