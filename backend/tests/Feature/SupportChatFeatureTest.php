<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use App\Support\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class SupportChatFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_customer_can_create_one_persistent_support_conversation(): void
    {
        $customer = KhachHang::where('email', 'user@example.com')->firstOrFail();
        Sanctum::actingAs($customer);

        $this->postJson('/api/support/messages', ['content' => 'Tôi cần tư vấn size áo.'])
            ->assertCreated()
            ->assertJsonPath('data.status', 'waiting')
            ->assertJsonCount(1, 'data.messages');

        $this->postJson('/api/support/messages', ['content' => 'Tôi cao 1m75.'])
            ->assertCreated()
            ->assertJsonCount(2, 'data.messages');

        $this->assertDatabaseCount('cuoc_tro_chuyen_ho_tro', 1);
        $this->assertDatabaseCount('tin_nhan_ho_tro', 2);
    }

    public function test_only_assigned_staff_can_reply_to_a_conversation(): void
    {
        $customer = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $firstStaff = $this->makeStaff('first-staff@example.com');
        $secondStaff = $this->makeStaff('second-staff@example.com');

        Sanctum::actingAs($customer);
        $conversationId = $this->postJson('/api/support/messages', ['content' => 'Tư vấn giúp tôi.'])
            ->json('data.id');

        Sanctum::actingAs($firstStaff);
        $this->postJson("/api/admin/support/conversations/{$conversationId}/claim")->assertOk();

        Sanctum::actingAs($secondStaff);
        $this->postJson("/api/admin/support/conversations/{$conversationId}/messages", ['content' => 'Tôi không được phép trả lời.'])
            ->assertUnprocessable();

        Sanctum::actingAs($firstStaff);
        $this->postJson("/api/admin/support/conversations/{$conversationId}/messages", ['content' => 'Cửa hàng sẽ hỗ trợ bạn.'])
            ->assertCreated()
            ->assertJsonPath('data.messages.1.sender_role', UserRole::STAFF);
    }

    private function makeStaff(string $email): KhachHang
    {
        return KhachHang::create([
            'ten_kh' => 'Nhân viên hỗ trợ',
            'email' => $email,
            'mat_khau' => bcrypt('password'),
            'vai_tro' => false,
            'role' => UserRole::STAFF,
            'trang_thai' => true,
            'ngay_tao' => now(),
        ]);
    }
}
