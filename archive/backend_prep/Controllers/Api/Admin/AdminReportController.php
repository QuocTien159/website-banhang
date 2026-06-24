<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DonHang;
use App\Models\SanPham;
use App\Models\KhachHang;
use App\Models\BienTheSanPham;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    /** GET /api/admin/reports/summary — Dashboard tổng quan */
    public function summary()
    {
        $totalRevenue = DonHang::where('trang_thai', '!=', 'cancelled')->sum('tong_tien');
        $totalOrders  = DonHang::count();
        $pendingOrders = DonHang::where('trang_thai', 'pending')->count();
        $totalCustomers = KhachHang::where('vai_tro', false)->count();
        $totalProducts  = SanPham::where('trang_thai', 'active')->count();
        $lowStockCount  = BienTheSanPham::where('trang_thai', true)->where('so_luong_ton', '<=', 5)->count();

        $recentOrders = DonHang::with('khachHang')
            ->orderBy('ngay_dat', 'desc')
            ->take(6)
            ->get()
            ->map(fn($dh) => [
                'id'       => $dh->ma_dh,
                'customer' => $dh->khachHang?->ten_kh,
                'total'    => (float)$dh->tong_tien,
                'status'   => $dh->trang_thai,
                'date'     => $dh->ngay_dat?->format('d/m/Y H:i'),
            ]);

        $topProducts = DB::table('chi_tiet_don_hang as ct')
            ->join('bien_the_san_pham as bt', 'ct.ma_bien_the', '=', 'bt.ma_bt')
            ->join('san_pham as sp', 'bt.ma_sp', '=', 'sp.ma_sp')
            ->join('don_hang as dh', 'ct.ma_dh', '=', 'dh.ma_dh')
            ->leftJoin('hinh_anh_san_pham as ha', function ($j) {
                $j->on('ha.ma_sp', '=', 'sp.ma_sp')->where('ha.anh_chinh', true);
            })
            ->where('dh.trang_thai', '!=', 'cancelled')
            ->selectRaw('sp.ma_sp, sp.ten_sp, ha.url as image, SUM(ct.so_luong) as sold, SUM(ct.so_luong * ct.don_gia) as revenue')
            ->groupBy('sp.ma_sp', 'sp.ten_sp', 'ha.url')
            ->orderByDesc('sold')
            ->take(5)
            ->get();

        return response()->json([
            'stats' => [
                'total_revenue'   => (float)$totalRevenue,
                'total_orders'    => $totalOrders,
                'pending_orders'  => $pendingOrders,
                'total_customers' => $totalCustomers,
                'total_products'  => $totalProducts,
                'low_stock_count' => $lowStockCount,
            ],
            'recent_orders' => $recentOrders,
            'top_products'  => $topProducts,
        ]);
    }

    /** GET /api/admin/reports/revenue — Doanh thu theo tháng */
    public function revenue(Request $request)
    {
        $year = $request->input('year', now()->year);

        $monthly = DB::table('don_hang')
            ->where('trang_thai', '!=', 'cancelled')
            ->whereYear('ngay_dat', $year)
            ->selectRaw('MONTH(ngay_dat) as month, SUM(tong_tien) as revenue, COUNT(*) as orders')
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $result = [];
        for ($m = 1; $m <= 12; $m++) {
            $result[] = [
                'month'   => "T{$m}",
                'revenue' => (float)($monthly[$m]->revenue ?? 0),
                'orders'  => (int)($monthly[$m]->orders ?? 0),
            ];
        }

        $totalRevenue = array_sum(array_column($result, 'revenue'));
        $totalOrders  = array_sum(array_column($result, 'orders'));
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;

        return response()->json([
            'year'            => $year,
            'monthly'         => $result,
            'total_revenue'   => $totalRevenue,
            'total_orders'    => $totalOrders,
            'avg_order_value' => round($avgOrderValue, 2),
        ]);
    }

    /** GET /api/admin/reports/inventory — Cảnh báo tồn kho */
    public function inventory()
    {
        $lowStock = BienTheSanPham::with(['sanPham.anhChinh'])
            ->where('trang_thai', true)
            ->where('so_luong_ton', '<=', 10)
            ->orderBy('so_luong_ton', 'asc')
            ->get()
            ->map(fn($bt) => [
                'variant_id'   => $bt->ma_bt,
                'sku'          => $bt->sku,
                'product_name' => $bt->sanPham?->ten_sp,
                'product_id'   => $bt->ma_sp,
                'image'        => $bt->sanPham?->anhChinh?->url,
                'stock'        => $bt->so_luong_ton,
                'alert_level'  => $bt->so_luong_ton === 0 ? 'out_of_stock' : ($bt->so_luong_ton <= 5 ? 'critical' : 'low'),
            ]);

        return response()->json([
            'low_stock_items' => $lowStock,
            'out_of_stock'    => $lowStock->where('alert_level', 'out_of_stock')->count(),
            'critical'        => $lowStock->where('alert_level', 'critical')->count(),
            'low'             => $lowStock->where('alert_level', 'low')->count(),
        ]);
    }
}
