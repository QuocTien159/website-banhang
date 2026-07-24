<?php

namespace Tests\Feature;

use App\Models\KhachHang;
use App\Notifications\CustomerResetPasswordNotification;
use App\Support\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetFeatureTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_customer_can_request_a_password_reset_link_without_exposing_account_existence(): void
    {
        $customer = KhachHang::where('email', 'user@example.com')->firstOrFail();
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => $customer->email])
            ->assertOk()
            ->assertJsonPath('message', 'Nếu email hợp lệ thuộc một tài khoản đang hoạt động, hướng dẫn đặt lại mật khẩu đã được gửi.');

        Notification::assertSentTo($customer, CustomerResetPasswordNotification::class);

        $this->postJson('/api/auth/forgot-password', ['email' => 'missing@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'Nếu email hợp lệ thuộc một tài khoản đang hoạt động, hướng dẫn đặt lại mật khẩu đã được gửi.');

        Notification::assertSentToTimes($customer, CustomerResetPasswordNotification::class, 1);
    }

    public function test_customer_can_reset_password_and_old_tokens_are_revoked(): void
    {
        $customer = KhachHang::where('email', 'user@example.com')->firstOrFail();
        $customer->createToken('existing-session');
        Notification::fake();
        $token = null;

        $this->postJson('/api/auth/forgot-password', ['email' => $customer->email])->assertOk();

        Notification::assertSentTo(
            $customer,
            CustomerResetPasswordNotification::class,
            function (CustomerResetPasswordNotification $notification) use (&$token): bool {
                $token = $notification->token;
                return true;
            }
        );

        $this->assertNotNull($token);

        $this->postJson('/api/auth/reset-password', [
            'email' => $customer->email,
            'token' => $token,
            'mat_khau' => 'new-password-123',
            'mat_khau_confirmation' => 'new-password-123',
        ])
            ->assertOk()
            ->assertJsonPath('message', 'Mật khẩu đã được đặt lại. Vui lòng đăng nhập lại.');

        $customer->refresh();
        $this->assertTrue(Hash::check('new-password-123', $customer->mat_khau));
        $this->assertDatabaseCount('personal_access_tokens', 0);

        $this->postJson('/api/auth/login', [
            'email' => $customer->email,
            'mat_khau' => 'new-password-123',
        ])->assertOk();
    }

    public function test_staff_cannot_request_or_use_customer_password_reset(): void
    {
        $staff = KhachHang::create([
            'ten_kh' => 'Nhân viên thử nghiệm',
            'email' => 'staff-reset@example.com',
            'mat_khau' => Hash::make('staff-password'),
            'vai_tro' => false,
            'role' => UserRole::STAFF,
            'trang_thai' => true,
            'ngay_tao' => now(),
        ]);
        Notification::fake();

        $this->postJson('/api/auth/forgot-password', ['email' => $staff->email])->assertOk();
        Notification::assertNothingSent();

        $this->postJson('/api/auth/reset-password', [
            'email' => $staff->email,
            'token' => str_repeat('a', 64),
            'mat_khau' => 'new-password-123',
            'mat_khau_confirmation' => 'new-password-123',
        ])->assertUnprocessable();
    }
}
