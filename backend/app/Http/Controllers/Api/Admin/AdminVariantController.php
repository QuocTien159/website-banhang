<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BienTheSanPham;
use App\Models\ChiTietDonHang;
use App\Models\ChiTietPhieuNhapKho;
use App\Models\GiaTriThuocTinh;
use App\Models\HinhAnhSanPham;
use App\Models\SanPham;
use App\Models\ThuocTinh;
use App\Services\VariantStockStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminVariantController extends Controller
{
    public function index(Request $request, VariantStockStatusService $stockStatus)
    {
        $this->abortUnlessAdmin($request);

        $hasQuery = $request->filled('search')
            || $request->filled('product_id')
            || $request->filled('category_id')
            || $request->filled('sell_status')
            || $request->filled('stock_status')
            || $request->filled('image_mode');

        if (!$hasQuery) {
            return response()->json([
                'data' => [],
                'meta' => ['requires_query' => true, 'total' => 0, 'current_page' => 1, 'last_page' => 1],
            ]);
        }

        $variantQuery = $this->filteredQuery($request);
        $products = SanPham::with(['danhMuc', 'anhChinh'])
            ->whereIn('ma_sp', (clone $variantQuery)->select('ma_sp'));
        $direction = $request->input('direction') === 'desc' ? 'desc' : 'asc';
        $sort = $request->input('sort', 'name');
        if ($sort === 'sku') {
            $products->orderBy((clone $variantQuery)
                ->select('sku')
                ->whereColumn('bien_the_san_pham.ma_sp', 'san_pham.ma_sp')
                ->orderBy('sku')
                ->limit(1), $direction);
        } elseif ($sort === 'stock') {
            $products->orderBy((clone $variantQuery)
                ->selectRaw('SUM(so_luong_ton)')
                ->whereColumn('bien_the_san_pham.ma_sp', 'san_pham.ma_sp'), $direction);
        } else {
            $products->orderBy($sort === 'updated' ? 'ngay_cap_nhat' : 'ten_sp', $direction);
        }
        $products = $products->paginate($request->integer('per_page', 12));

        $productIds = $products->getCollection()->pluck('ma_sp');
        $allVariants = BienTheSanPham::with(['giaTriThuocTinhs.thuocTinh', 'hinhAnhs'])
            ->whereIn('ma_sp', $productIds)
            ->orderBy('sku')
            ->get()
            ->groupBy('ma_sp');
        $matchedVariants = (clone $variantQuery)
            ->with(['giaTriThuocTinhs.thuocTinh', 'hinhAnhs'])
            ->whereIn('ma_sp', $productIds)
            ->orderBy('sku')
            ->get()
            ->groupBy('ma_sp');

        return response()->json([
            'data' => $products->getCollection()->map(fn (SanPham $product) => $this->formatProductGroup(
                $product,
                $matchedVariants->get($product->ma_sp, collect()),
                $allVariants->get($product->ma_sp, collect()),
                $stockStatus
            ))->values(),
            'meta' => [
                'requires_query' => false,
                'total' => $products->total(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    public function show(Request $request, string $id, VariantStockStatusService $stockStatus)
    {
        $this->abortUnlessAdmin($request);

        $variant = BienTheSanPham::with([
            'sanPham.danhMuc',
            'sanPham.hinhAnhs',
            'giaTriThuocTinhs.thuocTinh',
            'hinhAnhs',
        ])->findOrFail($id);

        $movements = $variant->lichSuKho()
            ->with('nguoiThucHien')
            ->orderByDesc('thoi_gian')
            ->limit(20)
            ->get()
            ->map(fn ($movement) => [
                'id' => $movement->ma_ls_kho,
                'type' => $movement->loai_bien_dong,
                'quantity_change' => $movement->so_luong_thay_doi,
                'stock_before' => $movement->ton_kho_truoc,
                'stock_after' => $movement->ton_kho_sau,
                'actor' => $movement->nguoiThucHien?->ten_kh,
                'time' => $movement->thoi_gian?->toISOString(),
                'note' => $movement->ghi_chu,
                'reference' => $movement->ma_tham_chieu,
            ]);
        $receipts = ChiTietPhieuNhapKho::with('phieuNhap.nguoiDuyet')
            ->where('ma_bien_the', $variant->ma_bt)
            ->orderByDesc('ma_pnk')
            ->limit(10)
            ->get()
            ->map(fn (ChiTietPhieuNhapKho $detail) => [
                'id' => $detail->ma_pnk,
                'code' => $detail->phieuNhap?->ma_phieu,
                'quantity' => $detail->so_luong,
                'status' => $detail->phieuNhap?->trang_thai,
                'approved_at' => $detail->phieuNhap?->ngay_duyet?->toISOString(),
                'approved_by' => $detail->phieuNhap?->nguoiDuyet?->ten_kh,
            ]);
        $orders = ChiTietDonHang::with('donHang')
            ->where('ma_bien_the', $variant->ma_bt)
            ->orderByDesc('ma_dh')
            ->limit(10)
            ->get()
            ->map(fn (ChiTietDonHang $detail) => [
                'id' => $detail->ma_dh,
                'quantity' => $detail->so_luong,
                'status' => $detail->donHang?->trang_thai,
                'ordered_at' => $detail->donHang?->ngay_dat?->toISOString(),
            ]);

        return response()->json([
            'variant' => $this->formatVariant($variant, $stockStatus),
            'history' => [
                'movements' => $movements,
                'receipts' => $receipts,
                'orders' => $orders,
            ],
        ]);
    }

    public function store(Request $request, VariantStockStatusService $stockStatus)
    {
        $this->abortUnlessAdmin($request);
        $data = $this->validateStore($request);
        $product = SanPham::findOrFail($data['product_id']);
        $attributeIds = $this->attributeIds($data['attributes']);
        $signature = BienTheSanPham::signatureFor($data['attributes']);

        $this->ensureUniqueCombination($product->ma_sp, $signature);

        $variant = DB::transaction(function () use ($data, $product, $attributeIds, $signature) {
            $status = $data['sell_status'];
            $variant = $product->bienThes()->create([
                'sku' => trim($data['sku']),
                'variant_signature' => $signature,
                'gia_niem_yet' => $data['list_price'] ?? $data['price'],
                'gia_ban' => $data['price'],
                'so_luong_ton' => 0,
                'nguong_canh_bao_ton' => $data['low_stock_threshold'] ?? 5,
                'trang_thai' => $status === 'active',
                'trang_thai_ban' => $status,
                'ngay_cap_nhat' => now(),
            ]);
            $variant->giaTriThuocTinhs()->sync($attributeIds);
            $product->update(['ngay_cap_nhat' => now()]);

            return $variant;
        });

        return response()->json([
            'message' => 'Đã tạo biến thể với tồn kho ban đầu bằng 0.',
            'variant' => $this->formatVariant($variant->load(['sanPham', 'giaTriThuocTinhs.thuocTinh', 'hinhAnhs']), $stockStatus),
        ], 201);
    }

    public function update(Request $request, string $id, VariantStockStatusService $stockStatus)
    {
        $this->abortUnlessAdmin($request);
        $variant = BienTheSanPham::with(['sanPham', 'giaTriThuocTinhs.thuocTinh'])->findOrFail($id);
        $data = $this->validateUpdate($request, $variant);

        if (array_key_exists('attributes', $data) && $this->hasBusinessReferences($variant)) {
            throw ValidationException::withMessages([
                'attributes' => 'Không thể đổi tổ hợp thuộc tính của SKU đã có đơn hàng, giỏ hàng hoặc lịch sử kho. Hãy ngừng bán SKU này và tạo biến thể mới.',
            ]);
        }

        DB::transaction(function () use ($variant, $data) {
            $updates = [];
            foreach (['sku', 'gia_ban', 'nguong_canh_bao_ton'] as $field) {
                if (array_key_exists($field, $data)) {
                    $updates[$field] = $field === 'sku' ? trim($data[$field]) : $data[$field];
                }
            }
            if (array_key_exists('list_price', $data)) {
                $updates['gia_niem_yet'] = $data['list_price'];
            }

            if (array_key_exists('sell_status', $data)) {
                $updates['trang_thai_ban'] = $data['sell_status'];
                $updates['trang_thai'] = $data['sell_status'] === 'active';
            }

            if (array_key_exists('attributes', $data)) {
                $attributeIds = $this->attributeIds($data['attributes']);
                $signature = BienTheSanPham::signatureFor($data['attributes']);
                $this->ensureUniqueCombination($variant->ma_sp, $signature, $variant->ma_bt);
                $updates['variant_signature'] = $signature;
                $variant->giaTriThuocTinhs()->sync($attributeIds);
            }

            if (array_key_exists('image_id', $data)) {
                $variant->hinhAnhs()->update(['ma_bt' => null]);

                if ($data['image_id']) {
                    HinhAnhSanPham::where('ma_sp', $variant->ma_sp)
                        ->where('ma_anh', $data['image_id'])
                        ->update(['ma_bt' => $variant->ma_bt]);
                }
            }

            $variant->update($updates + ['ngay_cap_nhat' => now()]);
            $variant->sanPham()->update(['ngay_cap_nhat' => now()]);
        });

        return response()->json([
            'message' => 'Đã cập nhật biến thể. Tồn kho chỉ thay đổi qua nghiệp vụ kho.',
            'variant' => $this->formatVariant($variant->fresh(['sanPham', 'giaTriThuocTinhs.thuocTinh', 'hinhAnhs']), $stockStatus),
        ]);
    }

    private function filteredQuery(Request $request): Builder
    {
        $query = BienTheSanPham::query()->whereHas('sanPham');

        if ($search = trim((string) $request->input('search'))) {
            $query->where(function (Builder $query) use ($search) {
                $query->where('sku', 'like', "%{$search}%")
                    ->orWhereHas('sanPham', fn (Builder $product) => $product
                        ->where('ten_sp', 'like', "%{$search}%")
                        ->orWhere('ma_sp', 'like', "%{$search}%"))
                    ->orWhereHas('giaTriThuocTinhs', fn (Builder $value) => $value
                        ->where('gia_tri', 'like', "%{$search}%"));
            });
        }
        if ($productId = $request->input('product_id')) {
            $query->where('ma_sp', $productId);
        }
        if ($categoryId = $request->input('category_id')) {
            $query->whereHas('sanPham', fn (Builder $product) => $product->where('ma_dm', $categoryId));
        }
        if ($sellStatus = $request->input('sell_status')) {
            $query->where('trang_thai_ban', $sellStatus);
        }
        if ($stockStatus = $request->input('stock_status')) {
            match ($stockStatus) {
                'in_stock' => $query->where('so_luong_ton', '>', 0)->whereColumn('so_luong_ton', '>', 'nguong_canh_bao_ton'),
                'low_stock' => $query->where('so_luong_ton', '>', 0)->whereColumn('so_luong_ton', '<=', 'nguong_canh_bao_ton'),
                'out_of_stock' => $query->where('so_luong_ton', 0),
                default => null,
            };
        }
        if ($imageMode = $request->input('image_mode')) {
            $imageMode === 'own' ? $query->whereHas('hinhAnhs') : $query->whereDoesntHave('hinhAnhs');
        }

        return $query;
    }

    private function formatProductGroup(SanPham $product, $matched, $all, VariantStockStatusService $stockStatus): array
    {
        return [
            'id' => $product->ma_sp,
            'name' => $product->ten_sp,
            'category' => $product->danhMuc?->ten_dm,
            'image' => $product->anhChinh?->url,
            'variant_count' => $all->count(),
            'stock_total' => (int) $all->sum('so_luong_ton'),
            'alert_count' => $all->filter(fn (BienTheSanPham $variant) => $stockStatus->isAlert($variant))->count(),
            'variants' => $matched->map(fn (BienTheSanPham $variant) => $this->formatVariant($variant, $stockStatus))->values(),
        ];
    }

    private function formatVariant(BienTheSanPham $variant, VariantStockStatusService $stockStatus): array
    {
        return [
            'id' => $variant->ma_bt,
            'product_id' => $variant->ma_sp,
            'product_name' => $variant->sanPham?->ten_sp,
            'sku' => $variant->sku,
            'list_price' => (float) ($variant->gia_niem_yet ?? $variant->gia_ban),
            'price' => (float) $variant->gia_ban,
            'stock' => (int) $variant->so_luong_ton,
            'low_stock_threshold' => (int) $variant->nguong_canh_bao_ton,
            'sell_status' => $variant->trang_thai_ban,
            'stock_status' => $stockStatus->status($variant),
            'attributes' => $variant->giaTriThuocTinhs->map(fn (GiaTriThuocTinh $value) => [
                'name' => $value->thuocTinh?->ten_tt,
                'value' => $value->gia_tri,
            ])->values(),
            'image_id' => $variant->hinhAnhs->first()?->ma_anh,
            'image' => $variant->hinhAnhs->first()?->url ?? $variant->sanPham?->anhChinh?->url,
            'image_mode' => $variant->hinhAnhs->isNotEmpty() ? 'own' : 'shared',
            'updated_at' => $variant->ngay_cap_nhat?->toISOString(),
        ];
    }

    private function validateStore(Request $request): array
    {
        return $request->validate([
            'product_id' => ['required', Rule::exists('san_pham', 'ma_sp')],
            'sku' => ['required', 'string', 'max:50', Rule::unique('bien_the_san_pham', 'sku')],
            'list_price' => ['nullable', 'numeric', 'min:0'],
            'price' => ['required', 'numeric', 'min:0'],
            'low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'sell_status' => ['required', Rule::in(['active', 'inactive', 'incomplete'])],
            'stock' => ['prohibited'],
            ...$this->attributeRules('attributes', true),
        ]);
    }

    private function validateUpdate(Request $request, BienTheSanPham $variant): array
    {
        $data = $request->validate([
            'sku' => ['sometimes', 'required', 'string', 'max:50', Rule::unique('bien_the_san_pham', 'sku')->ignore($variant->ma_bt, 'ma_bt')],
            'list_price' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'price' => ['sometimes', 'required', 'numeric', 'min:0'],
            'low_stock_threshold' => ['sometimes', 'integer', 'min:0'],
            'sell_status' => ['sometimes', Rule::in(['active', 'inactive', 'incomplete'])],
            'image_id' => ['sometimes', 'nullable', Rule::exists('hinh_anh_san_pham', 'ma_anh')],
            'stock' => ['prohibited'],
            ...$this->attributeRules('attributes', false),
        ]);

        if (array_key_exists('price', $data)) {
            $data['gia_ban'] = $data['price'];
            unset($data['price']);
        }

        if (array_key_exists('low_stock_threshold', $data)) {
            $data['nguong_canh_bao_ton'] = $data['low_stock_threshold'];
            unset($data['low_stock_threshold']);
        }

        if (!empty($data['image_id']) && !HinhAnhSanPham::where('ma_anh', $data['image_id'])
            ->where('ma_sp', $variant->ma_sp)
            ->exists()) {
            throw ValidationException::withMessages([
                'image_id' => 'Ảnh được gán phải thuộc cùng sản phẩm với biến thể.',
            ]);
        }

        return $data;
    }

    private function attributeRules(string $key, bool $required): array
    {
        return [
            $key => [$required ? 'required' : 'sometimes', 'array', 'min:1', 'max:8'],
            "{$key}.*.name" => ['required', 'string', 'max:50'],
            "{$key}.*.value" => ['required', 'string', 'max:50'],
        ];
    }

    private function attributeIds(array $attributes): array
    {
        $names = collect($attributes)->pluck('name')->map(fn ($name) => trim($name));
        if ($names->unique()->count() !== $names->count()) {
            throw ValidationException::withMessages(['attributes' => 'Một biến thể không được lặp loại thuộc tính.']);
        }

        return collect($attributes)->map(function (array $attribute) {
            $type = ThuocTinh::where('ten_tt', trim($attribute['name']))->first();
            $value = $type
                ? GiaTriThuocTinh::where('ma_tt', $type->ma_tt)->where('gia_tri', trim($attribute['value']))->first()
                : null;

            if (!$value) {
                throw ValidationException::withMessages([
                    'attributes' => "Không tìm thấy giá trị thuộc tính {$attribute['name']}: {$attribute['value']}.",
                ]);
            }

            return $value->ma_gt;
        })->all();
    }

    private function ensureUniqueCombination(string $productId, string $signature, ?string $exceptId = null): void
    {
        $exists = BienTheSanPham::where('ma_sp', $productId)
            ->where('variant_signature', $signature)
            ->when($exceptId, fn (Builder $query) => $query->where('ma_bt', '!=', $exceptId))
            ->exists();

        if ($exists) {
            throw ValidationException::withMessages(['attributes' => 'Tổ hợp thuộc tính này đã có biến thể trong sản phẩm.']);
        }
    }

    private function hasBusinessReferences(BienTheSanPham $variant): bool
    {
        return $variant->chiTietDonHangs()->exists()
            || $variant->chiTietGioHangs()->exists()
            || $variant->lichSuKho()->exists()
            || ChiTietPhieuNhapKho::where('ma_bien_the', $variant->ma_bt)->exists();
    }

    private function abortUnlessAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403);
    }
}
