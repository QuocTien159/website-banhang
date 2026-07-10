<?php

namespace Tests\Feature;

use App\Models\SanPham;
use Database\Seeders\SportsCatalogSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SportsCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private array $catalogNames = [
        'Áo Chạy Bộ Under Armour Tech 2.0',
        'Áo Tập Gym Reebok Speedwick',
        'Quần Short Chạy Adidas Own The Run',
        'Quần Legging Tập Puma FormKnit',
        'Giày Bóng Rổ Nike Precision 7',
        'Giày Chạy Trail Adidas Terrex Soulstride',
        'Bóng Đá Adidas Tiro League',
        'Bóng Rổ Nike Everyday Playground',
    ];

    public function test_sports_catalog_has_a_unique_sku_and_mapped_image_for_every_variant(): void
    {
        $products = SanPham::with(['bienThes.hinhAnhs', 'hinhAnhs'])
            ->whereIn('ten_sp', $this->catalogNames)
            ->get();

        $this->assertCount(8, $products);
        foreach ($products as $product) {
            $this->assertSame(2, $product->bienThes->count(), $product->ten_sp);
            $this->assertGreaterThanOrEqual($product->bienThes->count(), $product->hinhAnhs->count(), $product->ten_sp);
            $this->assertTrue($product->bienThes->every(fn ($variant) => $variant->hinhAnhs->isNotEmpty()), $product->ten_sp);
            $this->assertSame($product->bienThes->count(), $product->bienThes->pluck('sku')->unique()->count(), $product->ten_sp);
        }
    }

    public function test_catalog_seeder_is_idempotent_and_product_api_returns_variant_image(): void
    {
        $this->seed(SportsCatalogSeeder::class);
        $this->assertSame(8, SanPham::whereIn('ten_sp', $this->catalogNames)->count());

        $product = SanPham::where('ten_sp', 'Giày Bóng Rổ Nike Precision 7')->firstOrFail();
        $this->getJson("/api/products/{$product->ma_sp}")
            ->assertOk()
            ->assertJsonCount(2, 'variants')
            ->assertJsonPath('variants.0.image', fn ($image) => is_string($image) && str_contains($image, 'images.unsplash.com'));
    }
}
