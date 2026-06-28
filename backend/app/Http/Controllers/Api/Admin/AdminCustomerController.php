<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\KhachHang;
use Illuminate\Http\Request;

class AdminCustomerController extends Controller
{
    /** GET /api/admin/customers */
    public function index(Request $request)
    {
        $query = KhachHang::withCount('donHangs')
            ->where('role', 'customer')
            ->where('vai_tro', false);

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ten_kh', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('dien_thoai', 'like', "%{$search}%");
            });
        }
        if ($status = $request->input('status')) {
            $query->where('trang_thai', $status === 'active' ? true : false);
        }

        $customers = $query->orderBy('ngay_tao', 'desc')->paginate(20);

        return response()->json([
            'data' => $customers->getCollection()->map(fn($kh) => [
                'id'          => $kh->ma_kh,
                'name'        => $kh->ten_kh,
                'email'       => $kh->email,
                'phone'       => $kh->dien_thoai,
                'status'      => $kh->trang_thai ? 'active' : 'blocked',
                'join_date'   => $kh->ngay_tao?->format('d/m/Y'),
                'order_count' => $kh->don_hangs_count,
                'total_spent' => (float)\App\Models\DonHang::where('ma_kh', $kh->ma_kh)
                    ->where('trang_thai', 'delivered')
                    ->sum('tong_tien'),
            ]),
            'meta' => [
                'total' => $customers->total(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
            ],
        ]);
    }

    /** PUT /api/admin/customers/{id}/status — Khóa/mở tài khoản */
    public function toggleStatus(Request $request, string $id)
    {
        $customer = KhachHang::where('ma_kh', $id)
            ->where('role', 'customer')
            ->where('vai_tro', false)
            ->firstOrFail();
        $customer->update(['trang_thai' => !$customer->trang_thai]);

        $action = $customer->trang_thai ? 'mở khóa' : 'khóa';
        return response()->json([
            'message' => "Đã {$action} tài khoản thành công.",
            'status'  => $customer->trang_thai ? 'active' : 'blocked',
        ]);
    }
}
