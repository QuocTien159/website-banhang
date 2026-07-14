<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use App\Support\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as GoogleUser;
use Mockery;
use Tests\TestCase;

class GoogleOAuthTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.google.client_id' => 'test-client',
            'services.google.client_secret' => 'test-secret',
            'services.google.redirect' => 'http://localhost:8000/api/auth/google/callback',
            'services.google.frontend_url' => 'http://localhost:5173',
            'services.google.ca_bundle' => null,
        ]);
    }

    public function test_verified_google_user_creates_exactly_one_customer_and_can_log_in_again(): void
    {
        $first = $this->completeCallback('google-new-1', 'new-google@example.com', true, 'Khách Google');
        $this->assertSame(UserRole::CUSTOMER, $first['user']['role']);
        $this->assertDatabaseHas('khach_hang', ['email' => 'new-google@example.com', 'google_id' => 'google-new-1', 'role' => UserRole::CUSTOMER]);

        $second = $this->completeCallback('google-new-1', 'new-google@example.com', true, 'Khách Google');
        $this->assertSame($first['user']['id'], $second['user']['id']);
        $this->assertSame(1, KhachHang::where('email', 'new-google@example.com')->count());
    }

    public function test_google_email_links_existing_customer_without_duplicate_account(): void
    {
        $customer = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $result = $this->completeCallback('google-linked-1', $customer->email, true, 'Khách Demo');

        $this->assertSame($customer->ma_kh, $result['user']['id']);
        $this->assertSame(1, KhachHang::where('email', $customer->email)->count());
        $this->assertSame('google-linked-1', $customer->fresh()->google_id);
    }

    public function test_google_login_never_changes_existing_staff_or_admin_role(): void
    {
        $staff = KhachHang::create([
            'ten_kh' => 'Nhân viên Google', 'email' => 'staff-google@example.com',
            'mat_khau' => Hash::make('staff123'), 'dien_thoai' => '0914444444',
            'vai_tro' => false, 'role' => UserRole::STAFF, 'trang_thai' => true, 'ngay_tao' => now(),
        ]);

        $result = $this->completeCallback('google-staff-1', $staff->email, true, 'Nhân viên Google');
        $this->assertSame(UserRole::STAFF, $result['user']['role']);
        $this->assertSame(UserRole::STAFF, $staff->fresh()->role);
        $this->assertSame('google-staff-1', $staff->fresh()->google_id);
    }

    public function test_unverified_or_cancelled_google_login_returns_frontend_error(): void
    {
        $response = $this->googleCallback('google-unverified', 'unverified@example.com', false, 'Chưa xác minh');
        $response->assertRedirect('http://localhost:5173/auth/google/callback?error=email_not_verified');

        $this->get('/api/auth/google/callback?error=access_denied')
            ->assertRedirect('http://localhost:5173/auth/google/callback?error=cancelled');
    }

    private function completeCallback(string $id, string $email, bool $verified, string $name): array
    {
        $response = $this->googleCallback($id, $email, $verified, $name)->assertRedirect();
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        return $this->postJson('/api/auth/google/exchange', ['code' => $query['code']])->assertOk()->json();
    }

    private function googleCallback(string $id, string $email, bool $verified, string $name)
    {
        $googleUser = new GoogleUser;
        $googleUser->id = $id;
        $googleUser->email = $email;
        $googleUser->name = $name;
        $googleUser->avatar = 'https://example.test/avatar.jpg';
        $googleUser->user = ['email_verified' => $verified];

        $provider = Mockery::mock();
        $provider->shouldReceive('user')->once()->andReturn($googleUser);
        Socialite::shouldReceive('driver')->with('google')->once()->andReturn($provider);

        return $this->get('/api/auth/google/callback');
    }
}
