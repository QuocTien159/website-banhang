<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BienTheSanPham;
use App\Models\GiaTriThuocTinh;
use App\Models\HinhAnhSanPham;
use App\Models\SanPham;
use App\Models\ThuocTinh;
use App\Services\CloudinaryMediaService;
use App\Support\OrderStatus;
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
        $query = SanPham::with(['danhMuc', 'anhChinh'])
            ->withCount('bienThes')
            ->withCount(['bienThes as low_stock_variant_count' => fn ($variant) => $variant
                ->where('trang_thai', true)
                ->where('trang_thai_ban', 'active')
                ->whereColumn('so_luong_ton', '<=', 'nguong_canh_bao_ton')])
            ->withMin(['bienThes as min_variant_price' => fn ($variant) => $variant->where('trang_thai_ban', 'active')], 'gia_ban')
            ->withMax(['bienThes as max_variant_price' => fn ($variant) => $variant->where('trang_thai_ban', 'active')], 'gia_ban');

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
            'hinhAnhs.bienThe.giaTriThuocTinhs.thuocTinh',
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
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480', 'dimensions:min_width=800,min_height=800,max_width=10000,max_height=10000'],
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
        if (!empty($data['variants'])) {
            $this->validateVariantCombinations($data['variants']);
        }

        $product = DB::transaction(function () use ($data, $request) {
            $product = SanPham::create([
                'ma_dm' => $data['category_id'],
                'ten_sp' => trim($data['name']),
                'mo_ta' => $data['description'] ?? null,
                'gia_co_ban' => $data['base_price'],
                'trang_thai' => $data['status'],
                'ngay_tao' => now(),
                'ngay_cap_nhat' => now(),
            ]);

            if (!empty($data['variants'])) {
                $this->syncVariants($product, $data['variants']);
            } else {
                $this->syncConfiguration($product, $data);
            }
            $this->syncImages($product, $data['images'], $request->user()->ma_kh);

            return $product;
        });

        return response()->json(
            $this->formatDetail($product->load(['danhMuc', 'hinhAnhs.bienThe.giaTriThuocTinhs.thuocTinh', 'bienThes.giaTriThuocTinhs.thuocTinh'])),
            201
        );
    }

    public function update(Request $request, string $id)
    {
        $this->abortUnlessAdmin($request);

        $product = SanPham::with(['hinhAnhs', 'bienThes.chiTietDonHangs'])->findOrFail($id);
        $data = $this->validateProduct($request, $product);
        if (!empty($data['variants'])) {
            $this->validateVariantCombinations($data['variants'], $product);
        }

        DB::transaction(function () use ($product, $data, $request) {
            $product->update([
                'ma_dm' => $data['category_id'],
                'ten_sp' => trim($data['name']),
                'mo_ta' => $data['description'] ?? null,
                'gia_co_ban' => $data['base_price'],
                'trang_thai' => $data['status'],
                'ngay_cap_nhat' => now(),
            ]);

            if (!empty($data['variants'])) {
                $this->syncVariants($product, $data['variants']);
            } elseif (array_key_exists('variant_axes', $data) || array_key_exists('shared_attributes', $data)) {
                $this->syncConfiguration($product, $data);
            }
            $this->syncImages($product, $data['images'], $request->user()->ma_kh);
        });

        return response()->json($this->formatDetail(
            $product->fresh(['danhMuc', 'hinhAnhs.bienThe.giaTriThuocTinhs.thuocTinh', 'bienThes.giaTriThuocTinhs.thuocTinh'])
        ));
    }

    public function destroy(string $id)
    {
        $product = SanPham::findOrFail($id);
        $product->update(['trang_thai' => 'inactive', 'ngay_cap_nhat' => now()]);
        $product->bienThes()->update(['trang_thai' => false, 'trang_thai_ban' => 'inactive', 'ngay_cap_nhat' => now()]);

        return response()->json(['message' => 'Sản phẩm và các biến thể đã được ngừng bán.']);
    }

    public function hide(Request $request, string $id)
    {
        $product = SanPham::findOrFail($id);
        if ($product->trang_thai !== 'active') {
            return response()->json(['message' => 'Sáº£n pháº©m khÃ´ng Ä‘ang hiá»ƒn thá»‹.'], 422);
        }

        $product->update(['trang_thai' => 'inactive', 'ngay_cap_nhat' => now()]);
        $product->bienThes()->update(['trang_thai' => false, 'trang_thai_ban' => 'inactive', 'ngay_cap_nhat' => now()]);

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

        $data = $request->validate([
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
            'images.*.crop' => ['nullable', 'array'],
            'images.*.crop.x' => ['nullable', 'integer', 'min:0'],
            'images.*.crop.y' => ['nullable', 'integer', 'min:0'],
            'images.*.crop.width' => ['nullable', 'integer', 'min:1'],
            'images.*.crop.height' => ['nullable', 'integer', 'min:1'],
            'images.*.crop.rotation' => ['nullable', 'numeric', 'between:-360,360'],
            'images.*.variant_id' => ['nullable', Rule::in($variantIds)],
            'images.*.variant_sku' => ['nullable', 'string', 'max:50'],
            'images.*.variant_attributes' => ['nullable', 'array', 'min:1'],
            'images.*.variant_attributes.*.name' => ['required_with:images.*.variant_attributes', 'string', 'max:50'],
            'images.*.variant_attributes.*.value' => ['required_with:images.*.variant_attributes', 'string', 'max:50'],
            'images.*.is_primary' => ['required', 'boolean'],
            'variants' => ['nullable', 'array', 'min:1', 'max:100'],
            'variants.*.id' => ['nullable', Rule::in($variantIds)],
            'variants.*.sku' => ['required', 'string', 'max:50'],
            'variants.*.price' => ['required', 'numeric', 'min:0'],
            'variants.*.stock' => ['required', 'integer', 'min:0'],
            'variants.*.low_stock_threshold' => ['nullable', 'integer', 'min:0'],
            'variants.*.active' => ['required', 'boolean'],
            'variants.*.attributes' => ['required', 'array', 'min:1'],
            'variants.*.attributes.*.name' => ['required', 'string', 'max:50'],
            'variants.*.attributes.*.value' => ['required', 'string', 'max:50'],
            'shared_attributes' => ['nullable', 'array', 'max:8'],
            'shared_attributes.*.name' => ['required_with:shared_attributes', 'string', 'max:50'],
            'shared_attributes.*.value' => ['required_with:shared_attributes', 'string', 'max:50'],
            'variant_axes' => ['nullable', 'array', 'max:4'],
            'variant_axes.*.name' => ['required_with:variant_axes', 'string', 'max:50'],
            'variant_axes.*.values' => ['required_with:variant_axes', 'array', 'min:1', 'max:20'],
            'variant_axes.*.values.*' => ['required', 'string', 'max:50'],
        ]);

        if (empty($data['variants']) && empty($data['variant_axes']) && empty($data['shared_attributes'])) {
            throw ValidationException::withMessages([
                'variant_axes' => 'Chọn ít nhất một thuộc tính dùng chung hoặc thuộc tính tạo biến thể.',
            ]);
        }

        return $data;
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
            if (!$variantId && !empty($imageData['variant_attributes'])) {
                $variantId = $this->findVariantIdByAttributes($product, $imageData['variant_attributes']);
                if (!$variantId) {
                    throw ValidationException::withMessages([
                        'images' => 'Không tìm thấy SKU thực tế khớp với tổ hợp thuộc tính được gán cho ảnh.',
                    ]);
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
                    'vai_tro_anh' => $variantId ? 'variant' : 'product',
                    'thu_tu' => $order,
                ]);
            } else {
                $asset = $this->verifiedImageAsset($imageData, 'products', $actorId);
                HinhAnhSanPham::create([
                    'ma_sp' => $product->ma_sp,
                    'ma_bt' => $variantId,
                    'url' => $asset['url'],
                    'original_url' => $asset['url'],
                    'provider' => $asset['provider'],
                    'cloudinary_public_id' => $asset['public_id'] ?? null,
                    'chieu_rong' => $asset['width'] ?? null,
                    'chieu_cao' => $asset['height'] ?? null,
                    'kich_thuoc_byte' => $asset['bytes'] ?? null,
                    'dinh_dang' => $asset['format'] ?? null,
                    'crop_x' => $imageData['crop']['x'] ?? null,
                    'crop_y' => $imageData['crop']['y'] ?? null,
                    'crop_width' => $imageData['crop']['width'] ?? null,
                    'crop_height' => $imageData['crop']['height'] ?? null,
                    'goc_xoay' => $imageData['crop']['rotation'] ?? 0,
                    'ty_le_khung_hinh' => '1:1',
                    'vai_tro_anh' => $variantId ? 'variant' : 'product',
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
                'gia_niem_yet' => $variantData['list_price'] ?? $variantData['price'],
                'nguong_canh_bao_ton' => $variantData['low_stock_threshold'] ?? 5,
                'trang_thai' => $variantData['active'],
                'trang_thai_ban' => $variantData['active'] ? 'active' : 'inactive',
                'ngay_cap_nhat' => now(),
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

    private function syncConfiguration(SanPham $product, array $data): void
    {
        $shared = collect($data['shared_attributes'] ?? [])
            ->map(fn (array $attribute) => ['name' => trim($attribute['name']), 'value' => trim($attribute['value'])])
            ->values()
            ->all();
        $axes = collect($data['variant_axes'] ?? [])
            ->map(fn (array $axis) => [
                'name' => trim($axis['name']),
                'values' => collect($axis['values'])->map(fn ($value) => trim($value))->filter()->unique()->values()->all(),
            ])
            ->values()
            ->all();
        $names = collect([...$shared, ...collect($axes)->map(fn ($axis) => ['name' => $axis['name']])->all()])->pluck('name');

        if ($names->filter()->unique()->count() !== $names->filter()->count() || $axes !== [] && collect($axes)->contains(fn ($axis) => count($axis['values']) === 0)) {
            throw ValidationException::withMessages(['variant_axes' => 'Mỗi loại thuộc tính chỉ được chọn một lần và phải có ít nhất một giá trị.']);
        }

        $combinations = $axes === [] ? [[]] : $this->cartesian($axes);
        foreach ($combinations as $combination) {
            $attributes = [...$shared, ...$combination];
            $signature = BienTheSanPham::signatureFor($attributes);
            if ($product->bienThes()->where('variant_signature', $signature)->exists()) {
                continue;
            }

            $valueIds = $this->attributeValueIds($attributes);
            $variant = $product->bienThes()->create([
                'sku' => $this->nextIncompleteSku($product),
                'variant_signature' => $signature,
                'gia_niem_yet' => $product->gia_co_ban,
                'gia_ban' => $product->gia_co_ban,
                'so_luong_ton' => 0,
                'nguong_canh_bao_ton' => 5,
                'trang_thai' => false,
                'trang_thai_ban' => 'incomplete',
                'ngay_cap_nhat' => now(),
            ]);
            $variant->giaTriThuocTinhs()->sync($valueIds);
        }
    }

    private function cartesian(array $axes): array
    {
        return array_reduce($axes, function (array $rows, array $axis): array {
            return collect($rows)->flatMap(fn (array $row) => collect($axis['values'])
                ->map(fn (string $value) => [...$row, ['name' => $axis['name'], 'value' => $value]]))
                ->values()
                ->all();
        }, [[]]);
    }

    private function attributeValueIds(array $attributes): array
    {
        return collect($attributes)->map(function (array $attribute) {
            $type = ThuocTinh::where('ten_tt', $attribute['name'])->first();
            $value = $type
                ? GiaTriThuocTinh::where('ma_tt', $type->ma_tt)->where('gia_tri', $attribute['value'])->first()
                : null;
            if (!$value) {
                throw ValidationException::withMessages([
                    'variant_axes' => "Không tìm thấy giá trị thuộc tính {$attribute['name']}: {$attribute['value']}.",
                ]);
            }

            return $value->ma_gt;
        })->all();
    }

    private function nextIncompleteSku(SanPham $product): string
    {
        $prefix = $product->ma_sp.'-DRAFT-';
        $number = 1;
        do {
            $sku = $prefix.str_pad((string) $number++, 3, '0', STR_PAD_LEFT);
        } while (BienTheSanPham::where('sku', $sku)->exists());

        return $sku;
    }

    private function findVariantIdByAttributes(SanPham $product, array $attributes): ?string
    {
        return $product->bienThes()
            ->where('variant_signature', BienTheSanPham::signatureFor($attributes))
            ->value('ma_bt');
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
        $minPrice = (float) ($product->min_variant_price ?? $product->gia_co_ban);
        $maxPrice = (float) ($product->max_variant_price ?? $product->gia_co_ban);
        $imageUrls = app(CloudinaryMediaService::class)->urls($product->anhChinh?->original_url ?? $product->anhChinh?->url, $product->anhChinh?->provider, $this->crop($product->anhChinh));
        return [
            'id' => $product->ma_sp,
            'name' => $product->ten_sp,
            'category' => $product->danhMuc?->ten_dm,
            'category_id' => $product->ma_dm,
            'image' => $imageUrls['list_url'],
            'image_urls' => $imageUrls,
            'min_price' => $minPrice,
            'max_price' => $maxPrice,
            'price' => $minPrice,
            'base_price' => (float) $product->gia_co_ban,
            'variant_count' => $product->variants_count ?? $product->bienThes->count(),
            'low_stock_variant_count' => $product->low_stock_variant_count ?? 0,
            'status' => $product->trang_thai,
            'updated_at' => ($product->ngay_cap_nhat ?? $product->ngay_tao)?->toISOString(),
            'sold' => max(0, (int) DB::table('chi_tiet_don_hang as order_items')
                ->join('bien_the_san_pham as variants', 'order_items.ma_bien_the', '=', 'variants.ma_bt')
                ->join('don_hang as orders', 'order_items.ma_dh', '=', 'orders.ma_dh')
                ->where('variants.ma_sp', $product->ma_sp)
                ->whereIn('orders.trang_thai', OrderStatus::FULFILLED)
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
                $urls = app(CloudinaryMediaService::class)->urls($image->original_url ?? $image->url, $image->provider, $this->crop($image));
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
                'variant_attributes' => $image->bienThe?->giaTriThuocTinhs->map(fn ($value) => [
                    'name' => $value->thuocTinh?->ten_tt,
                    'value' => $value->gia_tri,
                ])->values(),
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
            'variant_options' => $this->variantOptions($product),
            'configuration' => $this->configuration($product),
        ]);
    }

    private function variantOptions(SanPham $product): array
    {
        return $product->bienThes
            ->flatMap->giaTriThuocTinhs
            ->groupBy(fn (GiaTriThuocTinh $value) => $value->thuocTinh?->ten_tt)
            ->map(fn ($values, $name) => [
                'name' => $name,
                'values' => $values->pluck('gia_tri')->unique()->sort()->values(),
            ])
            ->filter(fn (array $option) => count($option['values']) > 1)
            ->values()
            ->all();
    }

    private function configuration(SanPham $product): array
    {
        $groups = $product->bienThes
            ->flatMap->giaTriThuocTinhs
            ->groupBy(fn (GiaTriThuocTinh $value) => $value->thuocTinh?->ten_tt);

        return [
            'shared_attributes' => $groups
                ->filter(fn ($values) => $values->pluck('gia_tri')->unique()->count() === 1)
                ->map(fn ($values, $name) => ['name' => $name, 'value' => $values->first()->gia_tri])
                ->values(),
            'variant_axes' => $groups
                ->filter(fn ($values) => $values->pluck('gia_tri')->unique()->count() > 1)
                ->map(fn ($values, $name) => ['name' => $name, 'values' => $values->pluck('gia_tri')->unique()->sort()->values()])
                ->values(),
        ];
    }

    private function crop(?HinhAnhSanPham $image): array
    {
        if (!$image) return [];
        return ['x' => $image->crop_x, 'y' => $image->crop_y, 'width' => $image->crop_width, 'height' => $image->crop_height, 'rotation' => $image->goc_xoay];
    }
}
