<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\SanPham;
use App\Models\BienTheSanPham;
use App\Models\HinhAnhSanPham;
use App\Models\DanhMuc;
use App\Models\ThuocTinh;
use App\Models\GiaTriThuocTinh;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminProductController extends Controller
{
    /** GET /api/admin/products */
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

        $products = $query->orderBy('ngay_tao', 'desc')->paginate(20);

        return response()->json([
            'data' => $products->getCollection()->map(fn($p) => $this->formatAdminProduct($p)),
            'meta' => [
                'total' => $products->total(),
                'current_page' => $products->currentPage(),
                'last_page' => $products->lastPage(),
            ],
        ]);
    }

    /** POST /api/admin/products */
    public function store(Request $request)
    {
        $data = $request->validate([
            'ten_sp'     => 'required|string|max:100',
            'ma_dm'      => 'required|exists:danh_muc,ma_dm',
            'mo_ta'      => 'nullable|string',
            'gia_co_ban' => 'required|numeric|min:0',
            'trang_thai' => 'required|in:active,inactive,out_of_stock',
            'images'     => 'nullable|array',
            'images.*'   => 'url',
            'variants'   => 'nullable|array',
            'variants.*.sku'          => 'required|string|unique:bien_the_san_pham,sku',
            'variants.*.gia_ban'      => 'required|numeric|min:0',
            'variants.*.so_luong_ton' => 'required|integer|min:0',
            'variants.*.attributes'   => 'nullable|array',
        ]);

        $product = DB::transaction(function () use ($data) {
            $sp = SanPham::create([
                'ma_dm'      => $data['ma_dm'],
                'ten_sp'     => $data['ten_sp'],
                'mo_ta'      => $data['mo_ta'] ?? null,
                'gia_co_ban' => $data['gia_co_ban'],
                'trang_thai' => $data['trang_thai'],
                'ngay_tao'   => now(),
            ]);

            // Images
            if (!empty($data['images'])) {
                foreach ($data['images'] as $i => $url) {
                    HinhAnhSanPham::create([
                        'ma_sp'    => $sp->ma_sp,
                        'url'      => $url,
                        'anh_chinh' => $i === 0,
                    ]);
                }
            }

            // Variants
            if (!empty($data['variants'])) {
                foreach ($data['variants'] as $vData) {
                    $bt = BienTheSanPham::create([
                        'ma_sp'        => $sp->ma_sp,
                        'sku'          => $vData['sku'],
                        'gia_ban'      => $vData['gia_ban'],
                        'so_luong_ton' => $vData['so_luong_ton'],
                        'trang_thai'   => true,
                    ]);

                    // Attributes
                    if (!empty($vData['attributes'])) {
                        $gtIds = [];
                        foreach ($vData['attributes'] as $attr) {
                            $tt = ThuocTinh::firstOrCreate(
                                ['ten_tt' => $attr['name']],
                                ['ma_tt' => ThuocTinh::generateId('TT', 'thuoc_tinh', 'ma_tt')]
                            );
                            $gt = GiaTriThuocTinh::firstOrCreate(
                                ['ma_tt' => $tt->ma_tt, 'gia_tri' => $attr['value']],
                                ['ma_gt' => GiaTriThuocTinh::generateId('GT', 'gia_tri_thuoc_tinh', 'ma_gt')]
                            );
                            $gtIds[] = $gt->ma_gt;
                        }
                        DB::table('lien_ket_bien_the_gia_tri')->insert(
                            array_map(fn($id) => ['ma_bt' => $bt->ma_bt, 'ma_gt' => $id], $gtIds)
                        );
                    }
                }
            }

            return $sp;
        });

        $product->load(['danhMuc', 'anhChinh', 'bienThes.giaTriThuocTinhs.thuocTinh']);
        return response()->json($this->formatAdminProduct($product), 201);
    }

    /** PUT /api/admin/products/{id} */
    public function update(Request $request, string $id)
    {
        $product = SanPham::findOrFail($id);
        $data = $request->validate([
            'ten_sp'     => 'sometimes|string|max:100',
            'ma_dm'      => 'sometimes|exists:danh_muc,ma_dm',
            'mo_ta'      => 'nullable|string',
            'gia_co_ban' => 'sometimes|numeric|min:0',
            'trang_thai' => 'sometimes|in:active,inactive,out_of_stock',
        ]);

        $product->update($data);
        $product->load(['danhMuc', 'anhChinh', 'bienThes']);
        return response()->json($this->formatAdminProduct($product));
    }

    /** DELETE /api/admin/products/{id} */
    public function destroy(string $id)
    {
        $product = SanPham::findOrFail($id);
        $product->update(['trang_thai' => 'inactive']);
        return response()->json(['message' => 'Sản phẩm đã được ẩn.']);
    }

    /** PUT /api/admin/variants/{id} — Cập nhật tồn kho */
    public function updateVariant(Request $request, string $id)
    {
        $bt = BienTheSanPham::findOrFail($id);
        $data = $request->validate([
            'so_luong_ton' => 'required|integer|min:0',
            'gia_ban'      => 'sometimes|numeric|min:0',
            'trang_thai'   => 'sometimes|boolean',
        ]);
        $bt->update($data);
        return response()->json(['message' => 'Đã cập nhật biến thể.', 'variant' => $bt]);
    }

    private function formatAdminProduct(SanPham $p): array
    {
        $totalStock = $p->bienThes->sum('so_luong_ton');
        return [
            'id'          => $p->ma_sp,
            'name'        => $p->ten_sp,
            'category'    => $p->danhMuc?->ten_dm,
            'category_id' => $p->ma_dm,
            'price'       => (float)($p->bienThes->min('gia_ban') ?? $p->gia_co_ban),
            'original_price' => (float)$p->gia_co_ban,
            'image'       => $p->anhChinh?->url,
            'stock'       => $totalStock,
            'status'      => $p->trang_thai,
            'created_at'  => $p->ngay_tao?->format('Y-m-d'),
            'rating'      => round(\App\Models\DanhGia::where('ma_sp', $p->ma_sp)->avg('so_sao') ?? 0, 1),
            'sold'        => (int)\DB::table('chi_tiet_don_hang as ctdh')
                ->join('bien_the_san_pham as bt', 'ctdh.ma_bien_the', '=', 'bt.ma_bt')
                ->where('bt.ma_sp', $p->ma_sp)->sum('ctdh.so_luong'),
            'variant_count' => $p->bienThes->count(),
            'low_stock'   => $totalStock <= 5,
        ];
    }
}
