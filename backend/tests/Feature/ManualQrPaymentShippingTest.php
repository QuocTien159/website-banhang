<?php

namespace Tests\Feature;

use App\Models\BienTheSanPham;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\PaymentLog;
use App\Services\PayOsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ManualQrPaymentShippingTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_shipping_fee_is_calculated_by_ghn_without_legacy_zones(): void
    {
        $customer = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $variant = BienTheSanPham::where('so_luong_ton', '>', 5)->orderBy('gia_ban')->firstOrFail();

        Sanctum::actingAs($customer);
        $this->postJson('/api/cart/items', ['variant_id' => $variant->ma_bt, 'quantity' => 1])->assertOk();

        $this->postJson('/api/shipping/calculate', [
            'province_id' => '202', 'district_code' => '1442', 'ward_code' => '20101', 'address_detail' => '123 Nguyễn Huệ',
        ])
            ->assertOk()
            ->assertJsonPath('valid', true)
            ->assertJsonPath('provider', 'ghn')
            ->assertJsonPath('shipping_fee', 22000);

        $this->postJson('/api/shipping/calculate', [
            'province_id' => '202', 'district_code' => '1442', 'ward_code' => '20101', 'address_detail' => '123 Test',
        ])
            ->assertOk()
            ->assertJsonPath('shipping_fee', 22000);

        $this->postJson('/api/shipping/calculate', [
            'province_id' => '202', 'district_code' => '1442', 'ward_code' => '20101', 'address_detail' => '123 Test',
        ])
            ->assertOk()
            ->assertJsonPath('shipping_fee', 22000);

        $this->postJson('/api/shipping/calculate', [
            'province_id' => '202', 'district_code' => '1442', 'ward_code' => '20101',
        ])
            ->assertUnprocessable();
    }

    public function test_customer_can_place_payos_qr_order_and_webhook_marks_it_paid(): void
    {
        $customer = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $variant = BienTheSanPham::where('so_luong_ton', '>', 2)->firstOrFail();

        config([
            'services.payos.client_id' => 'test-client',
            'services.payos.api_key' => 'test-api-key',
            'services.payos.checksum_key' => 'test-checksum-key',
            'services.payos.base_url' => 'https://api-merchant.payos.vn',
            'services.payos.frontend_url' => 'http://localhost:5173',
            'services.payos.return_url' => 'http://localhost:5173/payment/payos/return?orderCode={payos_order_code}',
            'services.payos.cancel_url' => 'http://localhost:5173/payment/payos/cancel?orderCode={payos_order_code}',
        ]);

        $payosPayload = null;
        Http::fake(array_merge($this->ghnFakes(), [
            'https://api-merchant.payos.vn/*' => function ($request) use (&$payosPayload) {
                $payosPayload = $request->data();

                return Http::response([
                    'code' => '00',
                    'desc' => 'success',
                    'data' => [
                        'paymentLinkId' => 'payos-link-1',
                        'checkoutUrl' => 'https://pay.payos.vn/web/payos-link-1',
                        'qrCode' => 'https://pay.payos.vn/qr/payos-link-1.png',
                    ],
                ]);
            },
        ]));

        Sanctum::actingAs($customer);
        $this->postJson('/api/cart/items', ['variant_id' => $variant->ma_bt, 'quantity' => 1])->assertOk();

        $order = $this->postJson('/api/orders', [
            'ten_nguoi_nhan' => 'Khách QR',
            'so_dien_thoai' => '0909123456',
            'province_id' => '202',
            'district_code' => '1442',
            'ward_code' => '20101',
            'address_detail' => '123 Nguyễn Huệ',
            'phuong_thuc_tt' => 'payos',
        ])
            ->assertCreated()
            ->assertJsonPath('order.payment_method', 'payos')
            ->assertJsonPath('order.payment_provider', 'payos')
            ->assertJsonPath('order.payment_status', 'pending_payment')
            ->assertJsonPath('order.payment_link_id', 'payos-link-1')
            ->assertJsonPath('order.payment_checkout_url', 'https://pay.payos.vn/web/payos-link-1')
            ->assertJsonPath('order.shipping', 22000)
            ->assertJsonPath('order.shipping_info.province_type', 'ghn')
            ->json('order');

        $this->assertSame('https://pay.payos.vn/qr/payos-link-1.png', $order['qr_code_url']);
        $this->assertNotEmpty($order['payos_order_code']);
        $this->assertSame("http://localhost:5173/payment/payos/return?orderCode={$order['payos_order_code']}", $payosPayload['returnUrl'] ?? null);
        $this->assertSame("http://localhost:5173/payment/payos/cancel?orderCode={$order['payos_order_code']}", $payosPayload['cancelUrl'] ?? null);

        $this->putJson("/api/orders/{$order['id']}/bank-transfer-paid")
            ->assertStatus(422);

        $webhookData = [
            'orderCode' => (int) $order['payos_order_code'],
            'amount' => (int) $order['total'],
            'description' => $order['bank_transfer_content'],
            'paymentLinkId' => 'payos-link-1',
            'code' => '00',
            'desc' => 'success',
            'reference' => 'PAYOS-REF-1',
            'transactionDateTime' => now()->format('Y-m-d H:i:s'),
            'currency' => 'VND',
        ];

        $this->postJson('/api/payment/payos-webhook', [
            'code' => '00',
            'desc' => 'success',
            'success' => true,
            'data' => $webhookData,
            'signature' => app(PayOsService::class)->signature($webhookData),
        ])->assertOk();

        $stored = DonHang::findOrFail($order['id']);
        $this->assertSame('ghn', $stored->shipping_provider);
        $this->assertSame('202', (string) $stored->ma_tinh_thanh);
        $this->assertSame('Ho Chi Minh', $stored->tinh_thanh);
        $this->assertSame('1442', (string) $stored->ma_quan_huyen);
        $this->assertSame('Quan 1', $stored->quan_huyen);
        $this->assertSame('20101', $stored->ma_phuong_xa);
        $this->assertSame('Ben Nghe', $stored->phuong_xa);
        $this->assertSame('123 Nguyễn Huệ', $stored->dia_chi_chi_tiet);
        $this->assertSame('paid', $stored->trang_thai_thanh_toan);
        $this->assertSame('confirmed', $stored->trang_thai);
        $this->assertSame('payos', $stored->payment_provider);
        $this->assertNotNull($stored->thanh_toan_xac_nhan_at);
        $this->assertNotNull($stored->paid_at);
        $this->assertTrue(PaymentLog::where('ma_dh', $order['id'])->where('provider', 'payos')->where('verified', true)->exists());

        $this->postJson('/api/payment/payos-webhook', [
            'code' => '00',
            'desc' => 'success',
            'success' => true,
            'data' => $webhookData,
            'signature' => app(PayOsService::class)->signature($webhookData),
        ])->assertOk();

        $this->assertSame('paid', $stored->fresh()->trang_thai_thanh_toan);
    }

    public function test_payos_webhook_with_wrong_amount_does_not_mark_order_paid(): void
    {
        $order = DonHang::create([
            'ma_kh' => KhachHang::where('email', 'user@example.com')->firstOrFail()->ma_kh,
            'ngay_dat' => now(),
            'tong_tien' => 250000,
            'phuong_thuc_tt' => 'payos',
            'payment_provider' => 'payos',
            'payos_order_code' => 123456,
            'payment_link_id' => 'payos-link-wrong-amount',
            'trang_thai_thanh_toan' => 'pending_payment',
            'dia_chi_giao' => 'Khách | 0909123456 | 123 Test',
            'trang_thai' => 'pending',
        ]);

        config(['services.payos.checksum_key' => 'test-checksum-key']);

        $webhookData = [
            'orderCode' => 123456,
            'amount' => 240000,
            'description' => 'DH123456',
            'paymentLinkId' => 'payos-link-wrong-amount',
            'code' => '00',
            'desc' => 'success',
        ];

        $this->postJson('/api/payment/payos-webhook', [
            'code' => '00',
            'desc' => 'success',
            'success' => true,
            'data' => $webhookData,
            'signature' => app(PayOsService::class)->signature($webhookData),
        ])->assertStatus(400);

        $this->assertSame('pending_payment', $order->fresh()->trang_thai_thanh_toan);
        $this->assertTrue(PaymentLog::where('ma_dh', $order->ma_dh)->where('event_type', 'payos_webhook_amount_mismatch')->exists());
    }

    public function test_address_api_returns_an_empty_list_when_ghn_is_not_configured(): void
    {
        config(['services.ghn.token' => null]);
        Sanctum::actingAs(KhachHang::where('email', 'user@example.com')->firstOrFail());

        $this->getJson('/api/address/provinces')
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('message', 'Dữ liệu địa chỉ GHN chưa sẵn sàng.');
    }

    public function test_admin_order_list_syncs_paid_payos_order_from_payos_api(): void
    {
        $customer = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $admin = KhachHang::where('vai_tro', true)->firstOrFail();
        $order = DonHang::create([
            'ma_kh' => $customer->ma_kh,
            'ngay_dat' => now(),
            'tong_tien' => 3000,
            'phuong_thuc_tt' => 'payos',
            'payment_provider' => 'payos',
            'payos_order_code' => 777,
            'payment_link_id' => 'payos-link-paid',
            'trang_thai_thanh_toan' => 'pending_payment',
            'dia_chi_giao' => 'Khách | 0909123456 | 123 Test',
            'trang_thai' => 'pending',
        ]);

        config([
            'services.payos.client_id' => 'test-client',
            'services.payos.api_key' => 'test-api-key',
            'services.payos.checksum_key' => 'test-checksum-key',
            'services.payos.base_url' => 'https://api-merchant.payos.vn',
        ]);

        Http::fake([
            'https://api-merchant.payos.vn/v2/payment-requests/payos-link-paid' => Http::response([
                'code' => '00',
                'desc' => 'success',
                'data' => [
                    'id' => 'payos-link-paid',
                    'orderCode' => 777,
                    'amount' => 3000,
                    'amountPaid' => 3000,
                    'amountRemaining' => 0,
                    'status' => 'PAID',
                ],
            ]),
        ]);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/orders')
            ->assertOk()
            ->assertJsonPath('data.0.id', $order->ma_dh)
            ->assertJsonPath('data.0.payment_status', 'paid')
            ->assertJsonPath('data.0.status', 'confirmed');

        $order->refresh();
        $this->assertSame('paid', $order->trang_thai_thanh_toan);
        $this->assertSame('confirmed', $order->trang_thai);
        $this->assertNotNull($order->paid_at);
    }

    public function test_admin_cannot_confirm_unpaid_payos_order_manually(): void
    {
        $customer = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $admin = KhachHang::where('vai_tro', true)->firstOrFail();
        $order = DonHang::create([
            'ma_kh' => $customer->ma_kh,
            'ngay_dat' => now(),
            'tong_tien' => 3000,
            'phuong_thuc_tt' => 'payos',
            'payment_provider' => 'payos',
            'payos_order_code' => 778,
            'payment_link_id' => 'payos-link-pending',
            'trang_thai_thanh_toan' => 'pending_payment',
            'dia_chi_giao' => 'Khách | 0909123456 | 123 Test',
            'trang_thai' => 'pending',
        ]);

        Sanctum::actingAs($admin);
        $this->putJson("/api/admin/orders/{$order->ma_dh}/status", ['status' => 'confirmed'])
            ->assertStatus(422);

        $this->assertSame('pending', $order->fresh()->trang_thai);
    }
}
