<?php

namespace Tests\Feature;

use App\Models\DanhMuc;
use App\Models\KhachHang;
use App\Models\SanPham;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminCatalogManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(KhachHang::where('vai_tro', true)->firstOrFail());
    }

    public function test_admin_can_manage_categories_with_product_constraints(): void
    {
        $created = $this->postJson('/api/admin/categories', ['name' => 'Đồ bảo hộ'])
            ->assertCreated()
            ->json();

        $this->putJson("/api/admin/categories/{$created['id']}", ['name' => 'Bảo hộ thể thao'])
            ->assertOk()
            ->assertJsonPath('name', 'Bảo hộ thể thao');

        $usedCategory = DanhMuc::whereHas('sanPhams')->firstOrFail();
        $this->putJson("/api/admin/categories/{$usedCategory->ma_dm}", ['active' => false])
            ->assertUnprocessable();
        $this->deleteJson("/api/admin/categories/{$usedCategory->ma_dm}")
            ->assertUnprocessable();

        $this->deleteJson("/api/admin/categories/{$created['id']}")->assertOk();
    }

    public function test_admin_can_upload_product_images(): void
    {
        Storage::fake('public');

        $response = $this->post('/api/admin/products/images', [
            'images' => [UploadedFile::fake()->image('shirt.jpg', 800, 800)->size(500)],
        ]);

        $response->assertCreated()->assertJsonStructure(['images' => [['url', 'path']]]);
        Storage::disk('public')->assertExists($response->json('images.0.path'));
    }

    public function test_admin_can_create_and_update_product_images_and_variants(): void
    {
        Storage::fake('public');
        $category = DanhMuc::where('ten_dm', 'Áo')->firstOrFail();
        $uploads = $this->post('/api/admin/products/images', [
            'images' => [
                UploadedFile::fake()->image('front.jpg', 900, 900)->size(500),
                UploadedFile::fake()->image('back.jpg', 900, 900)->size(500),
            ],
        ])->assertCreated()->json('images');
        $payload = [
            'name' => 'Áo Test Admin',
            'category_id' => $category->ma_dm,
            'description' => 'Sản phẩm kiểm thử',
            'base_price' => 500000,
            'status' => 'active',
            'images' => [
                $uploads[0] + ['is_primary' => true],
                $uploads[1] + ['is_primary' => false],
            ],
            'variants' => [
                [
                    'sku' => 'ADMIN-TEST-DEN-M', 'price' => 450000, 'stock' => 10, 'active' => true,
                    'attributes' => [['name' => 'Màu sắc', 'value' => 'Đen'], ['name' => 'Kích thước', 'value' => 'M']],
                ],
                [
                    'sku' => 'ADMIN-TEST-TRANG-L', 'price' => 520000, 'stock' => 5, 'active' => true,
                    'attributes' => [['name' => 'Màu sắc', 'value' => 'Trắng'], ['name' => 'Kích thước', 'value' => 'L']],
                ],
            ],
        ];

        $created = $this->postJson('/api/admin/products', $payload)
            ->assertCreated()
            ->assertJsonCount(2, 'images')
            ->assertJsonCount(2, 'variants')
            ->json();

        $payload['name'] = 'Áo Test Admin Đã Sửa';
        $payload['images'] = [[
            'id' => $created['images'][1]['id'],
            'url' => $created['images'][1]['url'],
            'is_primary' => true,
        ]];
        $payload['variants'] = [[
            'id' => $created['variants'][0]['id'],
            'sku' => $created['variants'][0]['sku'],
            'price' => 475000,
            'stock' => $created['variants'][0]['stock'],
            'low_stock_threshold' => $created['variants'][0]['low_stock_threshold'],
            'active' => true,
            'attributes' => $created['variants'][0]['attributes'],
        ]];

        $this->putJson("/api/admin/products/{$created['id']}", $payload)
            ->assertOk()
            ->assertJsonPath('name', 'Áo Test Admin Đã Sửa')
            ->assertJsonCount(1, 'images')
            ->assertJsonCount(1, 'variants')
            ->assertJsonPath('variants.0.price', 475000);

        $this->assertSame(1, SanPham::findOrFail($created['id'])->hinhAnhs()->count());
    }

    public function test_duplicate_sku_and_attribute_combinations_are_rejected(): void
    {
        $category = DanhMuc::where('ten_dm', 'Áo')->firstOrFail();
        $base = [
            'name' => 'Sản phẩm lỗi',
            'category_id' => $category->ma_dm,
            'description' => '',
            'base_price' => 100000,
            'status' => 'active',
            'images' => [['url' => 'https://example.com/image.jpg', 'is_primary' => true]],
        ];

        $variant = [
            'sku' => 'DUPLICATE-SKU',
            'price' => 100000,
            'stock' => 1,
            'active' => true,
            'attributes' => [['name' => 'Màu sắc', 'value' => 'Đen']],
        ];

        $this->postJson('/api/admin/products', $base + ['variants' => [$variant, $variant]])
            ->assertUnprocessable();
    }
}
