<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KhachHang;
use App\Support\UserRole;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
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
}
