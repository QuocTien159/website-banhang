<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\BienTheSanPham;
use App\Models\HinhAnhSanPham;
use App\Models\SanPham;
use App\Services\CloudinaryMediaService;
use App\Support\OrderStatus;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = SanPham::with([
            'danhMuc',
            'anhChinh',
            'bienThes.giaTriThuocTinhs.thuocTinh',
            'bienThes.hinhAnhs',
        ])
            ->where('trang_thai', 'active');

        // Search by name
        if ($search = $request->input('search')) {
            $query->where('ten_sp', 'like', "%{$search}%");
        }

        // Filter by category
        if ($category = $request->input('category')) {
            $query->whereHas('danhMuc', fn($q) => $q->where('ten_dm', $category));
        }

        // Filter by category ID
        if ($categoryId = $request->input('category_id')) {
            $query->where('ma_dm', $categoryId);
        }

        $attributeFilters = [
            'brand' => 'Thương hiệu',
            'color' => 'Màu sắc',
            'size' => 'Kích thước',
            'weight' => 'Khối lượng',
            'resistance' => 'Độ đàn hồi',
        ];
        foreach ($attributeFilters as $parameter => $attributeName) {
            if ($value = $request->input($parameter)) {
                if ($parameter === 'brand' && $value === 'Khác') {
                    $query->whereHas('bienThes.giaTriThuocTinhs', function ($attributeQuery) use ($attributeName) {
                        $attributeQuery
                            ->whereNotIn('gia_tri', ['Nike', 'Adidas', 'Puma'])
                            ->whereHas('thuocTinh', fn ($typeQuery) => $typeQuery->where('ten_tt', $attributeName));
                    });
                } else {
                    $query->whereHas('bienThes.giaTriThuocTinhs', function ($attributeQuery) use ($attributeName, $value) {
                        $attributeQuery
                            ->where('gia_tri', $value)
                            ->whereHas('thuocTinh', fn ($typeQuery) => $typeQuery->where('ten_tt', $attributeName));
                    });
                }
            }
        }

        // Filter by price
        if ($request->filled('min_price') || $request->filled('max_price')) {
            $query->whereHas('bienThes', function ($variantQuery) use ($request) {
                $variantQuery->where('trang_thai', true)
                    ->where('trang_thai_ban', 'active')
                    ->when(
                        $request->filled('min_price'),
                        fn ($priceQuery) => $priceQuery->where('gia_ban', '>=', $request->input('min_price'))
                    )
                    ->when(
                        $request->filled('max_price'),
                        fn ($priceQuery) => $priceQuery->where('gia_ban', '<=', $request->input('max_price'))
                    );
            });
        }

        // Featured only
        if ($request->boolean('featured')) {
            $query->whereHas('bienThes', fn($q) => $q
                ->where('trang_thai', true)
                ->where('trang_thai_ban', 'active')
                ->where('so_luong_ton', '>', 0));
        }

        // Sort
        switch ($request->input('sort', 'newest')) {
            case 'price_asc':
                $query->orderBy('gia_co_ban', 'asc');
                break;
            case 'price_desc':
                $query->orderBy('gia_co_ban', 'desc');
                break;
            case 'name':
                $query->orderBy('ten_sp', 'asc');
                break;
            default: // newest
                $query->orderBy('ngay_tao', 'desc');
                break;
        }

        $products = $query->paginate($request->input('per_page', 16));

        return response()->json([
            'data' => $products->getCollection()->map(fn($p) => $this->formatProduct($p)),
            'meta' => [
                'current_page' => $products->currentPage(),
                'last_page'    => $products->lastPage(),
                'total'        => $products->total(),
                'filters'      => $this->availableFilters(),
            ],
        ]);
    }

    public function show(string $id)
    {
        $product = SanPham::with([
            'danhMuc',
            'hinhAnhs',
            'hinhAnhs.bienThe.giaTriThuocTinhs.thuocTinh',
            'bienThes.giaTriThuocTinhs.thuocTinh',
            'bienThes.hinhAnhs',
            'danhGias.khachHang',
            'danhGias.hinhAnhs',
        ])->where('ma_sp', $id)
          ->where('trang_thai', 'active')
          ->firstOrFail();

        return response()->json($this->formatProductDetail($product));
    }

    private function formatProduct(SanPham $p): array
    {
        $sellableVariants = $p->bienThes->filter(fn (BienTheSanPham $variant) => $variant->isSellable());
        $minVariantPrice = $sellableVariants->min('gia_ban');
        $totalStock = $sellableVariants->sum('so_luong_ton');
        $avgRating = \App\Models\DanhGia::where('ma_sp', $p->ma_sp)->where('trang_thai', 'approved')->avg('so_sao');
        $reviewCount = \App\Models\DanhGia::where('ma_sp', $p->ma_sp)->where('trang_thai', 'approved')->count();

        $imageUrls = app(CloudinaryMediaService::class)->urls($p->anhChinh?->original_url ?? $p->anhChinh?->url, $p->anhChinh?->provider, $this->crop($p->anhChinh));

        return [
            'id'           => $p->ma_sp,
            'name'         => $p->ten_sp,
            'category'     => $p->danhMuc?->ten_dm,
            'category_id'  => $p->ma_dm,
            'price'        => (float)($minVariantPrice ?? $p->gia_co_ban),
            'original_price' => (float)$p->gia_co_ban,
            'image'        => $imageUrls['list_url'],
            'image_urls'   => $imageUrls,
            'stock'        => $totalStock,
            'rating'       => round($avgRating ?? 0, 1),
            'review_count' => $reviewCount,
            'featured'     => $totalStock > 10,
            'sold'         => $this->getSold($p->ma_sp),
            'brand'        => $this->attributeValue($p, 'Thương hiệu'),
            'attributes'   => $this->attributeSummary($p),
        ];
    }

    private function formatProductDetail(SanPham $p): array
    {
        $approvedReviews = $p->danhGias->where('trang_thai', 'approved');
        $avgRating = $approvedReviews->avg('so_sao');
        $sellableVariants = $p->bienThes->filter(fn (BienTheSanPham $variant) => $variant->isSellable());
        $totalStock = $sellableVariants->sum('so_luong_ton');

        $media = app(CloudinaryMediaService::class);
        $primaryImage = $p->hinhAnhs->where('anh_chinh', true)->first();
        $imageUrls = $media->urls($primaryImage?->original_url ?? $primaryImage?->url, $primaryImage?->provider, $this->crop($primaryImage));

        return [
            'id'           => $p->ma_sp,
            'name'         => $p->ten_sp,
            'category'     => $p->danhMuc?->ten_dm,
            'category_id'  => $p->ma_dm,
            'price'        => (float)($sellableVariants->min('gia_ban') ?? $p->gia_co_ban),
            'original_price' => (float)$p->gia_co_ban,
            'image'        => $imageUrls['detail_url'],
            'image_urls'   => $imageUrls,
            'images'       => $p->hinhAnhs->map(function ($image) use ($media) {
                $urls = $media->urls($image->original_url ?? $image->url, $image->provider, $this->crop($image));
                return [
                    'id' => $image->ma_anh,
                    ...$urls,
                    'width' => $image->chieu_rong,
                    'height' => $image->chieu_cao,
                    'variant_id' => $image->ma_bt,
                    'is_primary' => $image->anh_chinh,
                ];
            })->values(),
            'description'  => $p->mo_ta,
            'specs'        => $this->buildSpecs($p),
            'stock'        => $totalStock,
            'rating'       => round($avgRating ?? 0, 1),
            'review_count' => $approvedReviews->count(),
            'featured'     => $totalStock > 10,
            'sold'         => $this->getSold($p->ma_sp),
            'brand'        => $this->attributeValue($p, 'Thương hiệu'),
            'attributes'   => $this->attributeSummary($p),
            'required_attributes' => $this->requiredAttributes($p),
            'variants'     => $sellableVariants->map(function (BienTheSanPham $bt) use ($p, $media) {
                $image = $this->imageForVariant($p, $bt);
                return [
                    'id'         => $bt->ma_bt,
                    'sku'        => $bt->sku,
                    'price'      => (float) $bt->gia_ban,
                    'stock'      => $bt->so_luong_ton,
                    'stock_status' => $bt->stockStatus(),
                    'image'      => $media->urls($image?->original_url ?? $image?->url, $image?->provider, $this->crop($image))['detail_url'],
                    'attributes' => $bt->giaTriThuocTinhs->map(fn($gt) => [
                        'name'  => $gt->thuocTinh->ten_tt,
                        'value' => $gt->gia_tri,
                    ])->values(),
                ];
            })->values(),
            'reviews' => $approvedReviews->sortByDesc('ngay_danh_gia')->take(10)->map(fn($dg) => [
                'id'      => $dg->ma_danh_gia,
                'name'    => $dg->khachHang?->ten_kh ?? 'Ẩn danh',
                'rating'  => $dg->so_sao,
                'date'    => $dg->ngay_danh_gia?->format('d/m/Y'),
                'comment' => $dg->noi_dung,
                'images' => $dg->hinhAnhs?->pluck('url_anh') ?? [],
                'admin_reply' => $dg->phan_hoi_admin,
            ])->values(),
        ];
    }

    private function buildSpecs(SanPham $p): array
    {
        // Collect unique attribute types from all variants
        $specs = [];
        foreach ($p->bienThes as $bt) {
            foreach ($bt->giaTriThuocTinhs as $gt) {
                $name = $gt->thuocTinh->ten_tt;
                if (!isset($specs[$name])) {
                    $specs[$name] = [];
                }
                $specs[$name][] = $gt->gia_tri;
            }
        }

        return collect($specs)->map(fn($vals, $name) => [
            'label' => $name,
            'value' => implode(', ', array_unique($vals)),
        ])->values()->toArray();
    }

    private function imageForVariant(SanPham $product, BienTheSanPham $variant): ?HinhAnhSanPham
    {
        $exact = $variant->hinhAnhs->first();
        if ($exact) return $exact;

        $targetVisualValues = $variant->giaTriThuocTinhs
            ->filter(fn ($value) => $this->isVisualAttribute($value->thuocTinh?->ten_tt))
            ->mapWithKeys(fn ($value) => [$value->thuocTinh?->ten_tt => $value->gia_tri])
            ->all();

        if ($targetVisualValues) {
            $matched = $product->hinhAnhs
                ->whereNotNull('ma_bt')
                ->filter(function (HinhAnhSanPham $image) use ($product, $targetVisualValues) {
                    $source = $product->bienThes->firstWhere('ma_bt', $image->ma_bt);
                    if (!$source) return false;
                    $sourceVisualValues = $source->giaTriThuocTinhs
                        ->filter(fn ($value) => $this->isVisualAttribute($value->thuocTinh?->ten_tt))
                        ->mapWithKeys(fn ($value) => [$value->thuocTinh?->ten_tt => $value->gia_tri])
                        ->all();
                    return count(array_intersect_assoc($sourceVisualValues, $targetVisualValues)) > 0;
                })
                ->sortBy('thu_tu')
                ->first();
            if ($matched) return $matched;
        }

        return $product->hinhAnhs->whereNull('ma_bt')->sortBy('thu_tu')->first()
            ?? $product->hinhAnhs->sortBy('thu_tu')->first();
    }

    private function isVisualAttribute(?string $name): bool
    {
        $name = mb_strtolower($name ?? '');
        return str_contains($name, 'màu')
            || str_contains($name, 'color')
            || str_contains($name, 'kiểu')
            || str_contains($name, 'style')
            || str_contains($name, 'họa tiết')
            || str_contains($name, 'pattern');
    }

    private function attributeValue(SanPham $product, string $name): ?string
    {
        return $product->bienThes
            ->flatMap->giaTriThuocTinhs
            ->first(fn ($value) => $value->thuocTinh?->ten_tt === $name)
            ?->gia_tri;
    }

    private function attributeSummary(SanPham $product): array
    {
        return $product->bienThes
            ->flatMap->giaTriThuocTinhs
            ->groupBy(fn ($value) => $value->thuocTinh?->ten_tt)
            ->map(fn ($values, $name) => [
                'name' => $name,
                'values' => $values->pluck('gia_tri')->unique()->values(),
            ])
            ->values()
            ->all();
    }

    private function requiredAttributes(SanPham $product): array
    {
        return collect($this->attributeSummary($product))
            ->filter(fn (array $attribute) => count($attribute['values']) > 1)
            ->pluck('name')
            ->values()
            ->all();
    }

    private function availableFilters(): array
    {
        $names = ['Thương hiệu', 'Màu sắc', 'Kích thước', 'Khối lượng', 'Độ đàn hồi'];

        return \App\Models\ThuocTinh::with([
            'giaTriThuocTinhs' => fn ($query) => $query
                ->whereHas('bienThes.sanPham', fn ($productQuery) => $productQuery->where('trang_thai', 'active'))
                ->orderBy('gia_tri'),
        ])
            ->whereIn('ten_tt', $names)
            ->get()
            ->mapWithKeys(fn ($attribute) => [
                $attribute->ten_tt => $attribute->giaTriThuocTinhs->pluck('gia_tri')->unique()->values(),
            ])
            ->all();
    }

    private function getSold(string $productId): int
    {
        $sold = \DB::table('chi_tiet_don_hang as ctdh')
            ->join('bien_the_san_pham as bt', 'ctdh.ma_bien_the', '=', 'bt.ma_bt')
            ->join('don_hang as dh', 'ctdh.ma_dh', '=', 'dh.ma_dh')
            ->where('bt.ma_sp', $productId)
            ->whereIn('dh.trang_thai', OrderStatus::FULFILLED)
            ->sum('ctdh.so_luong');

        $returned = \DB::table('chi_tiet_tra_hang as ctth')
            ->join('yeu_cau_tra_hang as ycth', 'ctth.ma_yeu_cau', '=', 'ycth.ma_yeu_cau')
            ->where('ctth.ma_sp', $productId)
            ->whereIn('ycth.trang_thai', ['received', 'completed'])
            ->sum('ctth.so_luong');

        return max(0, (int) $sold - (int) $returned);
    }

    private function crop($image): array
    {
        if (!$image) return [];
        return ['x' => $image->crop_x, 'y' => $image->crop_y, 'width' => $image->crop_width, 'height' => $image->crop_height, 'rotation' => $image->goc_xoay];
    }
}
