<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BienTheSanPham;
use App\Models\ChiTietPhieuNhapKho;
use App\Models\LichSuBienDongKho;
use App\Models\PhieuNhapKho;
use App\Services\InventoryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminInventoryController extends Controller
{
    public function variants(Request $request)
    {
        $query = BienTheSanPham::with(['sanPham.anhChinh', 'giaTriThuocTinhs.thuocTinh'])
            ->whereHas('sanPham');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('sanPham', fn ($product) => $product->where('ten_sp', 'like', "%{$search}%"));
            });
        }

        return response()->json(
            $query->orderBy('sku')->limit(200)->get()->map(fn (BienTheSanPham $variant) => $this->formatVariant($variant))
        );
    }

    public function receipts(Request $request)
    {
        $query = PhieuNhapKho::with(['nguoiNhap', 'nguoiDuyet', 'chiTiets.bienThe.sanPham']);

        if (!$request->user()->isAdmin()) {
            $query->where('ma_nguoi_nhap', $request->user()->ma_kh);
        }

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ma_phieu', 'like', "%{$search}%")
                    ->orWhere('ghi_chu', 'like', "%{$search}%");
            });
        }
        if ($from = $request->input('from')) {
            $query->whereDate('ngay_nhap', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('ngay_nhap', '<=', $to);
        }
        if ($userId = $request->input('user_id')) {
            $query->where('ma_nguoi_nhap', $userId);
        }
        if ($variantId = $request->input('variant_id')) {
            $query->whereHas('chiTiets', fn ($detail) => $detail->where('ma_bien_the', $variantId));
        }

        $receipts = $query->orderByDesc('ngay_nhap')
            ->orderByDesc('ngay_tao')
            ->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $receipts->getCollection()->map(fn (PhieuNhapKho $receipt) => $this->formatReceiptSummary($receipt)),
            'meta' => [
                'total' => $receipts->total(),
                'current_page' => $receipts->currentPage(),
                'last_page' => $receipts->lastPage(),
            ],
        ]);
    }

    public function storeReceipt(Request $request)
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:50', Rule::unique('phieu_nhap_kho', 'ma_phieu')],
            'import_date' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.variant_id' => ['required', 'exists:bien_the_san_pham,ma_bt'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.note' => ['nullable', 'string', 'max:500'],
        ]);

        $variantIds = collect($data['items'])->pluck('variant_id');
        if ($variantIds->unique()->count() !== $variantIds->count()) {
            throw ValidationException::withMessages(['items' => 'Không được nhập trùng cùng một SKU trong một phiếu.']);
        }

        $receipt = DB::transaction(function () use ($data, $request) {
            $receipt = PhieuNhapKho::create([
                'ma_phieu' => $data['code'] ?? $this->generateReceiptCode(),
                'ngay_nhap' => $data['import_date'],
                'ma_nguoi_nhap' => $request->user()->ma_kh,
                'ghi_chu' => $data['note'] ?? null,
                'trang_thai' => 'pending',
                'ngay_tao' => now(),
            ]);

            foreach ($data['items'] as $item) {
                ChiTietPhieuNhapKho::create([
                    'ma_pnk' => $receipt->ma_pnk,
                    'ma_bien_the' => $item['variant_id'],
                    'so_luong' => $item['quantity'],
                    'ghi_chu' => $item['note'] ?? null,
                ]);

            }

            return $receipt;
        });

        return response()->json([
            'message' => 'Đã tạo phiếu nhập kho.',
            'receipt' => $this->formatReceiptDetail($receipt->fresh(['nguoiNhap', 'nguoiDuyet', 'chiTiets.bienThe.sanPham.anhChinh', 'chiTiets.bienThe.giaTriThuocTinhs.thuocTinh'])),
        ], 201);
    }

    public function showReceipt(Request $request, string $id)
    {
        $receipt = PhieuNhapKho::with(['nguoiNhap', 'nguoiDuyet', 'chiTiets.bienThe.sanPham.anhChinh', 'chiTiets.bienThe.giaTriThuocTinhs.thuocTinh'])
            ->where('ma_pnk', $id)
            ->orWhere('ma_phieu', $id)
            ->firstOrFail();

        if (!$request->user()->isAdmin() && $receipt->ma_nguoi_nhap !== $request->user()->ma_kh) {
            abort(403, 'Báº¡n khÃ´ng cÃ³ quyá»n xem phiáº¿u nháº­p nÃ y.');
        }

        return response()->json($this->formatReceiptDetail($receipt));
    }

    public function approveReceipt(Request $request, string $id, InventoryService $inventoryService)
    {
        $data = $request->validate([
            'approval_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $receipt = DB::transaction(function () use ($id, $request, $data, $inventoryService) {
            $receipt = PhieuNhapKho::with('chiTiets')
                ->where('ma_pnk', $id)
                ->orWhere('ma_phieu', $id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($receipt->trang_thai !== 'pending') {
                throw ValidationException::withMessages(['receipt' => 'Phiáº¿u nháº­p nÃ y khÃ´ng cÃ²n chá» duyá»‡t.']);
            }

            $receipt->update([
                'trang_thai' => 'approved',
                'ma_nguoi_duyet' => $request->user()->ma_kh,
                'ngay_duyet' => now(),
                'ghi_chu_duyet' => $data['approval_note'] ?? null,
            ]);

            foreach ($receipt->chiTiets as $item) {
                $inventoryService->changeStock(
                    $item->ma_bien_the,
                    (int) $item->so_luong,
                    'stock_import',
                    $request->user()->ma_kh,
                    $item->ghi_chu ?? $receipt->ghi_chu ?? null,
                    $receipt->ma_phieu
                );
            }

            return $receipt;
        });

        return response()->json([
            'message' => 'ÄÃ£ duyá»‡t phiáº¿u nháº­p vÃ  cá»™ng tá»“n kho.',
            'receipt' => $this->formatReceiptDetail($receipt->fresh(['nguoiNhap', 'nguoiDuyet', 'chiTiets.bienThe.sanPham.anhChinh', 'chiTiets.bienThe.giaTriThuocTinhs.thuocTinh'])),
        ]);
    }

    public function rejectReceipt(Request $request, string $id)
    {
        $data = $request->validate([
            'approval_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $receipt = PhieuNhapKho::where('ma_pnk', $id)
            ->orWhere('ma_phieu', $id)
            ->firstOrFail();

        if ($receipt->trang_thai !== 'pending') {
            throw ValidationException::withMessages(['receipt' => 'Phiáº¿u nháº­p nÃ y khÃ´ng cÃ²n chá» duyá»‡t.']);
        }

        $receipt->update([
            'trang_thai' => 'rejected',
            'ma_nguoi_duyet' => $request->user()->ma_kh,
            'ngay_duyet' => now(),
            'ghi_chu_duyet' => $data['approval_note'] ?? null,
        ]);

        return response()->json([
            'message' => 'ÄÃ£ tá»« chá»‘i phiáº¿u nháº­p.',
            'receipt' => $this->formatReceiptDetail($receipt->fresh(['nguoiNhap', 'nguoiDuyet', 'chiTiets.bienThe.sanPham.anhChinh', 'chiTiets.bienThe.giaTriThuocTinhs.thuocTinh'])),
        ]);
    }

    public function movements(Request $request)
    {
        $query = LichSuBienDongKho::with(['bienThe.sanPham.anhChinh', 'bienThe.giaTriThuocTinhs.thuocTinh', 'nguoiThucHien']);

        if ($type = $request->input('type')) {
            $query->where('loai_bien_dong', $type);
        }
        if ($variantId = $request->input('variant_id')) {
            $query->where('ma_bien_the', $variantId);
        }
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('ma_tham_chieu', 'like', "%{$search}%")
                    ->orWhere('ghi_chu', 'like', "%{$search}%")
                    ->orWhereHas('bienThe.sanPham', fn ($product) => $product->where('ten_sp', 'like', "%{$search}%"))
                    ->orWhereHas('bienThe', fn ($variant) => $variant->where('sku', 'like', "%{$search}%"));
            });
        }
        if ($from = $request->input('from')) {
            $query->whereDate('thoi_gian', '>=', $from);
        }
        if ($to = $request->input('to')) {
            $query->whereDate('thoi_gian', '<=', $to);
        }
        if ($userId = $request->input('user_id')) {
            $query->where('ma_nguoi_thuc_hien', $userId);
        }

        $movements = $query->orderByDesc('thoi_gian')->paginate($request->integer('per_page', 20));

        return response()->json([
            'data' => $movements->getCollection()->map(fn (LichSuBienDongKho $movement) => $this->formatMovement($movement)),
            'meta' => [
                'total' => $movements->total(),
                'current_page' => $movements->currentPage(),
                'last_page' => $movements->lastPage(),
            ],
        ]);
    }

    public function adjust(Request $request, InventoryService $inventoryService)
    {
        $data = $request->validate([
            'variant_id' => ['required', 'exists:bien_the_san_pham,ma_bt'],
            'stock' => ['required', 'integer', 'min:0'],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $variant = DB::transaction(fn () => $inventoryService->adjustStock(
            $data['variant_id'],
            (int) $data['stock'],
            $request->user()->ma_kh,
            $data['reason']
        ));

        return response()->json([
            'message' => 'Đã điều chỉnh tồn kho.',
            'variant' => $this->formatVariant($variant->load(['sanPham.anhChinh', 'giaTriThuocTinhs.thuocTinh'])),
        ]);
    }

    public function alerts(Request $request)
    {
        $query = BienTheSanPham::with(['sanPham.anhChinh', 'giaTriThuocTinhs.thuocTinh'])
            ->where('trang_thai', true)
            ->whereColumn('so_luong_ton', '<=', 'nguong_canh_bao_ton');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('sanPham', fn ($product) => $product->where('ten_sp', 'like', "%{$search}%"));
            });
        }

        return response()->json([
            'data' => $query->orderBy('so_luong_ton')->get()->map(fn (BienTheSanPham $variant) => [
                ...$this->formatVariant($variant),
                'status' => $variant->so_luong_ton <= 0 ? 'out_of_stock' : 'low_stock',
            ]),
        ]);
    }

    private function generateReceiptCode(): string
    {
        return 'NK-' . now()->format('Ymd-His') . '-' . random_int(100, 999);
    }

    private function formatReceiptSummary(PhieuNhapKho $receipt): array
    {
        return [
            'id' => $receipt->ma_pnk,
            'code' => $receipt->ma_phieu,
            'import_date' => $receipt->ngay_nhap?->format('Y-m-d'),
            'importer' => $receipt->nguoiNhap?->ten_kh,
            'status' => $receipt->trang_thai,
            'approved_by' => $receipt->nguoiDuyet?->ten_kh,
            'approved_at' => $receipt->ngay_duyet?->toISOString(),
            'approval_note' => $receipt->ghi_chu_duyet,
            'item_count' => $receipt->chiTiets->count(),
            'total_quantity' => $receipt->chiTiets->sum('so_luong'),
            'note' => $receipt->ghi_chu,
            'created_at' => $receipt->ngay_tao?->toISOString(),
        ];
    }

    private function formatReceiptDetail(PhieuNhapKho $receipt): array
    {
        return [
            ...$this->formatReceiptSummary($receipt),
            'items' => $receipt->chiTiets->map(fn (ChiTietPhieuNhapKho $detail) => [
                'variant' => $this->formatVariant($detail->bienThe),
                'quantity' => $detail->so_luong,
                'note' => $detail->ghi_chu,
            ])->values(),
        ];
    }

    private function formatMovement(LichSuBienDongKho $movement): array
    {
        return [
            'id' => $movement->ma_ls_kho,
            'variant' => $this->formatVariant($movement->bienThe),
            'type' => $movement->loai_bien_dong,
            'quantity_change' => $movement->so_luong_thay_doi,
            'stock_before' => $movement->ton_kho_truoc,
            'stock_after' => $movement->ton_kho_sau,
            'actor' => $movement->nguoiThucHien?->ten_kh,
            'time' => $movement->thoi_gian?->toISOString(),
            'note' => $movement->ghi_chu,
            'reference' => $movement->ma_tham_chieu,
        ];
    }

    private function formatVariant(?BienTheSanPham $variant): ?array
    {
        if (!$variant) {
            return null;
        }

        return [
            'id' => $variant->ma_bt,
            'sku' => $variant->sku,
            'product_id' => $variant->ma_sp,
            'product_name' => $variant->sanPham?->ten_sp,
            'image' => $variant->sanPham?->anhChinh?->url,
            'stock' => $variant->so_luong_ton,
            'low_stock_threshold' => $variant->nguong_canh_bao_ton,
            'active' => $variant->trang_thai,
            'attributes' => $variant->giaTriThuocTinhs?->map(fn ($value) => [
                'name' => $value->thuocTinh?->ten_tt,
                'value' => $value->gia_tri,
            ])->values() ?? [],
        ];
    }
}
