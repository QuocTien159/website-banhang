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
        $query = YeuCauTraHang::with(['donHang.khachHang', 'donHang.chiTiets', 'khachHang', 'chiTiets.bienThe.sanPham.anhChinh', 'hinhAnhs', 'xuLyGanNhat.nguoiXuLy']);

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
        $return = YeuCauTraHang::with(['donHang.khachHang', 'donHang.chiTiets', 'khachHang', 'chiTiets.bienThe.sanPham.anhChinh', 'hinhAnhs', 'lichSuXuLy.nguoiXuLy', 'xuLyGanNhat.nguoiXuLy'])
            ->findOrFail($id);

        return response()->json($this->formatAdmin($return, true));
    }

    public function updateStatus(Request $request, string $id, InventoryService $inventoryService)
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(['approved', 'rejected', 'received', 'completed'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
            'reject_reason' => ['required_if:status,rejected', 'nullable', 'string', 'max:1000'],
        ]);

        $return = DB::transaction(function () use ($id, $data, $request, $inventoryService) {
            $return = YeuCauTraHang::with('chiTiets')
                ->where('ma_yeu_cau', $id)
                ->lockForUpdate()
                ->firstOrFail();
            $oldStatus = $return->trang_thai;
            $allowedTransitions = [
                'pending' => ['approved', 'rejected'],
                'approved' => ['received'],
                'received' => ['completed'],
            ];

            if (! in_array($data['status'], $allowedTransitions[$oldStatus] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => 'Yêu cầu trả hàng không thể chuyển sang trạng thái này.',
                ]);
            }

            if ($data['status'] === 'received') {
                if ($return->da_nhap_kho) {
                    throw ValidationException::withMessages([
                        'status' => 'Yêu cầu này đã được nhận hàng và nhập kho trước đó.',
                    ]);
                }

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
            }

            $return->update([
                'trang_thai' => $data['status'],
                'ghi_chu_admin' => $data['admin_note'] ?? $return->ghi_chu_admin,
                'ly_do_tu_choi' => $data['status'] === 'rejected'
                    ? $data['reject_reason']
                    : $return->ly_do_tu_choi,
                'da_nhap_kho' => $data['status'] === 'received' ? true : $return->da_nhap_kho,
                'ngay_nhan_hang' => $data['status'] === 'received' ? now() : $return->ngay_nhan_hang,
                'ngay_cap_nhat' => now(),
            ]);

            LichSuXuLyTraHang::create([
                'ma_yeu_cau' => $return->ma_yeu_cau,
                'loai_thao_tac' => 'cap_nhat_trang_thai',
                'gia_tri_cu' => $oldStatus,
                'gia_tri_moi' => $data['status'],
                'ma_nguoi_xu_ly' => $request->user()->ma_kh,
                'thoi_gian_xu_ly' => now(),
                'ghi_chu' => $data['admin_note'] ?? $data['reject_reason'] ?? null,
            ]);

            return $return;
        });

        return response()->json([
            'message' => 'Đã cập nhật yêu cầu trả hàng.',
            'return_request' => $this->formatAdmin($return->fresh(['donHang.khachHang', 'donHang.chiTiets', 'khachHang', 'chiTiets.bienThe.sanPham.anhChinh', 'hinhAnhs', 'lichSuXuLy.nguoiXuLy', 'xuLyGanNhat.nguoiXuLy']), true),
        ]);
    }

    public function updateRefund(Request $request, string $id)
    {
        $data = $request->validate([
            'refund_status' => ['required', Rule::in(['not_refunded', 'refunding', 'refunded', 'refund_failed'])],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);

        DB::transaction(function () use ($id, $data, $request) {
            $return = YeuCauTraHang::where('ma_yeu_cau', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! in_array($return->trang_thai, ['received', 'completed'], true)) {
                throw ValidationException::withMessages([
                    'refund_status' => 'Chỉ hoàn tiền sau khi đã nhận hàng trả về kho.',
                ]);
            }

            $oldRefundStatus = $return->trang_thai_hoan_tien;
            $allowedTransitions = [
                'not_refunded' => ['refunding'],
                'refunding' => ['refunded', 'refund_failed'],
                'refund_failed' => ['refunding'],
                'refunded' => [],
            ];

            if (! in_array($data['refund_status'], $allowedTransitions[$oldRefundStatus] ?? [], true)) {
                throw ValidationException::withMessages([
                    'refund_status' => 'Trạng thái hoàn tiền không thể chuyển theo cách này.',
                ]);
            }

            $return->update([
                'trang_thai_hoan_tien' => $data['refund_status'],
                'ghi_chu_admin' => $data['admin_note'] ?? $return->ghi_chu_admin,
                'ngay_hoan_tien' => $data['refund_status'] === 'refunded' ? now() : $return->ngay_hoan_tien,
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
        });

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
            'total_refund_estimate' => $this->refundEstimate($return),
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

    private function refundEstimate(YeuCauTraHang $return): float
    {
        $order = $return->donHang;
        if (! $order || ! $order->chiTiets) {
            return 0;
        }

        $grossLineTotal = (float) $order->chiTiets->sum(
            fn ($item) => max(0, (int) $item->so_luong) * (float) $item->don_gia
        );
        if ($grossLineTotal <= 0) {
            return 0;
        }

        $returnedLineTotal = (float) $return->chiTiets->sum(function (ChiTietTraHang $item) use ($order) {
            $orderItem = $order->chiTiets->firstWhere('ma_bien_the', $item->ma_bien_the);
            if (! $orderItem) {
                return 0;
            }

            return min((int) $item->so_luong, (int) $orderItem->so_luong) * (float) $orderItem->don_gia;
        });

        $subtotal = (float) ($order->tam_tinh ?? $grossLineTotal);
        $productTotal = min($grossLineTotal, max(0, $subtotal));
        $discount = min($productTotal, max(0, (float) ($order->so_tien_giam ?? 0)));
        $refundableProductTotal = $productTotal - $discount;

        return round($returnedLineTotal * ($refundableProductTotal / $grossLineTotal), 2);
    }
}
