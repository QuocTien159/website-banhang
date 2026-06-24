<?php

namespace Tests\Feature;

use App\Models\BienTheSanPham;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\KhachHang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminOrderListTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_admin_order_list_returns_display_ready_date_and_product_counts(): void
    {
        $admin = KhachHang::where('vai_tro', true)->firstOrFail();
        $customer = KhachHang::where('vai_tro', false)->firstOrFail();
        $variants = BienTheSanPham::take(2)->get();

        $order = DonHang::create([
            'ma_kh' => $customer->ma_kh,
            'ngay_dat' => now()->setTime(14, 30),
            'tong_tien' => 300000,
            'phuong_thuc_tt' => 'cod',
            'dia_chi_giao' => 'Khách Test | 0909123456 | TP HCM',
            'trang_thai' => 'pending',
        ]);

        ChiTietDonHang::create([
            'ma_dh' => $order->ma_dh,
            'ma_bien_the' => $variants[0]->ma_bt,
            'so_luong' => 2,
            'don_gia' => 100000,
        ]);
        ChiTietDonHang::create([
            'ma_dh' => $order->ma_dh,
            'ma_bien_the' => $variants[1]->ma_bt,
            'so_luong' => 1,
            'don_gia' => 100000,
        ]);

        Sanctum::actingAs($admin);

        $this->getJson('/api/admin/orders?search='.$order->ma_dh)
            ->assertOk()
            ->assertJsonPath('data.0.id', $order->ma_dh)
            ->assertJsonPath('data.0.customer_name', $customer->ten_kh)
            ->assertJsonPath('data.0.payment_method', 'cod')
            ->assertJsonPath('data.0.item_count', 2)
            ->assertJsonPath('data.0.items_count', 3)
            ->assertJsonStructure(['data' => [['created_at', 'created_at_formatted', 'total_quantity']]]);
    }
}
