<?php

namespace Tests\Feature;

use App\Models\BienTheSanPham;
use App\Models\KhachHang;
use App\Models\SanPham;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class VariantManagementTest extends TestCase
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

    public function test_catalog_only_exposes_real_sellable_skus_and_keeps_missing_combinations_distinct(): void
    {
        $product = $this->underArmourProduct();

        $response = $this->getJson("/api/products/{$product->ma_sp}")
            ->assertOk()
            ->assertJsonCount(2, 'variants');

        $variants = collect($response->json('variants'));
        $this->assertTrue($variants->contains(fn (array $variant) => $variant['sku'] === 'UA-TECH-BLU-L' && $variant['stock'] === 18));
        $this->assertTrue($variants->contains(fn (array $variant) => $variant['sku'] === 'UA-TECH-BLK-M' && $variant['stock'] === 24));
        $this->assertFalse($variants->contains(fn (array $variant) => $this->hasAttributes($variant, [
            'Màu sắc' => 'Xanh dương',
            'Kích thước' => 'M',
        ])));

        $this->getJson('/api/admin/inventory/alerts')
            ->assertOk()
            ->assertJsonMissing(['sku' => 'UA-TECH-BLU-M']);
    }

    public function test_admin_can_create_a_real_out_of_stock_sku_then_stock_workflow_controls_its_availability(): void
    {
        $product = $this->underArmourProduct();

        $variant = $this->postJson('/api/admin/variants', [
            'product_id' => $product->ma_sp,
            'sku' => 'UA-TECH-BLU-M',
            'list_price' => 890000,
            'price' => 749000,
            'low_stock_threshold' => 5,
            'sell_status' => 'active',
            'attributes' => [
                ['name' => 'Thương hiệu', 'value' => 'Under Armour'],
                ['name' => 'Màu sắc', 'value' => 'Xanh dương'],
                ['name' => 'Kích thước', 'value' => 'M'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('variant.sku', 'UA-TECH-BLU-M')
            ->assertJsonPath('variant.stock', 0)
            ->assertJsonPath('variant.stock_status', 'out_of_stock')
            ->json('variant');

        $this->assertDatabaseHas('bien_the_san_pham', [
            'ma_bt' => $variant['id'],
            'so_luong_ton' => 0,
            'trang_thai_ban' => 'active',
        ]);

        $this->putJson("/api/admin/variants/{$variant['id']}", ['stock' => 8])
            ->assertUnprocessable();

        $this->getJson('/api/admin/inventory/alerts')
            ->assertOk()
            ->assertJsonFragment(['sku' => 'UA-TECH-BLU-M', 'stock' => 0]);

        $this->postJson('/api/admin/inventory/adjust', [
            'variant_id' => $variant['id'],
            'stock' => 8,
            'reason' => 'Bổ sung kho',
        ])->assertOk();

        $this->getJson("/api/products/{$product->ma_sp}")
            ->assertOk()
            ->assertJsonFragment(['sku' => 'UA-TECH-BLU-M', 'stock' => 8, 'stock_status' => 'in_stock']);

        $this->getJson('/api/admin/inventory/alerts')
            ->assertOk()
            ->assertJsonMissing(['sku' => 'UA-TECH-BLU-M']);
    }

    public function test_variant_management_requires_an_admin_and_is_query_first(): void
    {
        $staff = KhachHang::create([
            'ten_kh' => 'Nhân viên biến thể',
            'email' => 'variant-staff@example.com',
            'mat_khau' => Hash::make('staff123'),
            'dien_thoai' => '0933333333',
            'vai_tro' => false,
            'role' => 'staff',
            'trang_thai' => true,
            'ngay_tao' => now(),
        ]);

        Sanctum::actingAs($staff);
        $this->getJson('/api/admin/variants?search=UA-TECH')->assertForbidden();

        Sanctum::actingAs($this->admin);
        $this->getJson('/api/admin/variants')
            ->assertOk()
            ->assertJsonPath('meta.requires_query', true)
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/admin/variants?search=UA-TECH')
            ->assertOk()
            ->assertJsonPath('meta.requires_query', false)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Áo Chạy Bộ Under Armour Tech 2.0');
    }

    public function test_admin_variant_update_uses_price_field_and_keeps_stock_immutable(): void
    {
        $variant = BienTheSanPham::where('sku', 'UA-TECH-BLK-M')->firstOrFail();

        $this->putJson("/api/admin/variants/{$variant->ma_bt}", [
            'price' => 739000,
            'low_stock_threshold' => 3,
        ])
            ->assertOk()
            ->assertJsonPath('variant.price', 739000)
            ->assertJsonPath('variant.low_stock_threshold', 3);

        $this->assertDatabaseHas('bien_the_san_pham', [
            'ma_bt' => $variant->ma_bt,
            'gia_ban' => 739000,
            'so_luong_ton' => 24,
        ]);
    }

    private function underArmourProduct(): SanPham
    {
        return SanPham::where('ten_sp', 'Áo Chạy Bộ Under Armour Tech 2.0')->firstOrFail();
    }

    private function hasAttributes(array $variant, array $expected): bool
    {
        $attributes = collect($variant['attributes'])->mapWithKeys(fn (array $attribute) => [$attribute['name'] => $attribute['value']]);

        return collect($expected)->every(fn (string $value, string $name) => $attributes->get($name) === $value);
    }
}
