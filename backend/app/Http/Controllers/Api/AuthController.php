<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KhachHang;
use App\Support\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use GuzzleHttp\Client as GuzzleClient;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    public function redirectToGoogle(Request $request)
    {
        if (!$this->googleConfigured()) {
            return $this->redirectGoogleError('configuration');
        }

        $request->session()->put('google_login_return_to', $this->safeReturnTo($request->query('return_to')));

        return $this->googleDriver()
            ->scopes(['openid', 'profile', 'email'])
            ->with(['access_type' => 'online', 'prompt' => 'select_account'])
            ->redirect();
    }

    public function handleGoogleCallback(Request $request)
    {
        $providerError = $request->query('error');
        if ($providerError) {
            Log::notice('Google OAuth provider rejected the authorization request.', [
                'error' => $providerError,
            ]);

            return $this->redirectGoogleError($providerError === 'access_denied' ? 'cancelled' : 'provider');
        }
        if (!$this->googleConfigured()) {
            return $this->redirectGoogleError('configuration');
        }

        try {
            $googleUser = $this->googleDriver()->user();
        } catch (\Throwable $exception) {
            Log::warning('Google OAuth callback failed while retrieving the provider profile.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            return $this->redirectGoogleError('provider');
        }

        $email = mb_strtolower(trim((string) $googleUser->getEmail()));
        $googleId = (string) $googleUser->getId();
        $emailVerified = (bool) ($googleUser->user['email_verified'] ?? false);
        if (!$googleId || !filter_var($email, FILTER_VALIDATE_EMAIL) || !$emailVerified) {
            return $this->redirectGoogleError('email_not_verified');
        }

        try {
            $user = DB::transaction(function () use ($googleId, $email, $googleUser) {
                $user = KhachHang::where('google_id', $googleId)->lockForUpdate()->first();
                if ($user && mb_strtolower($user->email) !== $email) {
                    throw ValidationException::withMessages(['google' => ['Tài khoản Google không khớp với email đã liên kết.']]);
                }

                if (!$user) {
                    $user = KhachHang::where('email', $email)->lockForUpdate()->first();
                    if ($user) {
                        if ($user->google_id && $user->google_id !== $googleId) {
                            throw ValidationException::withMessages(['google' => ['Email này đã liên kết với một tài khoản Google khác.']]);
                        }
                        $user->update([
                            'google_id' => $googleId,
                            'google_avatar' => $googleUser->getAvatar(),
                            'google_linked_at' => now(),
                        ]);
                    } else {
                        $user = KhachHang::create([
                            'ten_kh' => Str::limit(trim((string) $googleUser->getName()) ?: Str::before($email, '@'), 50, ''),
                            'email' => $email,
                            'google_id' => $googleId,
                            'google_avatar' => $googleUser->getAvatar(),
                            'google_linked_at' => now(),
                            'mat_khau' => Hash::make(Str::random(64)),
                            'vai_tro' => false,
                            'role' => UserRole::CUSTOMER,
                            'trang_thai' => true,
                            'ngay_tao' => now(),
                        ]);
                        \App\Models\GioHang::create(['ma_kh' => $user->ma_kh]);
                    }
                }

                if (!$user->trang_thai) {
                    throw ValidationException::withMessages(['google' => ['Tài khoản của bạn đã bị khóa.']]);
                }

                // Existing staff/admin accounts keep their current role; only new OAuth accounts are customers.
                $user->tokens()->delete();
                $token = $user->createToken('auth_token')->plainTextToken;
                return compact('user', 'token');
            });
        } catch (ValidationException $exception) {
            return $this->redirectGoogleError($exception->errors()['google'][0] === 'Tài khoản của bạn đã bị khóa.' ? 'account_locked' : 'account_mismatch');
        } catch (\Throwable $exception) {
            Log::error('Google OAuth user synchronization failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return $this->redirectGoogleError('account_sync');
        }

        $code = Str::random(64);
        Cache::put('google_oauth_exchange:'.hash('sha256', $code), [
            'token' => $user['token'],
            'user' => $this->formatUser($user['user']),
            'return_to' => $this->safeReturnTo($request->session()->pull('google_login_return_to', '/')),
        ], now()->addMinutes(5));

        return redirect(rtrim(config('services.google.frontend_url'), '/').'/auth/google/callback?code='.urlencode($code));
    }

    public function exchangeGoogleCode(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'size:64']]);
        $payload = Cache::pull('google_oauth_exchange:'.hash('sha256', $data['code']));
        if (!$payload) {
            return response()->json(['message' => 'Phiên đăng nhập Google đã hết hạn hoặc đã được sử dụng.'], 422);
        }

        return response()->json($payload);
    }
    public function register(Request $request)
    {
        $data = $request->validate([
            'ten_kh'    => 'required|string|max:50',
            'email'     => 'required|email|max:100|unique:khach_hang,email',
            'mat_khau'  => 'required|string|min:6|confirmed',
            'dien_thoai'=> 'nullable|string|size:10,11|unique:khach_hang,dien_thoai',
        ]);

        $user = KhachHang::create([
            'ten_kh'     => $data['ten_kh'],
            'email'      => $data['email'],
            'mat_khau'   => Hash::make($data['mat_khau']),
            'dien_thoai' => $data['dien_thoai'] ?? null,
            'vai_tro'    => false,
            'role'        => UserRole::CUSTOMER,
            'trang_thai' => true,
            'ngay_tao'   => now(),
        ]);

        // Create cart for user
        \App\Models\GioHang::create(['ma_kh' => $user->ma_kh]);

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $this->formatUser($user),
            'token' => $token,
        ], 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate([
            'email'    => 'required|email',
            'mat_khau' => 'required|string',
        ]);

        $user = KhachHang::where('email', $data['email'])->first();

        if (!$user || !Hash::check($data['mat_khau'], $user->mat_khau)) {
            throw ValidationException::withMessages([
                'email' => ['Email hoặc mật khẩu không đúng.'],
            ]);
        }

        if (!$user->trang_thai) {
            return response()->json([
                'message' => 'Tài khoản của bạn đã bị khóa. Vui lòng liên hệ hỗ trợ.',
            ], 403);
        }

        // Revoke old tokens
        $user->tokens()->delete();

        $token = $user->createToken('auth_token')->plainTextToken;

        return response()->json([
            'user'  => $this->formatUser($user),
            'token' => $token,
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(['message' => 'Đã đăng xuất thành công.']);
    }

    public function me(Request $request)
    {
        return response()->json(['user' => $this->formatUser($request->user())]);
    }

    public function updateProfile(Request $request)
    {
        /** @var KhachHang $user */
        $user = $request->user();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'phone' => ['nullable', 'string', 'regex:/^[0-9]{10,11}$/', 'unique:khach_hang,dien_thoai,'.$user->ma_kh.',ma_kh'],
            'current_password' => ['nullable', 'required_with:new_password', 'string'],
            'new_password' => ['nullable', 'string', 'min:6', 'confirmed'],
        ]);

        if (!empty($data['new_password'])) {
            if (!Hash::check($data['current_password'] ?? '', $user->mat_khau)) {
                throw ValidationException::withMessages([
                    'current_password' => ['Mat khau hien tai khong dung.'],
                ]);
            }

            $user->mat_khau = Hash::make($data['new_password']);
        }

        $user->ten_kh = trim($data['name']);
        $user->dien_thoai = $data['phone'] ?? null;
        $user->save();

        return response()->json([
            'message' => 'Profile updated.',
            'user' => $this->formatUser($user->fresh()),
        ]);
    }

    private function formatUser(KhachHang $user): array
    {
        return [
            'id'        => $user->ma_kh,
            'name'      => $user->ten_kh,
            'email'     => $user->email,
            'phone'     => $user->dien_thoai,
            'role'      => $user->roleName(),
            'status'    => $user->trang_thai,
            'join_date' => $user->ngay_tao?->format('Y-m-d'),
        ];
    }

    private function googleConfigured(): bool
    {
        return filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect'));
    }

    private function googleDriver()
    {
        $driver = Socialite::driver('google');
        $caBundle = config('services.google.ca_bundle');

        if (is_string($caBundle) && is_file($caBundle)) {
            $driver->setHttpClient(new GuzzleClient(['verify' => $caBundle]));
        }

        return $driver;
    }

    private function safeReturnTo(?string $value): string
    {
        return is_string($value) && str_starts_with($value, '/') && !str_starts_with($value, '//') ? $value : '/';
    }

    private function redirectGoogleError(string $error): \Illuminate\Http\RedirectResponse
    {
        return redirect(rtrim(config('services.google.frontend_url'), '/').'/auth/google/callback?error='.urlencode($error));
    }
}
