<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AnnouncementImageManagementTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(KhachHang::where('vai_tro', true)->firstOrFail());
        Storage::fake('public');
    }

    public function test_admin_can_upload_create_reorder_and_remove_announcement_images(): void
    {
        $uploads = $this->post('/api/admin/announcements/images', [
            'images' => [
                UploadedFile::fake()->image('first.jpg', 900, 600)->size(500),
                UploadedFile::fake()->image('second.png', 800, 600)->size(400),
            ],
        ])->assertCreated()->json('images');

        Storage::disk('public')->assertExists($uploads[0]['path']);
        Storage::disk('public')->assertExists($uploads[1]['path']);

        $created = $this->postJson('/api/admin/announcements', [
            'title' => 'Thông báo có hình',
            'content' => 'Nội dung chi tiết của thông báo có nhiều hình ảnh minh họa.',
            'type' => 'update',
            'status' => 'published',
            'published_at' => now()->subMinute()->toISOString(),
            'images' => [$uploads[1], $uploads[0]],
        ])->assertCreated()
            ->assertJsonPath('images.0.url', $uploads[1]['url'])
            ->assertJsonPath('images.1.url', $uploads[0]['url'])
            ->json();

        $publicItems = $this->getJson('/api/announcements')
            ->assertOk()
            ->assertJsonFragment(['title' => 'Thông báo có hình'])
            ->json();
        $publicAnnouncement = collect($publicItems)->firstWhere('title', 'Thông báo có hình');
        $this->assertSame($uploads[1]['url'], $publicAnnouncement['images'][0]['url']);

        $this->putJson("/api/admin/announcements/{$created['id']}", [
            'title' => 'Thông báo đã sửa',
            'content' => 'Nội dung sau khi chỉnh sửa.',
            'type' => 'general',
            'status' => 'published',
            'published_at' => now()->subMinute()->toISOString(),
            'images' => [[
                'id' => $created['images'][0]['id'],
                'url' => $created['images'][0]['url'],
                'path' => $created['images'][0]['path'],
            ]],
        ])->assertOk()->assertJsonCount(1, 'images');

        Storage::disk('public')->assertMissing($uploads[0]['path']);
        Storage::disk('public')->assertExists($uploads[1]['path']);

        $this->deleteJson("/api/admin/announcements/{$created['id']}")->assertOk();
        Storage::disk('public')->assertMissing($uploads[1]['path']);
    }

    public function test_invalid_announcement_image_is_rejected(): void
    {
        $this->withHeaders(['Accept' => 'application/json'])->post('/api/admin/announcements/images', [
            'images' => [UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')],
        ])->assertUnprocessable()->assertJsonValidationErrors('images.0');
    }
}
