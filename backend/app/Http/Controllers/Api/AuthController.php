<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\KhachHang;
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

    private function formatUser(KhachHang $user): array
    {
        return [
            'id'        => $user->ma_kh,
            'name'      => $user->ten_kh,
            'email'     => $user->email,
            'phone'     => $user->dien_thoai,
            'role'      => $user->vai_tro ? 'admin' : 'user',
            'status'    => $user->trang_thai,
            'join_date' => $user->ngay_tao?->format('Y-m-d'),
        ];
    }
}
