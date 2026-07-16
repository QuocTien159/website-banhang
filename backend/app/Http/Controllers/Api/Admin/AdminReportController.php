<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\BienTheSanPham;
use App\Models\DonHang;
use App\Models\KhachHang;
use App\Models\PhieuNhapKho;
use App\Models\SanPham;
use App\Models\YeuCauTraHang;
use App\Services\RevenueAnalyticsService;
use App\Support\OrderStatus;
use App\Support\UserRole;
use App\Services\VariantStockStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminReportController extends Controller
{
    // Chỉ đơn đã giao thành công mới được ghi nhận doanh thu; đơn đang xử lý có thể bị hủy/trả.
    private const REVENUE_STATUSES = OrderStatus::FULFILLED;
    private const REVENUE_RETURN_STATUSES = ['received', 'completed'];

    public function __construct(private readonly RevenueAnalyticsService $revenueAnalytics)
    {
    }

    /**
     * Operational dashboard data. Revenue is recognised only for delivered
     * orders and is reduced by returns that have been received/completed.
     */
    public function dashboard(Request $request, VariantStockStatusService $stockStatus)
    {
        $data = $request->validate([
            'from' => ['required', 'date_format:Y-m-d'],
            'to' => ['required', 'date_format:Y-m-d', 'after_or_equal:from'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        $from = Carbon::createFromFormat('Y-m-d', $data['from'])->startOfDay();
        $to = Carbon::createFromFormat('Y-m-d', $data['to'])->endOfDay();
        $days = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        if ($days > 366) {
            return response()->json(['message' => 'Khoảng thời gian không được vượt quá 366 ngày.'], 422);
        }

        $limit = (int) ($data['limit'] ?? 5);
        $previousTo = $from->copy()->subDay()->endOfDay();
        $previousFrom = $previousTo->copy()->subDays($days - 1)->startOfDay();

        $currentOrders = $this->ordersForRange($from, $to);
        $previousOrders = $this->ordersForRange($previousFrom, $previousTo);
        $currentRevenueOrders = $currentOrders
            ->filter(fn (DonHang $order) => $this->revenueAnalytics->hasRecognisedRevenue($order))
            ->values();
        $previousRevenueOrders = $previousOrders
            ->filter(fn (DonHang $order) => $this->revenueAnalytics->hasRecognisedRevenue($order))
            ->values();
        $currentRevenue = $this->revenueAnalytics->netRevenueForOrders($currentRevenueOrders);
        $previousRevenue = $this->revenueAnalytics->netRevenueForOrders($previousRevenueOrders);

        $currentCustomers = KhachHang::where('role', UserRole::CUSTOMER)
            ->where('vai_tro', false)
            ->whereBetween('ngay_tao', [$from, $to])
            ->count();
        $previousCustomers = KhachHang::where('role', UserRole::CUSTOMER)
            ->where('vai_tro', false)
            ->whereBetween('ngay_tao', [$previousFrom, $previousTo])
            ->count();

        $activeProducts = SanPham::where('trang_thai', 'active')
            ->where('ngay_tao', '<=', $to)
            ->count();
        $previousActiveProducts = SanPham::where('trang_thai', 'active')
            ->where('ngay_tao', '<=', $previousTo)
            ->count();

        $topCategories = $this->revenueAnalytics->topCategories($from, $to, $limit);
        $chartCategories = $topCategories->take(5)->map(fn (array $category) => [
            'name' => $category['name'],
            'revenue' => $category['net_revenue'],
            'percent' => $category['revenue_share_percent'],
        ])->values();
        $otherCategoryRevenue = max(0, $currentRevenue - $chartCategories->sum('revenue'));
        if ($otherCategoryRevenue > 0) {
            $chartCategories->push([
                'name' => 'Khác',
                'revenue' => round($otherCategoryRevenue, 2),
                'percent' => $currentRevenue > 0 ? round(($otherCategoryRevenue / $currentRevenue) * 100, 1) : 0,
            ]);
        }

        $recentOrders = $currentOrders->sortByDesc('ngay_dat')->take(6)->map(fn (DonHang $order) => [
            'id' => $order->ma_dh,
            'customer' => $order->khachHang?->ten_kh ?? 'Khách hàng',
            'created_at' => $order->ngay_dat?->toISOString(),
            'total' => (float) $order->tong_tien,
            'status' => $order->trang_thai,
            'last_processed_by' => $order->xuLyGanNhat?->nguoiXuLy?->ten_kh,
        ])->values();

        $lowStock = $stockStatus->alertQuery()->count();
        $outOfStock = $stockStatus->alertQuery()->where('so_luong_ton', 0)->count();

        return response()->json([
            'period' => [
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'label' => $this->periodLabel($from, $to),
                'previous_from' => $previousFrom->toDateString(),
                'previous_to' => $previousTo->toDateString(),
            ],
            'kpis' => [
                'revenue' => $this->metric($currentRevenue, $previousRevenue),
                'orders' => $this->metric($currentOrders->count(), $previousOrders->count()),
                'new_customers' => $this->metric($currentCustomers, $previousCustomers),
                'active_products' => $this->metric($activeProducts, $previousActiveProducts),
            ],
            'actions' => [
                [
                    'key' => 'pending_orders', 'count' => $currentOrders->where('trang_thai', OrderStatus::PENDING)->count(),
                    'label' => 'Đơn hàng chờ xác nhận', 'description' => 'Mở danh sách đơn cần xử lý', 'href' => '/admin/orders', 'priority' => 'warning',
                ],
                [
                    'key' => 'pending_returns', 'count' => YeuCauTraHang::where('trang_thai', 'pending')->whereBetween('ngay_yeu_cau', [$from, $to])->count(),
                    'label' => 'Yêu cầu trả hàng chờ xử lý', 'description' => 'Xem các yêu cầu mới', 'href' => '/admin/returns', 'priority' => 'warning',
                ],
                [
                    'key' => 'pending_receipts', 'count' => PhieuNhapKho::where('trang_thai', 'pending')->whereBetween('ngay_tao', [$from, $to])->count(),
                    'label' => 'Phiếu nhập kho chờ duyệt', 'description' => 'Kiểm tra và phê duyệt phiếu nhập', 'href' => '/admin/stock-receipts', 'priority' => 'info',
                ],
                [
                    'key' => 'low_stock', 'count' => $lowStock, 'secondary_count' => $outOfStock,
                    'label' => 'SKU sắp hết hoặc hết hàng', 'description' => 'Kiểm tra mức tồn kho hiện tại', 'href' => '/admin/stock-alerts', 'priority' => $outOfStock > 0 ? 'danger' : 'warning',
                ],
            ],
            'charts' => [
                'revenue_and_completed_orders' => $this->chartSeries($currentRevenueOrders, $from, $to),
                'revenue_by_category' => $chartCategories->all(),
            ],
            'recent_orders' => $recentOrders,
            'top_products' => $this->topProducts($currentRevenueOrders),
            'top_categories' => $topCategories,
            'top_customers' => $this->revenueAnalytics->topCustomers($from, $to, $limit),
            'meta' => [
                'revenue_rule' => 'Chỉ tính đơn giao thành công đã thanh toán hợp lệ; doanh thu được phân bổ theo tổng tiền thực nhận và trừ hàng đã trả hoặc hoàn tiền.',
                'low_stock_count' => $lowStock,
                'ranking_limit' => $limit,
            ],
        ]);
    }

    private function ordersForRange(Carbon $from, Carbon $to)
    {
        return DonHang::with([
            'khachHang',
            'chiTiets.bienThe.sanPham.danhMuc',
            'chiTiets.bienThe.sanPham.anhChinh',
            'yeuCauTraHangs.chiTiets',
            'xuLyGanNhat.nguoiXuLy',
        ])->whereBetween('ngay_dat', [$from, $to])->get();
    }

    private function metric(float|int $value, float|int $previous): array
    {
        $change = $previous == 0 ? null : round((($value - $previous) / $previous) * 100, 1);

        return [
            'value' => $value,
            'previous_value' => $previous,
            'change_percent' => $change,
            'direction' => $change === null || $change === 0 ? 'neutral' : ($change > 0 ? 'up' : 'down'),
        ];
    }

    private function periodLabel(Carbon $from, Carbon $to): string
    {
        if ($from->isSameDay($to)) {
            return 'Ngày '.$from->format('d/m/Y');
        }

        return $from->format('d/m/Y').' - '.$to->format('d/m/Y');
    }

    private function chartSeries($orders, Carbon $from, Carbon $to): array
    {
        $days = $from->copy()->startOfDay()->diffInDays($to->copy()->startOfDay()) + 1;
        $mode = $days <= 2 ? 'hour' : ($days <= 31 ? 'day' : ($days <= 180 ? 'week' : 'month'));

        return $orders->groupBy(function (DonHang $order) use ($mode) {
            return match ($mode) {
                'hour' => $order->ngay_dat->format('d/m H:00'),
                'week' => 'Tuần '.$order->ngay_dat->isoWeek().'/'.$order->ngay_dat->format('Y'),
                'month' => 'T'.$order->ngay_dat->format('m/Y'),
                default => $order->ngay_dat->format('d/m'),
            };
        })->map(function ($bucket, string $label) {
            return [
                'label' => $label,
                'revenue' => (float) $this->revenueAnalytics->netRevenueForOrders($bucket),
                'completed_orders' => $bucket->count(),
            ];
        })->values()->all();
    }

    private function topProducts($orders): array
    {
        $products = [];
        foreach ($orders as $order) {
            $breakdown = $this->revenueAnalytics->orderRevenueBreakdown($order);
            $returned = $breakdown['returned_quantities'];
            foreach ($order->chiTiets as $item) {
                $variant = $item->bienThe;
                $product = $variant?->sanPham;
                if (!$product) continue;
                $quantity = max(0, (int) $item->so_luong - (int) ($returned[$item->ma_bien_the] ?? 0));
                $id = $product->ma_sp;
                $products[$id] ??= [
                    'id' => $id, 'name' => $product->ten_sp, 'sku' => $variant->sku,
                    'image' => $product->anhChinh?->url, 'sold' => 0, 'revenue' => 0,
                    'stock' => 0,
                ];
                $products[$id]['sold'] += $quantity;
                $products[$id]['revenue'] += $quantity * (float) $item->don_gia * $breakdown['allocation_factor'];
            }
        }

        $stockByProduct = BienTheSanPham::whereIn('ma_sp', array_keys($products))
            ->where('trang_thai', true)
            ->selectRaw('ma_sp, SUM(so_luong_ton) as stock')
            ->groupBy('ma_sp')
            ->pluck('stock', 'ma_sp');
        foreach ($products as &$product) {
            $product['stock'] = (int) ($stockByProduct[$product['id']] ?? 0);
        }

        return collect($products)->filter(fn ($product) => $product['sold'] > 0)->sortByDesc('sold')->take(5)->values()->all();
    }

    public function summary(VariantStockStatusService $stockStatus)
    {
        $revenueOrders = DonHang::with(['chiTiets', 'yeuCauTraHangs.chiTiets'])
            ->whereIn('trang_thai', self::REVENUE_STATUSES)
            ->get()
            ->filter(fn (DonHang $order) => $this->revenueAnalytics->hasRecognisedRevenue($order));
        $totalRevenue = $this->revenueAnalytics->netRevenueForOrders($revenueOrders);
        $totalOrders = DonHang::count();
        $pendingOrders = DonHang::where('trang_thai', 'pending')->count();
        $totalCustomers = KhachHang::where('role', UserRole::CUSTOMER)->where('vai_tro', false)->count();
        $totalProducts = SanPham::where('trang_thai', 'active')->count();
        $lowStockCount = $stockStatus->alertQuery()->count();

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
                'status_preparing' => DonHang::where('trang_thai', OrderStatus::PREPARING)->count(),
                'status_handed_to_carrier' => DonHang::where('trang_thai', OrderStatus::HANDED_TO_CARRIER)->count(),
                'status_shipping' => DonHang::where('trang_thai', 'shipping')->count(),
                'status_completed' => DonHang::where('trang_thai', OrderStatus::COMPLETED)->count(),
                'status_delivered' => DonHang::whereIn('trang_thai', OrderStatus::FULFILLED)->count(),
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
            ->filter(fn (DonHang $order) => $this->revenueAnalytics->hasRecognisedRevenue($order))
            ->groupBy(fn (DonHang $order) => (int) $order->ngay_dat->format('n'));

        $result = [];
        for ($month = 1; $month <= 12; $month++) {
            $monthOrders = $orders->get($month, collect());
            $result[] = [
                'month' => "T{$month}",
                'revenue' => (float) $this->revenueAnalytics->netRevenueForOrders($monthOrders),
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

    public function inventory(VariantStockStatusService $stockStatus)
    {
        $lowStock = $stockStatus->alertQuery()
            ->with(['sanPham.anhChinh'])
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
                'alert_level' => $stockStatus->status($variant),
            ]);

        return response()->json([
            'low_stock_items' => $lowStock,
            'out_of_stock' => $lowStock->where('alert_level', 'out_of_stock')->count(),
            'critical' => 0,
            'low' => $lowStock->where('alert_level', 'low_stock')->count(),
        ]);
    }

}
