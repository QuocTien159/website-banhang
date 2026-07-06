<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class AuthStateTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_me_requires_authenticated_token(): void
    {
        $this->getJson('/api/auth/me')->assertUnauthorized();
    }

    public function test_me_returns_current_user_after_manual_login(): void
    {
        $user = KhachHang::where('email', 'user@example.com')->firstOrFail();
        Sanctum::actingAs($user);

        $this->getJson('/api/auth/me')
            ->assertOk()
            ->assertJsonPath('user.email', 'user@example.com');
    }

    public function test_authenticated_user_can_update_profile_and_password(): void
    {
        $user = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $user->update([
            'mat_khau' => Hash::make('oldpass123'),
            'dien_thoai' => '0909000000',
        ]);
        Sanctum::actingAs($user);

        $this->putJson('/api/auth/profile', [
            'name' => 'Nguyen Van Moi',
            'phone' => '0909888777',
            'current_password' => 'oldpass123',
            'new_password' => 'newpass123',
            'new_password_confirmation' => 'newpass123',
        ])
            ->assertOk()
            ->assertJsonPath('user.name', 'Nguyen Van Moi')
            ->assertJsonPath('user.phone', '0909888777');

        $user->refresh();
        $this->assertSame('Nguyen Van Moi', $user->ten_kh);
        $this->assertTrue(Hash::check('newpass123', $user->mat_khau));
    }
}
