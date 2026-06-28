<?php

namespace Tests\Feature;

use App\Models\BienTheSanPham;
use App\Models\GiaTriThuocTinh;
use App\Models\KhachHang;
use App\Models\ThuocTinh;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AdminAttributeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(KhachHang::where('vai_tro', true)->firstOrFail());
    }

    public function test_admin_can_create_attribute_and_manage_values(): void
    {
        $attribute = $this->postJson('/api/admin/attributes', [
            'name' => 'Chất liệu test',
            'slug' => 'chat-lieu-test',
            'type' => 'select',
            'active' => true,
            'description' => 'Dùng cho kiểm thử',
            'values' => [
                ['value' => 'Cotton', 'slug' => 'cotton', 'sort_order' => 1, 'active' => true],
                ['value' => 'Polyester', 'slug' => 'polyester', 'sort_order' => 2, 'active' => true],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('name', 'Chất liệu test')
            ->assertJsonCount(2, 'values')
            ->json();

        $this->putJson("/api/admin/attributes/{$attribute['id']}", [
            'name' => 'Chất liệu test sửa',
            'slug' => 'chat-lieu-test-sua',
            'type' => 'select',
            'active' => false,
            'description' => null,
        ])
            ->assertOk()
            ->assertJsonPath('active', false);

        $value = $this->postJson("/api/admin/attributes/{$attribute['id']}/values", [
            'value' => 'Da tổng hợp',
            'slug' => 'da-tong-hop',
            'sort_order' => 3,
            'active' => true,
        ])
            ->assertCreated()
            ->assertJsonPath('value', 'Da tổng hợp')
            ->json();

        $this->putJson("/api/admin/attributes/{$attribute['id']}/values/{$value['id']}", [
            'value' => 'Da tổng hợp cao cấp',
            'slug' => 'da-tong-hop-cao-cap',
            'sort_order' => 4,
            'active' => true,
        ])
            ->assertOk()
            ->assertJsonPath('sort_order', 4);

        $this->getJson('/api/admin/attributes?search=chat-lieu-test-sua')
            ->assertOk()
            ->assertJsonFragment(['slug' => 'chat-lieu-test-sua']);
    }

    public function test_product_options_include_active_attribute_metadata(): void
    {
        $this->getJson('/api/admin/products/options')
            ->assertOk()
            ->assertJsonStructure([
                'attributes' => [
                    '*' => ['id', 'name', 'slug', 'type', 'values'],
                ],
            ]);
    }

    public function test_used_attribute_value_cannot_be_deleted(): void
    {
        $attribute = ThuocTinh::create([
            'ten_tt' => 'Thuộc tính đang dùng',
            'slug' => 'thuoc-tinh-dang-dung',
            'loai_hien_thi' => 'select',
            'trang_thai' => true,
            'ngay_tao' => now(),
            'ngay_cap_nhat' => now(),
        ]);

        $value = GiaTriThuocTinh::create([
            'ma_tt' => $attribute->ma_tt,
            'gia_tri' => 'Giá trị đang dùng',
            'slug' => 'gia-tri-dang-dung',
            'trang_thai' => true,
            'ngay_tao' => now(),
            'ngay_cap_nhat' => now(),
        ]);

        $variant = BienTheSanPham::firstOrFail();
        $variant->giaTriThuocTinhs()->attach($value->ma_gt);

        $this->deleteJson("/api/admin/attributes/{$attribute->ma_tt}/values/{$value->ma_gt}")
            ->assertUnprocessable();

        $this->deleteJson("/api/admin/attributes/{$attribute->ma_tt}")
            ->assertUnprocessable();
    }
}
