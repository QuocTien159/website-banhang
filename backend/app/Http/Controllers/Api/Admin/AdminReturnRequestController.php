<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ReturnRequestController;
use App\Models\ChiTietTraHang;
use App\Models\YeuCauTraHang;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminReturnRequestController extends ReturnRequestController
{
    public function index(Request $request)
    {
        $query = YeuCauTraHang::with(['donHang.khachHang', 'donHang.chiTiets', 'khachHang', 'chiTiets.bienThe', 'hinhAnhs']);

        if ($status = $request->input('status')) {
            $query->where('trang_thai', $status);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ma_yeu_cau', 'like', "%{$search}%")
                    ->orWhere('ma_dh', 'like', "%{$search}%")
                    ->orWhereHas('khachHang', fn ($customer) => $customer->where('ten_kh', 'like', "%{$search}%"));
            });
        }

        return response()->json($query->orderByDesc('ngay_yeu_cau')->get()->map(fn (YeuCauTraHang $return) => $this->formatAdmin($return)));
    }

    public function show(Request $request, string $id)
    {
        $return = YeuCauTraHang::with(['donHang.khachHang', 'donHang.chiTiets', 'khachHang', 'chiTiets.bienThe', 'hinhAnhs'])
            ->findOrFail($id);

        return response()->json($this->formatAdmin($return, true));
    }

    public function updateStatus(Request $request, string $id, InventoryService $inventoryService)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'received', 'completed'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'reject_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:1000'],
            'refund_status' => ['nullable', Rule::in(['not_refunded', 'refunding', 'refunded', 'refund_failed'])],
        ]);

        $return = YeuCauTraHang::with('chiTiets')->findOrFail($id);

        DB::transaction(function () use ($return, $data, $request, $inventoryService) {
            if ($data['status'] === 'received' && !$return->da_nhap_kho) {
                foreach ($return->chiTiets as $item) {
                    $inventoryService->changeStock(
                        $item->ma_bien_the,
                        (int) $item->so_luong,
                        'return',
                        $request->user()->ma_kh,
                        'Nhập lại kho từ yêu cầu trả hàng',
                        $return->ma_yeu_cau
                    );
                }
                $return->da_nhap_kho = true;
            }

            if ($data['status'] === 'rejected' && $return->trang_thai !== 'pending') {
                throw ValidationException::withMessages(['status' => 'Chỉ nên từ chối yêu cầu đang chờ xử lý.']);
            }

            $return->trang_thai = $data['status'];
            $return->ghi_chu_admin = $data['admin_note'] ?? $return->ghi_chu_admin;
            $return->ly_do_tu_choi = $data['reject_reason'] ?? $return->ly_do_tu_choi;
            if (!empty($data['refund_status'])) {
                $return->trang_thai_hoan_tien = $data['refund_status'];
            }
            $return->ngay_cap_nhat = now();
            $return->save();
        });

        return response()->json([
            'message' => 'Đã cập nhật yêu cầu trả hàng.',
            'return_request' => $this->formatAdmin($return->fresh(['donHang.khachHang', 'donHang.chiTiets', 'khachHang', 'chiTiets.bienThe', 'hinhAnhs']), true),
        ]);
    }

    public function updateRefund(Request $request, string $id)
    {
        $data = $request->validate([
            'refund_status' => ['required', Rule::in(['not_refunded', 'refunding', 'refunded', 'refund_failed'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $return = YeuCauTraHang::findOrFail($id);
        $return->update([
            'trang_thai_hoan_tien' => $data['refund_status'],
            'ghi_chu_admin' => $data['admin_note'] ?? $return->ghi_chu_admin,
            'ngay_cap_nhat' => now(),
        ]);

        return response()->json(['message' => 'Đã cập nhật trạng thái hoàn tiền thủ công.']);
    }

    private function formatAdmin(YeuCauTraHang $return, bool $detail = false): array
    {
        $base = $this->format($return, $detail);
        return array_merge($base, [
            'customer' => $return->khachHang?->ten_kh ?? $return->donHang?->khachHang?->ten_kh,
            'customer_id' => $return->ma_kh,
            'total_refund_estimate' => $return->chiTiets->sum(function (ChiTietTraHang $item) use ($return) {
                $orderItem = $return->donHang?->chiTiets?->firstWhere('ma_bien_the', $item->ma_bien_the);
                return (float) ($orderItem?->don_gia ?? 0) * $item->so_luong;
            }),
        ]);
    }
}
