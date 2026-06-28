<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
}
