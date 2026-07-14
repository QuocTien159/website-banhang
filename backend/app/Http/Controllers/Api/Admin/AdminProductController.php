<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BienTheSanPham;
use App\Models\GiaTriThuocTinh;
use App\Models\HinhAnhSanPham;
use App\Models\SanPham;
use App\Models\ThuocTinh;
use App\Services\CloudinaryMediaService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminProductController extends Controller
{
    public function index(Request $request)
    {
        $query = SanPham::with(['danhMuc', 'anhChinh', 'bienThes']);

        if ($search = $request->input('search')) {
            $query->where('ten_sp', 'like', "%{$search}%");
        }
        if ($category = $request->input('category_id')) {
            $query->where('ma_dm', $category);
        }
        if ($status = $request->input('status')) {
            $query->where('trang_thai', $status);
        }

        $products = $query->orderByDesc('ngay_tao')->paginate($request->integer('per_page', 15));

        return response()->json([
            'data' => $products->getCollection()->map(fn (SanPham $product) => $this->formatSummary($product)),
            'meta' => [
                'total' => $products->total(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    public function show(string $id)
    {
        $product = SanPham::with([
            'danhMuc',
            'hinhAnhs',
            'bienThes.giaTriThuocTinhs.thuocTinh',
        ])->findOrFail($id);

        return response()->json($this->formatDetail($product));
    }

    public function options()
    {
        return response()->json([
            'attributes' => ThuocTinh::with('giaTriThuocTinhs')
                ->where('trang_thai', true)
                ->orderBy('ten_tt')
                ->get()
                ->map(fn (ThuocTinh $attribute) => [
                    'id' => $attribute->ma_tt,
                    'name' => $attribute->ten_tt,
                    'slug' => $attribute->slug,
                    'type' => $attribute->loai_hien_thi ?? 'select',
                    'values' => $attribute->giaTriThuocTinhs
                        ->where('trang_thai', true)
                        ->sortBy([['thu_tu', 'asc'], ['gia_tri', 'asc']])
                        ->map(fn (GiaTriThuocTinh $value) => [
                            'id' => $value->ma_gt,
                            'value' => $value->gia_tri,
                            'slug' => $value->slug,
                            'color_code' => $value->ma_mau,
                        ])
                        ->values(),
                ]),
        ]);
    }

    public function uploadImages(Request $request)
    {
        $this->abortUnlessAdmin($request);
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:8'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=800,min_height=800,max_width=5000,max_height=5000'],
        ], [
            'images.*.dimensions' => 'Ảnh sản phẩm cần tối thiểu 800 x 800 px để hiển thị rõ nét.',
        ]);

        $media = app(CloudinaryMediaService::class);
        $images = collect($data['images'])->map(fn (UploadedFile $image) =>
            $media->upload($image, 'products', $request->user()->ma_kh)
        );

        return response()->json(['images' => $images], 201);
    }

    public function store(Request $request)
    {
        $this->abortUnlessAdmin($request);

        $data = $this->validateProduct($request);
        $this->validateVariantCombinations($data['variants']);

        $product = DB::transaction(function () use ($data, $request) {
            $product = SanPham::create([
                'ma_dm' => $data['category_id'],
                'ten_sp' => trim($data['name']),
                'mo_ta' => $data['description'] ?? null,
                'gia_co_ban' => $data['base_price'],
                'trang_thai' => $data['status'],
                'ngay_tao' => now(),
            ]);

            $this->syncVariants($product, $data['variants']);
            $this->syncImages($product, $data['images'], $request->user()->ma_kh);

            return $product;
        });

        return response()->json(
            $this->formatDetail($product->load(['danhMuc', 'hinhAnhs', 'bienThes.giaTriThuocTinhs.thuocTinh'])),
            201
        );
    }

    public function update(Request $request, string $id)
    {
        $this->abortUnlessAdmin($request);

        $product = SanPham::with(['hinhAnhs', 'bienThes.chiTietDonHangs'])->findOrFail($id);
        $data = $this->validateProduct($request, $product);
        $this->validateVariantCombinations($data['variants'], $product);

        DB::transaction(function () use ($product, $data, $request) {
            $product->update([
                'ma_dm' => $data['category_id'],
                'ten_sp' => trim($data['name']),
                'mo_ta' => $data['description'] ?? null,
                'gia_co_ban' => $data['base_price'],
                'trang_thai' => $data['status'],
            ]);

            $this->syncVariants($product, $data['variants']);
            $this->syncImages($product, $data['images'], $request->user()->ma_kh);
        });

        return response()->json($this->formatDetail(
            $product->fresh(['danhMuc', 'hinhAnhs', 'bienThes.giaTriThuocTinhs.thuocTinh'])
        ));
    }

    public function destroy(string $id)
    {
        $product = SanPham::findOrFail($id);
        $product->update(['trang_thai' => 'inactive']);
        $product->bienThes()->update(['trang_thai' => false]);

        return response()->json(['message' => 'Sản phẩm và các biến thể đã được ngừng bán.']);
    }

    public function hide(Request $request, string $id)
    {
        $product = SanPham::findOrFail($id);
        if ($product->trang_thai !== 'active') {
            return response()->json(['message' => 'Sáº£n pháº©m khÃ´ng Ä‘ang hiá»ƒn thá»‹.'], 422);
        }

        $product->update(['trang_thai' => 'inactive']);
        $product->bienThes()->update(['trang_thai' => false]);

        return response()->json(['message' => 'ÄÃ£ áº©n sáº£n pháº©m.']);
    }

    public function updateVariant(Request $request, string $id)
    {
        $variant = BienTheSanPham::findOrFail($id);
        $data = $request->validate([
            'gia_ban' => ['sometimes', 'numeric', 'min:0'],
            'nguong_canh_bao_ton' => ['sometimes', 'integer', 'min:0'],
            'trang_thai' => ['sometimes', 'boolean'],
        ]);

        if (!$request->user()->isAdmin()) {
            if (array_keys($data) !== ['trang_thai'] || $data['trang_thai'] !== false) {
                return response()->json(['message' => 'NhÃ¢n viÃªn chá»‰ Ä‘Æ°á»£c ngá»«ng bÃ¡n biáº¿n thá»ƒ.'], 403);
            }
        }

        $variant->update($data);

        return response()->json(['message' => 'Đã cập nhật biến thể.', 'variant' => $variant]);
    }

    private function abortUnlessAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Báº¡n khÃ´ng cÃ³ quyá»n thá»±c hiá»‡n chá»©c nÄƒng nÃ y.');
    }

    private function validateProduct(Request $request, ?SanPham $product = null): array
    {
        $variantIds = $product?->bienThes()->pluck('ma_bt')->all() ?? [];

        return $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'category_id' => ['required', Rule::exists('danh_muc', 'ma_dm')->where('trang_thai', true)],
            'description' => ['nullable', 'string', 'max:5000'],
            'base_price' => ['required', 'numeric', 'min:0'],
            'status' => ['required', Rule::in(['active', 'inactive', 'out_of_stock'])],
            'images' => ['required', 'array', 'min:1', 'max:8'],
            'images.*.id' => ['nullable', 'string'],
            'images.*.url' => ['required', 'string', 'max:500'],
            'images.*.path' => ['nullable', 'string', 'max:255'],
            'images.*.upload_token' => ['nullable', 'string'],
            'images.*.variant_id' => ['nullable', Rule::in($variantIds)],
            'images.*.variant_sku' => ['nullable', 'string', 'max:50'],
            'images.*.is_primary' => ['required', 'boolean'],
            'variants' => ['required', 'array', 'min:1', 'max:100'],
            'variants.*.id' => ['nullable', Rule::in($variantIds)],
            'variants.*.sku' => ['required', 'string', 'max:50'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.stock' => ['required', 'integer', 'min:0'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'variants.*.active' => ['required', 'boolean'],
            'variants.*.attributes' => ['required', 'array', 'min:1'],
            'variants.*.attributes.*.name' => ['required', 'string', 'max:50'],
            'variants.*.attributes.*.value' => ['required', 'string', 'max:50'],
        ]);
    }

    private function validateVariantCombinations(array $variants, ?SanPham $product = null): void
    {
        $skus = collect($variants)->pluck('sku')->map(fn ($sku) => mb_strtoupper(trim($sku)));
        if ($skus->unique()->count() !== $skus->count()) {
            throw ValidationException::withMessages(['variants' => 'SKU trong danh sách biến thể không được trùng nhau.']);
        }

        foreach ($variants as $index => $variant) {
            $duplicate = BienTheSanPham::where('sku', trim($variant['sku']))
                ->when($variant['id'] ?? null, fn ($query, $id) => $query->where('ma_bt', '!=', $id))
                ->exists();
            if ($duplicate) {
                throw ValidationException::withMessages([
                    "variants.{$index}.sku" => "SKU {$variant['sku']} đã tồn tại.",
                ]);
            }

            $names = collect($variant['attributes'])->pluck('name')->map(fn ($name) => mb_strtolower(trim($name)));
            if ($names->unique()->count() !== $names->count()) {
                throw ValidationException::withMessages([
                    "variants.{$index}.attributes" => 'Một biến thể không được lặp loại thuộc tính.',
                ]);
            }
        }

        $signatures = collect($variants)->map(fn ($variant) => BienTheSanPham::signatureFor($variant['attributes']));
        if ($signatures->unique()->count() !== $signatures->count()) {
            throw ValidationException::withMessages(['variants' => 'Không được tạo hai biến thể có cùng tổ hợp thuộc tính.']);
        }
    }

    private function syncImages(SanPham $product, array $images, string $actorId): void
    {
        if (collect($images)->where('is_primary', true)->count() !== 1) {
            throw ValidationException::withMessages(['images' => 'Phải chọn đúng một ảnh đại diện.']);
        }

        $keptIds = collect($images)->pluck('id')->filter()->all();
        $removed = $product->hinhAnhs()->whereNotIn('ma_anh', $keptIds)->get();
        foreach ($removed as $image) {
            app(CloudinaryMediaService::class)->delete($image->provider, $image->cloudinary_public_id, $this->localPath($image->url));
            $image->delete();
        }

        foreach ($images as $order => $imageData) {
            $variantId = $imageData['variant_id'] ?? null;
            if (!$variantId && !empty($imageData['variant_sku'])) {
                $variantId = $product->bienThes()->where('sku', $imageData['variant_sku'])->value('ma_bt');
                if (!$variantId) {
                    throw ValidationException::withMessages(['images' => 'Không tìm thấy SKU được gán cho ảnh.']);
                }
            }
            if ($variantId && !$product->bienThes()->where('ma_bt', $variantId)->exists()) {
                throw ValidationException::withMessages(['images' => 'Ảnh chỉ được gán cho biến thể thuộc sản phẩm này.']);
            }
            if (!empty($imageData['id'])) {
                $image = $product->hinhAnhs()->where('ma_anh', $imageData['id'])->firstOrFail();
                $image->update([
                    'anh_chinh' => $imageData['is_primary'],
                    'ma_bt' => $variantId,
                    'thu_tu' => $order,
                ]);
            } else {
                $asset = $this->verifiedImageAsset($imageData, 'products', $actorId);
                HinhAnhSanPham::create([
                    'ma_sp' => $product->ma_sp,
                    'ma_bt' => $variantId,
                    'url' => $asset['url'],
                    'provider' => $asset['provider'],
                    'cloudinary_public_id' => $asset['public_id'] ?? null,
                    'chieu_rong' => $asset['width'] ?? null,
                    'chieu_cao' => $asset['height'] ?? null,
                    'anh_chinh' => $imageData['is_primary'],
                    'thu_tu' => $order,
                ]);
            }
        }
    }

    private function syncVariants(SanPham $product, array $variants): void
    {
        $keptIds = collect($variants)->pluck('id')->filter()->all();
        $removedVariants = $product->bienThes()->whereNotIn('ma_bt', $keptIds)->get();

        foreach ($removedVariants as $variant) {
            if ($variant->chiTietDonHangs()->exists()) {
                $variant->update(['trang_thai' => false]);
            } else {
                $variant->delete();
            }
        }

        foreach ($variants as $variantData) {
            $attributes = collect($variantData['attributes'])
                ->map(fn ($attribute) => [
                    'name' => trim($attribute['name']),
                    'value' => trim($attribute['value']),
                ])
                ->values()
                ->all();

            $values = collect($attributes)->map(function ($attribute) {
                $type = ThuocTinh::firstOrCreate(
                    ['ten_tt' => $attribute['name']],
                    [
                        'slug' => Str::slug($attribute['name']),
                        'loai_hien_thi' => 'select',
                        'trang_thai' => true,
                        'ngay_tao' => now(),
                        'ngay_cap_nhat' => now(),
                    ]
                );
                return GiaTriThuocTinh::firstOrCreate([
                    'ma_tt' => $type->ma_tt,
                    'gia_tri' => $attribute['value'],
                ], [
                    'slug' => Str::slug($attribute['value']),
                    'thu_tu' => 0,
                    'trang_thai' => true,
                    'ngay_tao' => now(),
                    'ngay_cap_nhat' => now(),
                ]);
            });

            $payload = [
                'sku' => trim($variantData['sku']),
                'variant_signature' => BienTheSanPham::signatureFor($attributes),
                'gia_ban' => $variantData['price'],
                'nguong_canh_bao_ton' => $variantData['low_stock_threshold'] ?? 5,
                'trang_thai' => $variantData['active'],
            ];

            if (!empty($variantData['id'])) {
                $variant = $product->bienThes()->where('ma_bt', $variantData['id'])->firstOrFail();
                $variant->update($payload);
            } else {
                $variant = $product->bienThes()->create($payload + ['so_luong_ton' => 0]);
            }

            $variant->giaTriThuocTinhs()->sync($values->pluck('ma_gt')->all());
        }
    }

    private function verifiedImageAsset(array $image, string $purpose, string $actorId): array
    {
        if (!empty($image['upload_token'])) {
            return app(CloudinaryMediaService::class)->verifiedUpload($image['upload_token'], $purpose, $actorId);
        }

        if (!empty($image['path']) && str_starts_with($image['path'], $purpose.'/')) {
            return ['url' => $image['url'], 'path' => $image['path'], 'provider' => 'local'];
        }

        throw ValidationException::withMessages(['images' => 'Ảnh mới phải được tải lên qua API quản lý ảnh.']);
    }

    private function localPath(string $url): ?string
    {
        $prefix = rtrim(\Storage::disk('public')->url(''), '/').'/';
        return str_starts_with($url, $prefix) ? substr($url, strlen($prefix)) : null;
    }

    private function formatSummary(SanPham $product): array
    {
        $prices = $product->bienThes->pluck('gia_ban')->map(fn ($price) => (float) $price);
        $imageUrls = app(CloudinaryMediaService::class)->urls($product->anhChinh?->url, $product->anhChinh?->provider);
        return [
            'id' => $product->ma_sp,
            'name' => $product->ten_sp,
            'category' => $product->danhMuc?->ten_dm,
            'category_id' => $product->ma_dm,
            'image' => $imageUrls['list_url'],
            'image_urls' => $imageUrls,
            'min_price' => $prices->min() ?? (float) $product->gia_co_ban,
            'max_price' => $prices->max() ?? (float) $product->gia_co_ban,
            'price' => $prices->min() ?? (float) $product->gia_co_ban,
            'base_price' => (float) $product->gia_co_ban,
            'stock' => $product->bienThes->where('trang_thai', true)->sum('so_luong_ton'),
            'variant_count' => $product->bienThes->count(),
            'status' => $product->trang_thai,
            'sold' => max(0, (int) DB::table('chi_tiet_don_hang as order_items')
                ->join('bien_the_san_pham as variants', 'order_items.ma_bien_the', '=', 'variants.ma_bt')
                ->join('don_hang as orders', 'order_items.ma_dh', '=', 'orders.ma_dh')
                ->where('variants.ma_sp', $product->ma_sp)
                ->where('orders.trang_thai', 'delivered')
                ->sum('order_items.so_luong') - (int) DB::table('chi_tiet_tra_hang as return_items')
                ->join('yeu_cau_tra_hang as returns', 'return_items.ma_yeu_cau', '=', 'returns.ma_yeu_cau')
                ->where('return_items.ma_sp', $product->ma_sp)
                ->whereIn('returns.trang_thai', ['received', 'completed'])
                ->sum('return_items.so_luong')),
        ];
    }

    private function formatDetail(SanPham $product): array
    {
        return array_merge($this->formatSummary($product), [
            'description' => $product->mo_ta,
            'images' => $product->hinhAnhs->map(function (HinhAnhSanPham $image) {
                $urls = app(CloudinaryMediaService::class)->urls($image->url, $image->provider);
                return [
                'id' => $image->ma_anh,
                // url remains the original for compatibility with the editor payload.
                'url' => $urls['original_url'],
                ...$urls,
                'provider' => $image->provider,
                'public_id' => $image->cloudinary_public_id,
                'width' => $image->chieu_rong,
                'height' => $image->chieu_cao,
                'is_primary' => $image->anh_chinh,
                'variant_id' => $image->ma_bt,
                'order' => $image->thu_tu,
            ];
            })->values(),
            'variants' => $product->bienThes->map(fn (BienTheSanPham $variant) => [
                'id' => $variant->ma_bt,
                'sku' => $variant->sku,
                'price' => (float) $variant->gia_ban,
                'stock' => $variant->so_luong_ton,
                'low_stock_threshold' => $variant->nguong_canh_bao_ton,
                'active' => $variant->trang_thai,
                'attributes' => $variant->giaTriThuocTinhs->map(fn ($value) => [
                    'name' => $value->thuocTinh?->ten_tt,
                    'value' => $value->gia_tri,
                    'type' => $value->thuocTinh?->loai_hien_thi ?? 'select',
                    'color_code' => $value->ma_mau,
                ])->values(),
            ])->values(),
        ]);
    }
}
