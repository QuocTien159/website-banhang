<?php

namespace Tests\Feature;

use App\Models\BienTheSanPham;
use App\Models\DonHang;
use App\Models\KhachHang;
use Illuminate\Support\Facades\Hash;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PurchaseFlowTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_customer_can_add_an_in_stock_variant_and_place_an_order(): void
    {
        $customer = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $variant = BienTheSanPham::where('so_luong_ton', '>', 2)->firstOrFail();
        $stockBefore = $variant->so_luong_ton;

        Sanctum::actingAs($customer);

        $this->postJson('/api/cart/items', [
            'variant_id' => $variant->ma_bt,
            'quantity' => 2,
        ])
            ->assertOk()
            ->assertJsonPath('items.0.variant_id', $variant->ma_bt)
            ->assertJsonPath('items.0.quantity', 2);

        $this->postJson('/api/orders', [
            'ten_nguoi_nhan' => 'Khách hàng Demo',
            'so_dien_thoai' => '0909123456',
            'dia_chi_giao' => '123 Nguyễn Huệ, Quận 1, TP.HCM',
            'phuong_thuc_tt' => 'cod',
            'ghi_chu' => 'Giao giờ hành chính',
        ])
            ->assertCreated()
            ->assertJsonPath('order.status', 'pending')
            ->assertJsonPath('order.items.0.variant_id', $variant->ma_bt);

        $this->assertSame($stockBefore - 2, $variant->fresh()->so_luong_ton);
        $this->assertDatabaseCount('chi_tiet_gio_hang', 0);
        $this->assertSame(1, DonHang::where('ma_kh', $customer->ma_kh)->count());
    }

    public function test_out_of_stock_variant_cannot_be_added_to_cart(): void
    {
        $customer = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $variant = BienTheSanPham::where('so_luong_ton', 0)->firstOrFail();

        Sanctum::actingAs($customer);

        $this->postJson('/api/cart/items', [
            'variant_id' => $variant->ma_bt,
            'quantity' => 1,
        ])->assertUnprocessable();
    }

    public function test_customer_can_only_view_own_order_detail(): void
    {
        $customer = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $otherCustomer = KhachHang::create([
            'ten_kh' => 'Khách khác',
            'email' => 'other@example.com',
            'mat_khau' => Hash::make('secret123'),
            'dien_thoai' => '0911111111',
            'vai_tro' => false,
            'trang_thai' => true,
            'ngay_tao' => now(),
        ]);

        $order = DonHang::create([
            'ma_kh' => $customer->ma_kh,
            'ngay_dat' => now(),
            'tong_tien' => 100000,
            'phuong_thuc_tt' => 'cod',
            'dia_chi_giao' => 'Khách Demo | 0909123456 | TP HCM',
            'trang_thai' => 'pending',
        ]);

        Sanctum::actingAs($customer);
        $this->getJson("/api/orders/{$order->ma_dh}")
            ->assertOk()
            ->assertJsonPath('id', $order->ma_dh);

        Sanctum::actingAs($otherCustomer);
        $this->getJson("/api/orders/{$order->ma_dh}")
            ->assertNotFound();
    }
}
