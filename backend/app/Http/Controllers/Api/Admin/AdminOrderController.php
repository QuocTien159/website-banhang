<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminOrderController extends Controller
{
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
                    ->orWhere('noi_dung_chuyen_khoan', 'like', "%{$search}%")
                    ->orWhereHas('khachHang', fn ($customer) => $customer->where('ten_kh', 'like', "%{$search}%"));
            });
        }

        $orders = $query->orderBy('ngay_dat', 'desc')->paginate(20);

        return response()->json([
            'data' => $orders->getCollection()->map(function (DonHang $order) {
                $itemCount = $order->chiTiets->count();
                $totalQuantity = (int) $order->chiTiets->sum('so_luong');

                return [
                    'id' => $order->ma_dh,
                    'customer' => $order->khachHang?->ten_kh ?? 'N/A',
                    'customer_name' => $order->khachHang?->ten_kh ?? 'N/A',
                    'customer_email' => $order->khachHang?->email,
                    'customer_id' => $order->ma_kh,
                    'date' => $order->ngay_dat?->format('d/m/Y H:i'),
                    'created_at' => $order->ngay_dat?->toISOString(),
                    'created_at_formatted' => $order->ngay_dat?->format('d/m/Y H:i'),
                    'total' => (float) $order->tong_tien,
                    'status' => $order->trang_thai,
                    'payment' => $order->phuong_thuc_tt,
                    'payment_method' => $order->phuong_thuc_tt,
                    'payment_status' => $order->trang_thai_thanh_toan,
                    'bank_transfer_content' => $order->noi_dung_chuyen_khoan,
                    'customer_paid_at' => $order->khach_bao_da_chuyen_at?->toISOString(),
                    'payment_confirmed_at' => $order->thanh_toan_xac_nhan_at?->toISOString(),
                    'shipping_fee' => (float) ($order->phi_van_chuyen ?? 0),
                    'shipping_area_type' => $order->loai_khu_vuc_giao,
                    'address' => $order->dia_chi_giao,
                    'item_count' => $itemCount,
                    'items_count' => $totalQuantity,
                    'total_quantity' => $totalQuantity,
                ];
            }),
            'meta' => [
                'total' => $orders->total(),
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
            ],
            'stats' => $this->getStats(),
        ]);
    }

    public function updateStatus(Request $request, string $id, InventoryService $inventoryService)
    {
        $data = $request->validate([
            'status' => 'required|in:pending,confirmed,shipping,delivered,cancelled',
        ]);

        $order = DonHang::findOrFail($id);
        $oldStatus = $order->trang_thai;

        if ($data['status'] === 'cancelled' && $oldStatus !== 'cancelled') {
            DB::transaction(function () use ($order, $data, $request, $inventoryService) {
                foreach ($order->chiTiets as $item) {
                    $inventoryService->changeStock(
                        $item->ma_bien_the,
                        (int) $item->so_luong,
                        'order_cancelled',
                        $request->user()->ma_kh,
                        'Hoàn tồn kho khi hủy đơn',
                        $order->ma_dh
                    );
                }
                $order->update(['trang_thai' => $data['status']]);
            });
        } else {
            $order->update(['trang_thai' => $data['status']]);
        }

        return response()->json(['message' => 'Đã cập nhật trạng thái đơn hàng.', 'status' => $data['status']]);
    }

    public function updatePaymentStatus(Request $request, string $id)
    {
        $data = $request->validate([
            'payment_status' => 'required|in:paid,payment_not_received',
        ]);

        $order = DonHang::findOrFail($id);
        if ($order->phuong_thuc_tt !== 'bank_transfer_qr') {
            return response()->json(['message' => 'Đơn hàng này không dùng thanh toán QR chuyển khoản.'], 422);
        }

        $payload = ['trang_thai_thanh_toan' => $data['payment_status']];
        if ($data['payment_status'] === 'paid') {
            $payload['thanh_toan_xac_nhan_at'] = now();
            $payload['thanh_toan_xac_nhan_boi'] = $request->user()->ma_kh;
        } else {
            $payload['thanh_toan_xac_nhan_at'] = null;
            $payload['thanh_toan_xac_nhan_boi'] = null;
        }

        $order->update($payload);

        return response()->json([
            'message' => $data['payment_status'] === 'paid'
                ? 'Đã xác nhận đơn hàng đã thanh toán.'
                : 'Đã đánh dấu chưa nhận được tiền.',
            'payment_status' => $order->fresh()->trang_thai_thanh_toan,
        ]);
    }

    private function getStats(): array
    {
        return [
            'pending' => DonHang::where('trang_thai', 'pending')->count(),
            'confirmed' => DonHang::where('trang_thai', 'confirmed')->count(),
            'shipping' => DonHang::where('trang_thai', 'shipping')->count(),
            'delivered' => DonHang::where('trang_thai', 'delivered')->count(),
            'cancelled' => DonHang::where('trang_thai', 'cancelled')->count(),
        ];
    }
}
