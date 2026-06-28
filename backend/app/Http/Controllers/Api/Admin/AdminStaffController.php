<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\KhachHang;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminStaffController extends Controller
{
    public function index(Request $request)
    {
        $query = KhachHang::query()
            ->where(function ($builder) {
                $builder->whereIn('role', ['staff', 'admin'])
                    ->orWhere('vai_tro', true);
            });

        if ($search = $request->input('search')) {
            $query->where(function ($builder) use ($search) {
                $builder->where('ten_kh', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('dien_thoai', 'like', "%{$search}%");
            });
        }

        if ($role = $request->input('role')) {
            $query->where(function ($builder) use ($role) {
                if ($role === 'admin') {
                    $builder->where('role', 'admin')->orWhere('vai_tro', true);
                    return;
                }

                $builder->where('role', $role)->where('vai_tro', false);
            });
        }

        $staff = $query->orderByDesc('ngay_tao')->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $staff->getCollection()->map(fn (KhachHang $user) => $this->formatStaff($user)),
            'meta' => [
                'total' => $staff->total(),
                'current_page' => $staff->currentPage(),
                'last_page' => $staff->lastPage(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:100', 'unique:khach_hang,email'],
            'phone' => ['nullable', 'string', 'max:11', 'unique:khach_hang,dien_thoai'],
            'password' => ['required', 'string', 'min:6'],
            'role' => ['required', Rule::in(['staff', 'admin'])],
            'active' => ['sometimes', 'boolean'],
        ]);

        $staff = KhachHang::create([
            'ten_kh' => trim($data['name']),
            'email' => trim($data['email']),
            'dien_thoai' => $data['phone'] ?: null,
            'mat_khau' => Hash::make($data['password']),
            'vai_tro' => $data['role'] === 'admin',
            'role' => $data['role'],
            'trang_thai' => $data['active'] ?? true,
            'ngay_tao' => now(),
        ]);

        \App\Models\GioHang::create(['ma_kh' => $staff->ma_kh]);

        return response()->json($this->formatStaff($staff), 201);
    }

    public function update(Request $request, string $id)
    {
        $staff = KhachHang::where('ma_kh', $id)
            ->where(function ($query) {
                $query->whereIn('role', ['staff', 'admin'])->orWhere('vai_tro', true);
            })
            ->firstOrFail();

        $data = $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:100', Rule::unique('khach_hang', 'email')->ignore($staff->ma_kh, 'ma_kh')],
            'phone' => ['nullable', 'string', 'max:11', Rule::unique('khach_hang', 'dien_thoai')->ignore($staff->ma_kh, 'ma_kh')],
            'password' => ['nullable', 'string', 'min:6'],
            'role' => ['required', Rule::in(['staff', 'admin'])],
            'active' => ['required', 'boolean'],
        ]);

        if ($request->user()->ma_kh === $staff->ma_kh && ($data['role'] !== 'admin' || !$data['active'])) {
            throw ValidationException::withMessages([
                'role' => 'Bạn không thể tự hạ quyền hoặc khóa tài khoản của chính mình.',
            ]);
        }

        $payload = [
            'ten_kh' => trim($data['name']),
            'email' => trim($data['email']),
            'dien_thoai' => $data['phone'] ?: null,
            'vai_tro' => $data['role'] === 'admin',
            'role' => $data['role'],
            'trang_thai' => $data['active'],
        ];

        if (!empty($data['password'])) {
            $payload['mat_khau'] = Hash::make($data['password']);
        }

        $staff->update($payload);

        return response()->json($this->formatStaff($staff->fresh()));
    }

    public function toggleStatus(Request $request, string $id)
    {
        $staff = KhachHang::where('ma_kh', $id)
            ->where(function ($query) {
                $query->whereIn('role', ['staff', 'admin'])->orWhere('vai_tro', true);
            })
            ->firstOrFail();

        if ($request->user()->ma_kh === $staff->ma_kh) {
            throw ValidationException::withMessages([
                'staff' => 'Bạn không thể tự khóa tài khoản của chính mình.',
            ]);
        }

        $staff->update(['trang_thai' => !$staff->trang_thai]);

        return response()->json($this->formatStaff($staff->fresh()));
    }

    private function formatStaff(KhachHang $user): array
    {
        return [
            'id' => $user->ma_kh,
            'name' => $user->ten_kh,
            'email' => $user->email,
            'phone' => $user->dien_thoai,
            'role' => $user->roleName(),
            'active' => (bool) $user->trang_thai,
            'created_at' => $user->ngay_tao?->toISOString(),
        ];
    }
}
