<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Models\BienTheSanPham;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends Controller
{
    /** GET /api/admin/orders */
    public function index(Request $request)
    {
        $query = DonHang::with(['khachHang', 'chiTiets']);

        if ($status = $request->input('status')) {
            $query->where('trang_thai', $status);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ma_dh', 'like', "%{$search}%")
                  ->orWhere('dia_chi_giao', 'like', "%{$search}%")
                  ->orWhereHas('khachHang', fn($kh) => $kh->where('ten_kh', 'like', "%{$search}%"));
            });
        }

        $orders = $query->orderBy('ngay_dat', 'desc')->paginate(20);

        return response()->json([
            'data' => $orders->getCollection()->map(fn($dh) => [
                'id'          => $dh->ma_dh,
                'customer'    => $dh->khachHang?->ten_kh ?? 'N/A',
                'customer_id' => $dh->ma_kh,
                'date'        => $dh->ngay_dat?->format('d/m/Y H:i'),
                'total'       => (float)$dh->tong_tien,
                'status'      => $dh->trang_thai,
                'payment'     => $dh->phuong_thuc_tt,
                'address'     => $dh->dia_chi_giao,
                'item_count'  => $dh->chiTiets->count(),
            ]),
            'meta' => [
                'total' => $orders->total(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ],
            'stats' => $this->getStats(),
        ]);
    }

    /** PUT /api/admin/orders/{id}/status */
    public function updateStatus(Request $request, string $id)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,confirmed,shipping,delivered,cancelled',
        ]);

        $order = DonHang::findOrFail($id);
        $oldStatus = $order->trang_thai;

        // If cancelling a delivered order, restore stock
        if ($data['status'] === 'cancelled' && $oldStatus !== 'cancelled') {
            DB::transaction(function () use ($order, $data) {
                foreach ($order->chiTiets as $ct) {
                    BienTheSanPham::where('ma_bt', $ct->ma_bien_the)
                        ->increment('so_luong_ton', $ct->so_luong);
                }
                $order->update(['trang_thai' => $data['status']]);
            });
        } else {
            $order->update(['trang_thai' => $data['status']]);
        }

        return response()->json(['message' => 'Đã cập nhật trạng thái đơn hàng.', 'status' => $data['status']]);
    }

    private function getStats(): array
    {
        return [
            'pending'   => DonHang::where('trang_thai', 'pending')->count(),
            'confirmed' => DonHang::where('trang_thai', 'confirmed')->count(),
            'shipping'  => DonHang::where('trang_thai', 'shipping')->count(),
            'delivered' => DonHang::where('trang_thai', 'delivered')->count(),
            'cancelled' => DonHang::where('trang_thai', 'cancelled')->count(),
        ];
    }
}
