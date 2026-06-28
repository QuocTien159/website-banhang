<?php

namespace Tests\Feature;

use App\Models\BienTheSanPham;
use App\Models\DonHang;
use App\Models\KhachHang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManualQrPaymentShippingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_shipping_fee_is_calculated_by_shipping_zone(): void
    {
        $customer = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $variant = BienTheSanPham::where('so_luong_ton', '>', 5)->orderBy('gia_ban')->firstOrFail();

        Sanctum::actingAs($customer);
        $this->postJson('/api/cart/items', ['variant_id' => $variant->ma_bt, 'quantity' => 1])->assertOk();

        $this->postJson('/api/shipping/calculate', [
            'province_type' => 'hcm', 'district_code' => '760', 'ward_code' => '26734', 'address_detail' => '123 Nguyễn Huệ',
        ])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('shipping_zone', 'inner_city')
            ->assertJsonPath('shipping_fee', 0);

        $this->postJson('/api/shipping/calculate', [
            'province_type' => 'hcm', 'district_code' => '783', 'ward_code' => '27565', 'address_detail' => '123 Test',
        ])
            ->assertOk()
            ->assertJsonPath('shipping_zone', 'suburban')
            ->assertJsonPath('shipping_fee', 30000);

        $this->postJson('/api/shipping/calculate', [
            'province_type' => 'other', 'address_detail' => '123 Test',
        ])
            ->assertOk()
            ->assertJsonPath('shipping_zone', 'other_province')
            ->assertJsonPath('shipping_fee', 50000);

        $this->postJson('/api/shipping/calculate', [
            'province_type' => 'hcm', 'district_code' => '760', 'ward_code' => '26734',
        ])
            ->assertOk()
            ->assertJsonPath('valid', false)
            ->assertJsonPath('shipping_fee', null);
    }

    public function test_customer_can_place_qr_order_mark_paid_and_admin_confirms_manually(): void
    {
        $customer = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $admin = KhachHang::where('vai_tro', true)->firstOrFail();
        $variant = BienTheSanPham::where('so_luong_ton', '>', 2)->firstOrFail();

        Sanctum::actingAs($customer);
        $this->postJson('/api/cart/items', ['variant_id' => $variant->ma_bt, 'quantity' => 1])->assertOk();

        $order = $this->postJson('/api/orders', [
            'ten_nguoi_nhan' => 'Khách QR',
            'so_dien_thoai' => '0909123456',
            'province_type' => 'hcm',
            'district_code' => '760',
            'ward_code' => '26734',
            'address_detail' => '123 Nguyễn Huệ',
            'phuong_thuc_tt' => 'bank_transfer_qr',
        ])
            ->assertCreated()
            ->assertJsonPath('order.payment_method', 'bank_transfer_qr')
            ->assertJsonPath('order.payment_status', 'pending_payment')
            ->assertJsonPath('order.shipping', 0)
            ->assertJsonPath('order.shipping_info.province_type', 'hcm')
            ->json('order');

        $this->assertStringContainsString($order['id'], $order['bank_transfer_content']);
        $this->assertStringContainsString('img.vietqr.io', $order['qr_code_url']);

        $this->putJson("/api/orders/{$order['id']}/bank-transfer-paid")
            ->assertOk()
            ->assertJsonPath('order.payment_status', 'waiting_admin_confirmation');

        Sanctum::actingAs($admin);
        $this->putJson("/api/admin/orders/{$order['id']}/payment-status", ['payment_status' => 'paid'])
            ->assertOk()
            ->assertJsonPath('payment_status', 'paid');

        $stored = DonHang::findOrFail($order['id']);
        $this->assertSame('paid', $stored->trang_thai_thanh_toan);
        $this->assertNotNull($stored->thanh_toan_xac_nhan_at);
        $this->assertSame($admin->ma_kh, $stored->thanh_toan_xac_nhan_boi);
    }
}
