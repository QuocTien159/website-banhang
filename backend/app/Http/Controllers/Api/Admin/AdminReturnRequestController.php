<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Api\ReturnRequestController;
use App\Models\ChiTietTraHang;
use App\Models\LichSuXuLyTraHang;
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
        $query = YeuCauTraHang::with(['donHang.khachHang', 'donHang.chiTiets', 'khachHang', 'chiTiets.bienThe', 'hinhAnhs', 'xuLyGanNhat.nguoiXuLy']);

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
        $return = YeuCauTraHang::with(['donHang.khachHang', 'donHang.chiTiets', 'khachHang', 'chiTiets.bienThe', 'hinhAnhs', 'lichSuXuLy.nguoiXuLy', 'xuLyGanNhat.nguoiXuLy'])
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
            $oldStatus = $return->trang_thai;
            $oldRefundStatus = $return->trang_thai_hoan_tien;

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

            LichSuXuLyTraHang::create([
                'ma_yeu_cau' => $return->ma_yeu_cau,
                'loai_thao_tac' => 'cap_nhat_trang_thai',
                'gia_tri_cu' => $oldStatus,
                'gia_tri_moi' => $data['status'],
                'ma_nguoi_xu_ly' => $request->user()->ma_kh,
                'thoi_gian_xu_ly' => now(),
                'ghi_chu' => $data['admin_note'] ?? $data['reject_reason'] ?? null,
            ]);

            if (!empty($data['refund_status']) && $oldRefundStatus !== $data['refund_status']) {
                LichSuXuLyTraHang::create([
                    'ma_yeu_cau' => $return->ma_yeu_cau,
                    'loai_thao_tac' => 'cap_nhat_hoan_tien',
                    'gia_tri_cu' => $oldRefundStatus,
                    'gia_tri_moi' => $data['refund_status'],
                    'ma_nguoi_xu_ly' => $request->user()->ma_kh,
                    'thoi_gian_xu_ly' => now(),
                    'ghi_chu' => $data['admin_note'] ?? null,
                ]);
            }
        });

        return response()->json([
            'message' => 'Đã cập nhật yêu cầu trả hàng.',
            'return_request' => $this->formatAdmin($return->fresh(['donHang.khachHang', 'donHang.chiTiets', 'khachHang', 'chiTiets.bienThe', 'hinhAnhs', 'lichSuXuLy.nguoiXuLy', 'xuLyGanNhat.nguoiXuLy']), true),
        ]);
    }

    public function updateRefund(Request $request, string $id)
    {
        $data = $request->validate([
            'refund_status' => ['required', Rule::in(['not_refunded', 'refunding', 'refunded', 'refund_failed'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        $return = YeuCauTraHang::findOrFail($id);
        $oldRefundStatus = $return->trang_thai_hoan_tien;
        $return->update([
            'trang_thai_hoan_tien' => $data['refund_status'],
            'ghi_chu_admin' => $data['admin_note'] ?? $return->ghi_chu_admin,
            'ngay_cap_nhat' => now(),
        ]);

        LichSuXuLyTraHang::create([
            'ma_yeu_cau' => $return->ma_yeu_cau,
            'loai_thao_tac' => 'cap_nhat_hoan_tien',
            'gia_tri_cu' => $oldRefundStatus,
            'gia_tri_moi' => $data['refund_status'],
            'ma_nguoi_xu_ly' => $request->user()->ma_kh,
            'thoi_gian_xu_ly' => now(),
            'ghi_chu' => $data['admin_note'] ?? null,
        ]);

        return response()->json(['message' => 'Đã cập nhật trạng thái hoàn tiền thủ công.']);
    }

    private function formatAdmin(YeuCauTraHang $return, bool $detail = false): array
    {
        $base = $this->format($return, $detail);
        return array_merge($base, [
            'customer' => $return->khachHang?->ten_kh ?? $return->donHang?->khachHang?->ten_kh,
            'customer_id' => $return->ma_kh,
            'last_processed_by' => $return->xuLyGanNhat?->nguoiXuLy?->ten_kh,
            'last_processed_at' => $return->xuLyGanNhat?->thoi_gian_xu_ly?->toISOString(),
            'total_refund_estimate' => $return->chiTiets->sum(function (ChiTietTraHang $item) use ($return) {
                $orderItem = $return->donHang?->chiTiets?->firstWhere('ma_bien_the', $item->ma_bien_the);
                return (float) ($orderItem?->don_gia ?? 0) * $item->so_luong;
            }),
            'processing_history' => $detail ? $return->lichSuXuLy?->sortByDesc('thoi_gian_xu_ly')->map(fn (LichSuXuLyTraHang $history) => [
                'action' => $history->loai_thao_tac,
                'old_value' => $history->gia_tri_cu,
                'new_value' => $history->gia_tri_moi,
                'processed_by' => $history->nguoiXuLy?->ten_kh,
                'processed_at' => $history->thoi_gian_xu_ly?->toISOString(),
                'note' => $history->ghi_chu,
            ])->values() : [],
        ]);
    }
}
