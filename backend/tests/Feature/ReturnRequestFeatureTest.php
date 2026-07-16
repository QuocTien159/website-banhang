<?php

namespace Tests\Feature;

use App\Models\BienTheSanPham;
use App\Models\ChiTietDonHang;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\LichSuBienDongKho;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReturnRequestFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_customer_can_request_item_return_and_admin_receiving_updates_stock_and_revenue(): void
    {
        $customer = KhachHang::where('vai_tro', false)->firstOrFail();
        $admin = KhachHang::where('vai_tro', true)->firstOrFail();
        $variant = BienTheSanPham::where('so_luong_ton', '>=', 5)->firstOrFail();
        $stockBefore = $variant->so_luong_ton;

        $order = DonHang::create([
            'ma_kh' => $customer->ma_kh,
            'ngay_dat' => now(),
            'tong_tien' => 300000,
            'phuong_thuc_tt' => 'cod',
            'dia_chi_giao' => 'Khách Test | 0909123456 | TP HCM',
            'trang_thai' => 'delivered',
            'ngay_giao_thanh_cong' => now(),
        ]);
        ChiTietDonHang::create([
            'ma_dh' => $order->ma_dh,
            'ma_bien_the' => $variant->ma_bt,
            'so_luong' => 3,
            'don_gia' => 100000,
        ]);

        Sanctum::actingAs($customer);
        $returnId = $this->postJson('/api/returns', [
            'order_id' => $order->ma_dh,
            'reason' => 'Sản phẩm không phù hợp',
            'items' => [[
                'variant_id' => $variant->ma_bt,
                'quantity' => 1,
                'reason' => 'Không vừa size',
                'description' => 'Sản phẩm còn nguyên tem.',
                'images' => [],
            ]],
        ])
            ->assertCreated()
            ->assertJsonPath('return_request.status', 'pending')
            ->json('return_request.id');

        $this->assertSame($stockBefore, $variant->fresh()->so_luong_ton);

        Sanctum::actingAs($admin);
        $this->getJson('/api/admin/reports/summary')
            ->assertOk()
            ->assertJsonPath('stats.total_revenue', 300000);

        $this->putJson("/api/admin/returns/{$returnId}/status", ['status' => 'approved'])
            ->assertOk()
            ->assertJsonPath('return_request.status', 'approved');

        $this->putJson("/api/admin/returns/{$returnId}/status", ['status' => 'received'])
            ->assertOk()
            ->assertJsonPath('return_request.status', 'received');

        $this->assertSame($stockBefore + 1, $variant->fresh()->so_luong_ton);
        $this->assertDatabaseHas('lich_su_bien_dong_kho', [
            'ma_bien_the' => $variant->ma_bt,
            'loai_bien_dong' => 'return',
            'so_luong_thay_doi' => 1,
            'ma_tham_chieu' => $returnId,
        ]);

        $this->getJson('/api/admin/reports/summary')
            ->assertOk()
            ->assertJsonPath('stats.total_revenue', 200000);
    }

    public function test_return_request_rejects_invalid_order_or_quantity(): void
    {
        $customer = KhachHang::where('vai_tro', false)->firstOrFail();
        $variant = BienTheSanPham::firstOrFail();

        $order = DonHang::create([
            'ma_kh' => $customer->ma_kh,
            'ngay_dat' => now(),
            'tong_tien' => 100000,
            'phuong_thuc_tt' => 'cod',
            'dia_chi_giao' => 'Khách Test | 0909123456 | TP HCM',
            'trang_thai' => 'pending',
        ]);
        ChiTietDonHang::create([
            'ma_dh' => $order->ma_dh,
            'ma_bien_the' => $variant->ma_bt,
            'so_luong' => 1,
            'don_gia' => 100000,
        ]);

        Sanctum::actingAs($customer);
        $this->postJson('/api/returns', [
            'order_id' => $order->ma_dh,
            'reason' => 'Muốn trả',
            'items' => [[
                'variant_id' => $variant->ma_bt,
                'quantity' => 1,
                'reason' => 'Không phù hợp',
            ]],
        ])->assertUnprocessable();

        $order->update(['trang_thai' => 'delivered', 'ngay_giao_thanh_cong' => now()]);
        $this->postJson('/api/returns', [
            'order_id' => $order->ma_dh,
            'reason' => 'Muốn trả',
            'items' => [[
                'variant_id' => $variant->ma_bt,
                'quantity' => 2,
                'reason' => 'Không phù hợp',
            ]],
        ])->assertUnprocessable();
    }

    public function test_return_workflow_rejects_invalid_steps_and_never_restocks_twice(): void
    {
        $customer = KhachHang::where('vai_tro', false)->firstOrFail();
        $admin = KhachHang::where('vai_tro', true)->firstOrFail();
        $variant = BienTheSanPham::where('so_luong_ton', '>=', 5)->firstOrFail();
        $stockBefore = (int) $variant->so_luong_ton;

        $order = DonHang::create([
            'ma_kh' => $customer->ma_kh,
            'ngay_dat' => now()->subDay(),
            'ngay_giao_thanh_cong' => now(),
            'tam_tinh' => 200000,
            'tong_tien' => 200000,
            'phuong_thuc_tt' => 'cod',
            'dia_chi_giao' => 'Khách Test | 0909123456 | TP HCM',
            'trang_thai' => 'completed',
        ]);
        ChiTietDonHang::create([
            'ma_dh' => $order->ma_dh,
            'ma_bien_the' => $variant->ma_bt,
            'so_luong' => 2,
            'don_gia' => 100000,
        ]);

        Sanctum::actingAs($customer);
        $returnId = $this->postJson('/api/returns', [
            'order_id' => $order->ma_dh,
            'reason' => 'Sản phẩm không phù hợp',
            'items' => [[
                'variant_id' => $variant->ma_bt,
                'quantity' => 1,
                'reason' => 'Không vừa size',
            ]],
        ])->assertCreated()->json('return_request.id');

        Sanctum::actingAs($admin);
        $this->putJson("/api/admin/returns/{$returnId}/status", ['status' => 'completed'])->assertUnprocessable();
        $this->putJson("/api/admin/returns/{$returnId}/refund", ['refund_status' => 'refunding'])->assertUnprocessable();

        $this->putJson("/api/admin/returns/{$returnId}/status", ['status' => 'approved'])->assertOk();
        $receivedResponse = $this->putJson("/api/admin/returns/{$returnId}/status", ['status' => 'received'])
            ->assertOk();
        $this->assertNotNull($receivedResponse->json('return_request.received_at'));

        $this->assertSame($stockBefore + 1, (int) $variant->fresh()->so_luong_ton);
        $this->putJson("/api/admin/returns/{$returnId}/status", ['status' => 'received'])->assertUnprocessable();
        $this->assertSame($stockBefore + 1, (int) $variant->fresh()->so_luong_ton);
        $this->assertSame(1, LichSuBienDongKho::where('ma_tham_chieu', $returnId)->where('loai_bien_dong', 'return')->count());

        $this->putJson("/api/admin/returns/{$returnId}/refund", ['refund_status' => 'refunded'])->assertUnprocessable();
        $this->putJson("/api/admin/returns/{$returnId}/refund", ['refund_status' => 'refunding'])->assertOk();
        $this->putJson("/api/admin/returns/{$returnId}/refund", ['refund_status' => 'refunded'])->assertOk();

        $this->assertDatabaseHas('yeu_cau_tra_hang', [
            'ma_yeu_cau' => $returnId,
            'trang_thai_hoan_tien' => 'refunded',
        ]);
    }
}
