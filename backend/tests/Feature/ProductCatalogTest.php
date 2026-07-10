<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductCatalogTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_catalog_contains_expected_sport_categories_and_filters(): void
    {
        $this->getJson('/api/categories')
            ->assertOk()
            ->assertJsonCount(4)
            ->assertJsonFragment(['name' => 'Áo', 'count' => 5])
            ->assertJsonFragment(['name' => 'Quần', 'count' => 5])
            ->assertJsonFragment(['name' => 'Giày', 'count' => 5])
            ->assertJsonFragment(['name' => 'Phụ kiện', 'count' => 5]);

        $response = $this->getJson('/api/products?category=Giày&brand=Nike');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonPath('data.0.name', 'Giày Bóng Rổ Nike Precision 7')
            ->assertJsonPath('data.0.brand', 'Nike')
            ->assertJsonStructure([
                'meta' => ['filters' => ['Thương hiệu', 'Màu sắc', 'Kích thước', 'Khối lượng', 'Độ đàn hồi']],
            ]);
    }

    public function test_product_detail_returns_complete_variant_information(): void
    {
        $productId = \App\Models\SanPham::where('ten_sp', 'Dây Kháng Lực GoodFit')->value('ma_sp');

        $this->getJson("/api/products/{$productId}")
            ->assertOk()
            ->assertJsonPath('brand', 'GoodFit')
            ->assertJsonPath('required_attributes.0', 'Màu sắc')
            ->assertJsonPath('required_attributes.1', 'Độ đàn hồi')
            ->assertJsonCount(4, 'variants')
            ->assertJsonStructure([
                'variants' => [
                    '*' => ['id', 'sku', 'price', 'stock', 'attributes'],
                ],
            ]);
    }

    public function test_other_brand_filter_excludes_nike_adidas_and_puma(): void
    {
        $response = $this->getJson('/api/products?brand=Khác&per_page=20');

        $response->assertOk()->assertJsonPath('meta.total', 5);

        $brands = collect($response->json('data'))->pluck('brand');
        $this->assertTrue($brands->contains('Vifa Sport'));
        $this->assertTrue($brands->contains('GoodFit'));
        $this->assertTrue($brands->contains('Under Armour'));
        $this->assertTrue($brands->contains('Reebok'));
        $this->assertFalse($brands->contains('Nike'));
        $this->assertFalse($brands->contains('Adidas'));
        $this->assertFalse($brands->contains('Puma'));
    }
}
