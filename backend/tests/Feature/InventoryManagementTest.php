<?php

namespace Tests\Feature;

use App\Models\BienTheSanPham;
use App\Models\GioHang;
use App\Models\KhachHang;
use App\Models\LichSuBienDongKho;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class InventoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private KhachHang $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = KhachHang::where('vai_tro', true)->firstOrFail();
        Sanctum::actingAs($this->admin);
    }

    public function test_admin_can_import_one_variant_and_stock_movement_is_created(): void
    {
        $variant = BienTheSanPham::firstOrFail();
        $stockBefore = $variant->so_luong_ton;

        $this->postJson('/api/admin/inventory/receipts', [
            'code' => 'NK-TEST-ONE',
            'import_date' => now()->format('Y-m-d'),
            'note' => 'Nhập kiểm thử',
            'items' => [
                ['variant_id' => $variant->ma_bt, 'quantity' => 7, 'note' => 'Dòng 1'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('receipt.code', 'NK-TEST-ONE')
            ->assertJsonPath('receipt.total_quantity', 7);

        $this->assertSame($stockBefore + 7, $variant->fresh()->so_luong_ton);
        $this->assertDatabaseHas('lich_su_bien_dong_kho', [
            'ma_bien_the' => $variant->ma_bt,
            'loai_bien_dong' => 'stock_import',
            'so_luong_thay_doi' => 7,
            'ma_tham_chieu' => 'NK-TEST-ONE',
        ]);
    }

    public function test_admin_can_import_multiple_variants_in_one_receipt(): void
    {
        $variants = BienTheSanPham::take(2)->get();

        $this->postJson('/api/admin/inventory/receipts', [
            'code' => 'NK-TEST-MULTI',
            'import_date' => now()->format('Y-m-d'),
            'items' => $variants->map(fn (BienTheSanPham $variant, int $index) => [
                'variant_id' => $variant->ma_bt,
                'quantity' => $index + 2,
            ])->values()->all(),
        ])
            ->assertCreated()
            ->assertJsonPath('receipt.item_count', 2)
            ->assertJsonPath('receipt.total_quantity', 5);

        $this->getJson('/api/admin/inventory/receipts?search=NK-TEST-MULTI')
            ->assertOk()
            ->assertJsonPath('data.0.code', 'NK-TEST-MULTI');
    }

    public function test_import_rejects_invalid_or_duplicate_quantity_lines(): void
    {
        $variant = BienTheSanPham::firstOrFail();

        $this->postJson('/api/admin/inventory/receipts', [
            'import_date' => now()->format('Y-m-d'),
            'items' => [
                ['variant_id' => $variant->ma_bt, 'quantity' => 0],
            ],
        ])->assertUnprocessable();

        $this->postJson('/api/admin/inventory/receipts', [
            'import_date' => now()->format('Y-m-d'),
            'items' => [
                ['variant_id' => $variant->ma_bt, 'quantity' => 1],
                ['variant_id' => $variant->ma_bt, 'quantity' => 2],
            ],
        ])->assertUnprocessable();
    }

    public function test_manual_adjustment_requires_reason_and_logs_stock_history(): void
    {
        $variant = BienTheSanPham::firstOrFail();
        $target = $variant->so_luong_ton + 3;

        $this->postJson('/api/admin/inventory/adjust', [
            'variant_id' => $variant->ma_bt,
            'stock' => $target,
            'reason' => 'Kiểm kê thực tế',
        ])->assertOk();

        $this->assertSame($target, $variant->fresh()->so_luong_ton);
        $this->assertDatabaseHas('lich_su_bien_dong_kho', [
            'ma_bien_the' => $variant->ma_bt,
            'loai_bien_dong' => 'manual_adjustment',
            'ton_kho_sau' => $target,
        ]);
    }

    public function test_low_stock_alert_uses_configured_threshold(): void
    {
        $variant = BienTheSanPham::firstOrFail();
        $variant->update(['so_luong_ton' => 4, 'nguong_canh_bao_ton' => 4, 'trang_thai' => true]);

        $this->getJson('/api/admin/inventory/alerts')
            ->assertOk()
            ->assertJsonFragment(['sku' => $variant->sku, 'stock' => 4, 'low_stock_threshold' => 4]);
    }

    public function test_order_sale_and_cancellation_create_stock_movements(): void
    {
        $customer = KhachHang::where('vai_tro', false)->firstOrFail();
        $variant = BienTheSanPham::where('so_luong_ton', '>=', 2)->firstOrFail();
        $stockBefore = $variant->so_luong_ton;

        Sanctum::actingAs($customer);
        $cart = GioHang::firstOrCreate(['ma_kh' => $customer->ma_kh]);
        $cart->chiTiets()->create([
            'ma_bien_the' => $variant->ma_bt,
            'so_luong' => 2,
        ]);

        $orderId = $this->postJson('/api/orders', [
            'ten_nguoi_nhan' => 'Khách kiểm thử',
            'so_dien_thoai' => '0909123456',
            'dia_chi_giao' => '123 Test',
            'phuong_thuc_tt' => 'cod',
        ])
            ->assertCreated()
            ->json('order.id');

        $this->assertSame($stockBefore - 2, $variant->fresh()->so_luong_ton);
        $this->assertSame(1, LichSuBienDongKho::where('ma_tham_chieu', $orderId)->where('loai_bien_dong', 'sale')->count());

        Sanctum::actingAs($this->admin);
        $this->putJson("/api/admin/orders/{$orderId}/status", ['status' => 'cancelled'])->assertOk();

        $this->assertSame($stockBefore, $variant->fresh()->so_luong_ton);
        $this->assertSame(1, LichSuBienDongKho::where('ma_tham_chieu', $orderId)->where('loai_bien_dong', 'order_cancelled')->count());
    }
}
