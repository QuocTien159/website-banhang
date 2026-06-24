<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\SanPham;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = SanPham::with(['danhMuc', 'anhChinh', 'bienThes'])
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

        // Filter by price
        if ($minPrice = $request->input('min_price')) {
            $query->where('gia_co_ban', '>=', $minPrice);
        }
        if ($maxPrice = $request->input('max_price')) {
            $query->where('gia_co_ban', '<=', $maxPrice);
        }

        // Featured only
        if ($request->boolean('featured')) {
            $query->whereHas('bienThes', fn($q) => $q->where('so_luong_ton', '>', 0));
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
            ],
        ]);
    }

    public function show(string $id)
    {
        $product = SanPham::with([
            'danhMuc',
            'hinhAnhs',
            'bienThes.giaTriThuocTinhs.thuocTinh',
            'danhGias.khachHang',
        ])->where('ma_sp', $id)
          ->where('trang_thai', 'active')
          ->firstOrFail();

        return response()->json($this->formatProductDetail($product));
    }

    private function formatProduct(SanPham $p): array
    {
        $minVariantPrice = $p->bienThes->where('trang_thai', true)->min('gia_ban');
        $totalStock = $p->bienThes->where('trang_thai', true)->sum('so_luong_ton');
        $avgRating = \App\Models\DanhGia::where('ma_sp', $p->ma_sp)->avg('so_sao');
        $reviewCount = \App\Models\DanhGia::where('ma_sp', $p->ma_sp)->count();

        return [
            'id'           => $p->ma_sp,
            'name'         => $p->ten_sp,
            'category'     => $p->danhMuc?->ten_dm,
            'category_id'  => $p->ma_dm,
            'price'        => (float)($minVariantPrice ?? $p->gia_co_ban),
            'original_price' => (float)$p->gia_co_ban,
            'image'        => $p->anhChinh?->url,
            'stock'        => $totalStock,
            'rating'       => round($avgRating ?? 0, 1),
            'review_count' => $reviewCount,
            'featured'     => $totalStock > 10,
            'sold'         => $this->getSold($p->ma_sp),
        ];
    }

    private function formatProductDetail(SanPham $p): array
    {
        $avgRating = $p->danhGias->avg('so_sao');
        $totalStock = $p->bienThes->where('trang_thai', true)->sum('so_luong_ton');

        return [
            'id'           => $p->ma_sp,
            'name'         => $p->ten_sp,
            'category'     => $p->danhMuc?->ten_dm,
            'category_id'  => $p->ma_dm,
            'price'        => (float)($p->bienThes->where('trang_thai', true)->min('gia_ban') ?? $p->gia_co_ban),
            'original_price' => (float)$p->gia_co_ban,
            'image'        => $p->hinhAnhs->where('anh_chinh', true)->first()?->url,
            'images'       => $p->hinhAnhs->pluck('url'),
            'description'  => $p->mo_ta,
            'specs'        => $this->buildSpecs($p),
            'stock'        => $totalStock,
            'rating'       => round($avgRating ?? 0, 1),
            'review_count' => $p->danhGias->count(),
            'featured'     => $totalStock > 10,
            'sold'         => $this->getSold($p->ma_sp),
            'variants'     => $p->bienThes->where('trang_thai', true)->map(fn($bt) => [
                'id'         => $bt->ma_bt,
                'sku'        => $bt->sku,
                'price'      => (float)$bt->gia_ban,
                'stock'      => $bt->so_luong_ton,
                'attributes' => $bt->giaTriThuocTinhs->map(fn($gt) => [
                    'name'  => $gt->thuocTinh->ten_tt,
                    'value' => $gt->gia_tri,
                ])->values(),
            ])->values(),
            'reviews' => $p->danhGias->sortByDesc('ngay_danh_gia')->take(10)->map(fn($dg) => [
                'id'      => $dg->ma_danh_gia,
                'name'    => $dg->khachHang?->ten_kh ?? 'Ẩn danh',
                'rating'  => $dg->so_sao,
                'date'    => $dg->ngay_danh_gia?->format('d/m/Y'),
                'comment' => $dg->noi_dung,
            ])->values(),
        ];
    }

    private function buildSpecs(SanPham $p): array
    {
        // Collect unique attribute types from all variants
        $specs = collect();
        foreach ($p->bienThes as $bt) {
            foreach ($bt->giaTriThuocTinhs as $gt) {
                $name = $gt->thuocTinh->ten_tt;
                if (!$specs->has($name)) {
                    $specs[$name] = [];
                }
                $specs[$name][] = $gt->gia_tri;
            }
        }

        return $specs->map(fn($vals, $name) => [
            'label' => $name,
            'value' => implode(', ', array_unique($vals)),
        ])->values()->toArray();
    }

    private function getSold(string $productId): int
    {
        return \DB::table('chi_tiet_don_hang as ctdh')
            ->join('bien_the_san_pham as bt', 'ctdh.ma_bien_the', '=', 'bt.ma_bt')
            ->join('don_hang as dh', 'ctdh.ma_dh', '=', 'dh.ma_dh')
            ->where('bt.ma_sp', $productId)
            ->where('dh.trang_thai', '!=', 'cancelled')
            ->sum('ctdh.so_luong');
    }
}
