<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CloudinaryMediaManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    private KhachHang $admin;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->admin = KhachHang::where('vai_tro', true)->firstOrFail();
        config([
            'services.cloudinary.cloud_name' => 'test-cloud',
            'services.cloudinary.api_key' => 'test-key',
            'services.cloudinary.api_secret' => 'test-secret',
            'services.cloudinary.upload_preset' => 'ml_default',
        ]);
        Http::fake([
            'https://api.cloudinary.com/*' => Http::response([
                'secure_url' => 'https://res.cloudinary.com/test-cloud/image/upload/v1/tienprosport/products/test-image',
                'public_id' => 'tienprosport/products/test-image',
                'width' => 1200,
                'height' => 800,
            ], 200),
        ]);
    }

    public function test_admin_can_upload_cloudinary_product_and_announcement_images(): void
    {
        Sanctum::actingAs($this->admin);

        $productImage = $this->post('/api/admin/products/images', [
            'images' => [UploadedFile::fake()->image('product.jpg', 1200, 800)->size(800)],
        ])->assertCreated()->json('images.0');
        $this->assertSame('cloudinary', $productImage['provider']);
        $this->assertNotEmpty($productImage['upload_token']);
        $this->assertArrayNotHasKey('api_secret', $productImage);

        $announcementImage = $this->post('/api/admin/announcements/images', [
            'images' => [UploadedFile::fake()->image('announcement.jpg', 1200, 675)->size(700)],
        ])->assertCreated()->json('images.0');

        $created = $this->postJson('/api/admin/announcements', [
            'title' => 'Thông báo ảnh Cloudinary',
            'content' => 'Nội dung thông báo có ảnh đã được xác minh ở backend.',
            'type' => 'update',
            'status' => 'published',
            'images' => [$announcementImage],
        ])->assertCreated()
            ->assertJsonPath('images.0.original_url', 'https://res.cloudinary.com/test-cloud/image/upload/v1/tienprosport/products/test-image')
            ->assertJsonPath('images.0.announcement_url', fn ($url) => str_contains($url, 'f_auto,q_auto:good,c_limit,w_1600'))
            ->json();

        $this->assertSame($created['images'][0]['original_url'], $created['images'][0]['url']);

        Http::assertSentCount(2);
    }

    public function test_small_images_are_rejected_before_upload(): void
    {
        Sanctum::actingAs($this->admin);

        $this->postJson('/api/admin/products/images', [
            'images' => [UploadedFile::fake()->image('small-product.jpg', 799, 800)],
        ])->assertUnprocessable()->assertJsonValidationErrors('images.0');

        $this->postJson('/api/admin/announcements/images', [
            'images' => [UploadedFile::fake()->image('small-announcement.jpg', 900, 600)],
        ])->assertUnprocessable()->assertJsonValidationErrors('images');

        Http::assertNothingSent();
    }

    public function test_staff_cannot_manage_product_or_announcement_images(): void
    {
        $staff = KhachHang::create([
            'ten_kh' => 'Nhân viên ảnh', 'email' => 'staff-images@example.com',
            'mat_khau' => bcrypt('staff123'), 'dien_thoai' => '0913333333',
            'vai_tro' => false, 'role' => 'staff', 'trang_thai' => true, 'ngay_tao' => now(),
        ]);
        Sanctum::actingAs($staff);

        $this->post('/api/admin/products/images', [
            'images' => [UploadedFile::fake()->image('blocked.jpg', 800, 800)],
        ])->assertForbidden();
        $this->post('/api/admin/announcements/images', [
            'images' => [UploadedFile::fake()->image('blocked.jpg', 800, 600)],
        ])->assertForbidden();
    }
}
