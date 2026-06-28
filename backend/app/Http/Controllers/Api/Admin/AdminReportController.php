<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BienTheSanPham;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\SanPham;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    private const REVENUE_STATUSES = ['delivered'];
    private const REVENUE_RETURN_STATUSES = ['received', 'completed'];

    public function summary()
    {
        $totalRevenue = $this->netRevenueForOrders(DonHang::with(['chiTiets', 'yeuCauTraHangs.chiTiets'])
            ->whereIn('trang_thai', self::REVENUE_STATUSES)
            ->get());
        $totalOrders = DonHang::count();
        $pendingOrders = DonHang::where('trang_thai', 'pending')->count();
        $totalCustomers = KhachHang::where('role', 'customer')->where('vai_tro', false)->count();
        $totalProducts = SanPham::where('trang_thai', 'active')->count();
        $lowStockCount = BienTheSanPham::where('trang_thai', true)
            ->whereColumn('so_luong_ton', '<=', 'nguong_canh_bao_ton')
            ->count();

        $recentOrders = DonHang::with('khachHang')
            ->orderBy('ngay_dat', 'desc')
            ->take(6)
            ->get()
            ->map(fn (DonHang $order) => [
                'id' => $order->ma_dh,
                'customer' => $order->khachHang?->ten_kh,
                'customer_name' => $order->khachHang?->ten_kh,
                'total' => (float) $order->tong_tien,
                'status' => $order->trang_thai,
                'date' => $order->ngay_dat?->format('d/m/Y H:i'),
                'created_at' => $order->ngay_dat?->toISOString(),
            ]);

        $returnQuantities = DB::table('chi_tiet_tra_hang as ctth')
            ->join('yeu_cau_tra_hang as ycth', 'ctth.ma_yeu_cau', '=', 'ycth.ma_yeu_cau')
            ->whereIn('ycth.trang_thai', self::REVENUE_RETURN_STATUSES)
            ->selectRaw('ycth.ma_dh, ctth.ma_bien_the, SUM(ctth.so_luong) as returned_quantity')
            ->groupBy('ycth.ma_dh', 'ctth.ma_bien_the')
            ->get()
            ->keyBy(fn ($row) => $row->ma_dh.'|'.$row->ma_bien_the);

        $topProducts = DB::table('chi_tiet_don_hang as ct')
            ->join('bien_the_san_pham as bt', 'ct.ma_bien_the', '=', 'bt.ma_bt')
            ->join('san_pham as sp', 'bt.ma_sp', '=', 'sp.ma_sp')
            ->join('don_hang as dh', 'ct.ma_dh', '=', 'dh.ma_dh')
            ->leftJoin('hinh_anh_san_pham as ha', function ($join) {
                $join->on('ha.ma_sp', '=', 'sp.ma_sp')->where('ha.anh_chinh', true);
            })
            ->whereIn('dh.trang_thai', self::REVENUE_STATUSES)
            ->selectRaw('dh.ma_dh, ct.ma_bien_the, sp.ma_sp, sp.ten_sp, ha.url as image, ct.so_luong, ct.don_gia')
            ->get()
            ->groupBy('ma_sp')
            ->map(function ($rows) use ($returnQuantities) {
                $first = $rows->first();
                $sold = 0;
                $revenue = 0;
                foreach ($rows as $row) {
                    $returned = (int) ($returnQuantities[$row->ma_dh.'|'.$row->ma_bien_the]->returned_quantity ?? 0);
                    $netQuantity = max(0, (int) $row->so_luong - $returned);
                    $sold += $netQuantity;
                    $revenue += $netQuantity * (float) $row->don_gia;
                }

                return [
                    'id' => $first->ma_sp,
                    'name' => $first->ten_sp,
                    'image' => $first->image,
                    'sold' => $sold,
                    'revenue' => $revenue,
                    'price' => (float) $rows->min('don_gia'),
                ];
            })
            ->filter(fn ($product) => $product['sold'] > 0)
            ->sortByDesc('sold')
            ->take(5)
            ->values();

        return response()->json([
            'stats' => [
                'total_revenue' => (float) $totalRevenue,
                'total_orders' => $totalOrders,
                'pending_orders' => $pendingOrders,
                'total_customers' => $totalCustomers,
                'total_products' => $totalProducts,
                'low_stock_count' => $lowStockCount,
                'low_stock' => $lowStockCount,
                'revenue_note' => 'Doanh thu chỉ tính từ các đơn đã hoàn thành.',
                'revenue_statuses' => self::REVENUE_STATUSES,
                'status_pending' => DonHang::where('trang_thai', 'pending')->count(),
                'status_confirmed' => DonHang::where('trang_thai', 'confirmed')->count(),
                'status_shipping' => DonHang::where('trang_thai', 'shipping')->count(),
                'status_delivered' => DonHang::where('trang_thai', 'delivered')->count(),
                'status_cancelled' => DonHang::where('trang_thai', 'cancelled')->count(),
            ],
            'recent_orders' => $recentOrders,
            'top_products' => $topProducts,
        ]);
    }

    public function revenue(Request $request)
    {
        $year = $request->input('year', now()->year);

        $orders = DonHang::with(['chiTiets', 'yeuCauTraHangs.chiTiets'])
            ->whereIn('trang_thai', self::REVENUE_STATUSES)
            ->whereYear('ngay_dat', $year)
            ->get()
            ->groupBy(fn (DonHang $order) => (int) $order->ngay_dat->format('n'));

        $result = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthOrders = $orders->get($month, collect());
            $result[] = [
                'month' => "T{$month}",
                'revenue' => (float) $this->netRevenueForOrders($monthOrders),
                'orders' => (int) $monthOrders->count(),
            ];
        }

        $totalRevenue = array_sum(array_column($result, 'revenue'));
        $totalRevenueOrders = array_sum(array_column($result, 'orders'));
        $avgOrderValue = $totalRevenueOrders > 0 ? $totalRevenue / $totalRevenueOrders : 0;

        return response()->json([
            'year' => (int) $year,
            'monthly' => $result,
            'total_revenue' => $totalRevenue,
            'revenue_orders' => $totalRevenueOrders,
            'total_orders' => DonHang::whereYear('ngay_dat', $year)->count(),
            'avg_order_value' => round($avgOrderValue, 2),
            'note' => 'Doanh thu chỉ tính từ các đơn đã hoàn thành.',
            'revenue_statuses' => self::REVENUE_STATUSES,
        ]);
    }

    public function inventory()
    {
        $lowStock = BienTheSanPham::with(['sanPham.anhChinh'])
            ->where('trang_thai', true)
            ->whereColumn('so_luong_ton', '<=', 'nguong_canh_bao_ton')
            ->orderBy('so_luong_ton', 'asc')
            ->get()
            ->map(fn (BienTheSanPham $variant) => [
                'variant_id' => $variant->ma_bt,
                'sku' => $variant->sku,
                'product_name' => $variant->sanPham?->ten_sp,
                'product_id' => $variant->ma_sp,
                'image' => $variant->sanPham?->anhChinh?->url,
                'stock' => $variant->so_luong_ton,
                'low_stock_threshold' => $variant->nguong_canh_bao_ton,
                'alert_level' => $variant->so_luong_ton === 0 ? 'out_of_stock' : ($variant->so_luong_ton <= 5 ? 'critical' : 'low'),
            ]);

        return response()->json([
            'low_stock_items' => $lowStock,
            'out_of_stock' => $lowStock->where('alert_level', 'out_of_stock')->count(),
            'critical' => $lowStock->where('alert_level', 'critical')->count(),
            'low' => $lowStock->where('alert_level', 'low')->count(),
        ]);
    }

    private function netRevenueForOrders($orders): float
    {
        return (float) $orders->sum(function (DonHang $order) {
            if ($order->chiTiets->isEmpty()) {
                return (float) $order->tong_tien;
            }

            $returned = $order->yeuCauTraHangs
                ->whereIn('trang_thai', self::REVENUE_RETURN_STATUSES)
                ->flatMap->chiTiets
                ->groupBy('ma_bien_the')
                ->map(fn ($items) => $items->sum('so_luong'));

            return $order->chiTiets->sum(function ($item) use ($returned) {
                $soldQuantity = (int) $item->so_luong;
                $returnedQuantity = min($soldQuantity, (int) ($returned[$item->ma_bien_the] ?? 0));
                return max(0, $soldQuantity - $returnedQuantity) * (float) $item->don_gia;
            });
        });
    }
}
