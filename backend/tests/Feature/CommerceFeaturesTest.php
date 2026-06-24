<?php

namespace Tests\Feature;

use App\Models\BienTheSanPham;
use App\Models\DanhGia;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\MaKhuyenMai;
use App\Models\SanPham;
use App\Models\ThongBao;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class CommerceFeaturesTest extends TestCase
{
    use RefreshDatabase;
    protected bool $seed = true;

    public function test_wishlist_is_persisted_and_does_not_duplicate(): void
    {
        $user = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $product = SanPham::firstOrFail();
        Sanctum::actingAs($user);

        $this->postJson("/api/wishlist/{$product->ma_sp}")->assertOk()->assertJsonPath('wishlisted', true);
        $this->getJson('/api/wishlist')->assertOk()->assertJsonCount(1);
        $this->getJson("/api/wishlist/{$product->ma_sp}/status")->assertJsonPath('wishlisted', true);
        $this->postJson("/api/wishlist/{$product->ma_sp}")->assertJsonPath('wishlisted', false);
        $this->assertDatabaseCount('danh_sach_yeu_thich', 0);
    }

    public function test_coupon_is_used_once_even_after_order_is_cancelled(): void
    {
        $user = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $admin = KhachHang::where('vai_tro', true)->firstOrFail();
        $variant = BienTheSanPham::where('so_luong_ton', '>', 2)->firstOrFail();
        Sanctum::actingAs($user);
        $this->postJson('/api/cart/items', ['variant_id' => $variant->ma_bt, 'quantity' => 2])->assertOk();
        $this->postJson('/api/promotions/validate', ['code' => 'SPORT20'])->assertOk();
        $order = $this->postJson('/api/orders', [
            'ten_nguoi_nhan' => 'Demo', 'so_dien_thoai' => '0909123456', 'dia_chi_giao' => 'TP HCM',
            'phuong_thuc_tt' => 'cod', 'coupon_code' => 'SPORT20',
        ])->assertCreated()->json('order');
        $this->assertDatabaseHas('lich_su_khuyen_mai', ['ma_kh' => $user->ma_kh, 'ma_dh' => $order['id']]);

        Sanctum::actingAs($admin);
        $this->putJson("/api/admin/orders/{$order['id']}/status", ['status' => 'cancelled'])->assertOk();
        Sanctum::actingAs($user);
        $this->postJson('/api/cart/items', ['variant_id' => $variant->ma_bt, 'quantity' => 1])->assertOk();
        $this->postJson('/api/promotions/validate', ['code' => 'SPORT20'])->assertUnprocessable();
    }

    public function test_review_requires_delivered_order_and_admin_approval(): void
    {
        $user = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $admin = KhachHang::where('vai_tro', true)->firstOrFail();
        $variant = BienTheSanPham::firstOrFail();
        $order = DonHang::create(['ma_kh' => $user->ma_kh, 'ngay_dat' => now(), 'tong_tien' => 100000, 'phuong_thuc_tt' => 'cod', 'dia_chi_giao' => 'TP HCM', 'trang_thai' => 'pending']);
        DB::table('chi_tiet_don_hang')->insert(['ma_dh' => $order->ma_dh, 'ma_bien_the' => $variant->ma_bt, 'so_luong' => 1, 'don_gia' => 100000]);
        Sanctum::actingAs($user);
        $payload = ['order_id' => $order->ma_dh, 'product_id' => $variant->ma_sp, 'rating' => 5, 'comment' => 'Sản phẩm rất tốt và đúng mô tả.', 'images' => []];
        $this->postJson('/api/reviews', $payload)->assertForbidden();
        $order->update(['trang_thai' => 'delivered']);
        $reviewId = $this->postJson('/api/reviews', $payload)->assertCreated()->json('review.id');
        $this->getJson("/api/products/{$variant->ma_sp}/reviews")->assertJsonPath('meta.total', 0);
        Sanctum::actingAs($admin);
        $this->putJson("/api/admin/reviews/{$reviewId}/status", ['status' => 'approved'])->assertOk();
        $this->putJson("/api/admin/reviews/{$reviewId}/reply", ['reply' => 'Cảm ơn bạn đã đánh giá.'])->assertOk();
        Sanctum::actingAs($user);
        $this->getJson("/api/products/{$variant->ma_sp}/reviews")->assertJsonPath('meta.total', 1)->assertJsonPath('data.0.admin_reply', 'Cảm ơn bạn đã đánh giá.');
    }

    public function test_only_published_announcements_are_public(): void
    {
        ThongBao::create(['tieu_de' => 'Nháp', 'noi_dung' => 'Ẩn', 'loai' => 'general', 'trang_thai' => 'draft', 'ngay_tao' => now()]);
        $this->getJson('/api/announcements')->assertOk()->assertJsonMissing(['title' => 'Nháp']);
        $this->assertGreaterThanOrEqual(1, count($this->getJson('/api/announcements')->json()));
    }
}
