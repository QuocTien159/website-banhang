<?php

namespace App\Services;

use App\Models\DonHang;
use App\Support\OrderStatus;
use App\Support\PaymentStatus;
use App\Support\UserRole;
use Carbon\Carbon;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class RevenueAnalyticsService
{
    private const REVENUE_STATUSES = OrderStatus::FULFILLED;

    private const RETURN_STATUSES = ['received', 'completed'];

    /**
     * Aggregates category performance from order lines, allocating each
     * order's final payable total across the items the customer keeps.
     */
    public function topCategories(Carbon $from, Carbon $to, int $limit = 5): Collection
    {
        $limit = min(20, max(1, $limit));
        $retainedQuantity = $this->retainedQuantitySql('ct', 'returned_items');
        $metrics = $this->revenueOrderMetrics($from, $to);

        $rows = DB::query()
            ->fromSub($metrics, 'metrics')
            ->join('chi_tiet_don_hang as ct', 'ct.ma_dh', '=', 'metrics.ma_dh')
            ->join('bien_the_san_pham as bt', 'ct.ma_bien_the', '=', 'bt.ma_bt')
            ->join('san_pham as sp', 'bt.ma_sp', '=', 'sp.ma_sp')
            ->leftJoin('danh_muc as dm', 'sp.ma_dm', '=', 'dm.ma_dm')
            ->leftJoinSub($this->returnedQuantityQuery(), 'returned_items', function ($join) {
                $join->on('returned_items.ma_dh', '=', 'ct.ma_dh')
                    ->on('returned_items.ma_bien_the', '=', 'ct.ma_bien_the');
            })
            ->whereRaw("{$retainedQuantity} > 0")
            ->selectRaw("COALESCE(dm.ma_dm, 'uncategorized') as category_key")
            ->selectRaw("COALESCE(dm.ten_dm, 'Uncategorized') as category_name")
            ->selectRaw("SUM({$retainedQuantity}) as quantity_sold")
            ->selectRaw('COUNT(DISTINCT metrics.ma_dh) as completed_order_count')
            ->selectRaw("SUM(({$retainedQuantity} * ct.don_gia) * CASE WHEN metrics.gross_line_total > 0 THEN (1.0 * metrics.tong_tien / metrics.gross_line_total) ELSE 0 END) as net_revenue")
            ->groupBy('dm.ma_dm', 'dm.ten_dm')
            ->orderByDesc('net_revenue')
            ->orderByDesc('quantity_sold')
            ->limit($limit)
            ->get();

        $totalRevenue = $this->totalRevenue($from, $to);

        return $rows->map(fn ($row) => [
            'category_id' => $row->category_key === 'uncategorized' ? null : $row->category_key,
            'name' => $row->category_name === 'Uncategorized' ? 'Chưa phân loại' : $row->category_name,
            // The current category schema has no media field. Keep the contract
            // ready for it without inventing an image URL.
            'image_url' => null,
            'quantity_sold' => (int) $row->quantity_sold,
            'completed_order_count' => (int) $row->completed_order_count,
            'net_revenue' => round((float) $row->net_revenue, 2),
            'revenue_share_percent' => $totalRevenue > 0
                ? round(((float) $row->net_revenue / $totalRevenue) * 100, 1)
                : 0,
        ])->values();
    }

    /**
     * Aggregates identifiable customers only. The returned contact is masked
     * before it ever reaches the dashboard API response.
     */
    public function topCustomers(Carbon $from, Carbon $to, int $limit = 5): Collection
    {
        $limit = min(20, max(1, $limit));
        $periodSpend = $this->customerSpendMetrics($from, $to);
        $lifetimeSpend = $this->customerSpendMetrics();

        $rows = DB::query()
            ->fromSub($periodSpend, 'period_spend')
            ->join('khach_hang as kh', 'period_spend.ma_kh', '=', 'kh.ma_kh')
            ->leftJoinSub($lifetimeSpend, 'lifetime_spend', function ($join) {
                $join->on('lifetime_spend.ma_kh', '=', 'kh.ma_kh');
            })
            ->where('kh.role', UserRole::CUSTOMER)
            ->where('kh.vai_tro', false)
            ->select([
                'kh.ma_kh as customer_id',
                'kh.ten_kh as customer_name',
                'kh.google_avatar as avatar_url',
                'kh.dien_thoai as phone',
                'kh.email as email',
                'period_spend.completed_order_count',
                'period_spend.net_spent',
                'period_spend.last_order_at',
                'lifetime_spend.completed_order_count as lifetime_order_count',
            ])
            ->orderByDesc('period_spend.net_spent')
            ->orderByDesc('period_spend.completed_order_count')
            ->orderByDesc('period_spend.last_order_at')
            ->limit($limit)
            ->get();

        return $rows->map(fn ($row) => [
            'customer_id' => $row->customer_id,
            'name' => $row->customer_name,
            'avatar_url' => $row->avatar_url ?: null,
            'masked_contact' => $this->maskContact($row->phone, $row->email),
            'completed_order_count' => (int) $row->completed_order_count,
            'net_spent' => round((float) $row->net_spent, 2),
            'last_order_at' => $row->last_order_at ? Carbon::parse($row->last_order_at)->toISOString() : null,
            'customer_type' => (int) ($row->lifetime_order_count ?? 0) > 1 ? 'returning' : 'new',
        ])->values();
    }

    /**
     * Reusable order-level metric query. It deliberately produces one row per
     * order so customer totals cannot be duplicated by multiple order lines.
     */
    public function customerSpendMetrics(?Carbon $from = null, ?Carbon $to = null): Builder
    {
        return DB::query()
            ->fromSub($this->revenueOrderMetrics($from, $to), 'order_metrics')
            ->where('order_metrics.net_revenue', '>', 0)
            ->selectRaw('order_metrics.ma_kh')
            ->selectRaw('COUNT(*) as completed_order_count')
            ->selectRaw('SUM(order_metrics.net_revenue) as net_spent')
            ->selectRaw('MAX(order_metrics.ngay_giao_thanh_cong) as last_order_at')
            ->groupBy('order_metrics.ma_kh');
    }

    public function totalRevenue(Carbon $from, Carbon $to): float
    {
        return (float) DB::query()
            ->fromSub($this->revenueOrderMetrics($from, $to), 'order_metrics')
            ->sum('net_revenue');
    }

    public function isRevenueEligible(DonHang $order): bool
    {
        if (! in_array($order->trang_thai, self::REVENUE_STATUSES, true) || ! $order->ngay_giao_thanh_cong) {
            return false;
        }

        // A fulfilled COD order is payment-valid by definition. For prepaid
        // methods, only paid orders (or pre-payment legacy records without a
        // status field) are recognised as revenue.
        return $order->phuong_thuc_tt === 'cod'
            || blank($order->trang_thai_thanh_toan)
            || $order->trang_thai_thanh_toan === PaymentStatus::PAID;
    }

    public function hasRecognisedRevenue(DonHang $order): bool
    {
        return $this->isRevenueEligible($order)
            && $this->orderRevenueBreakdown($order)['net_revenue'] > 0;
    }

    /**
     * @return array{net_revenue: float, allocation_factor: float, returned_quantities: array<string, int>}
     */
    public function orderRevenueBreakdown(DonHang $order): array
    {
        $returned = $this->returnedQuantitiesForOrder($order);
        $grossLineTotal = (float) $order->chiTiets->sum(
            fn ($item) => max(0, (int) $item->so_luong) * (float) $item->don_gia
        );

        if ($grossLineTotal <= 0) {
            return [
                'net_revenue' => (float) $order->tong_tien,
                'allocation_factor' => 0,
                'returned_quantities' => $returned,
            ];
        }

        $retainedLineTotal = (float) $order->chiTiets->sum(function ($item) use ($returned) {
            $ordered = max(0, (int) $item->so_luong);
            $returnedQuantity = min($ordered, (int) ($returned[$item->ma_bien_the] ?? 0));

            return ($ordered - $returnedQuantity) * (float) $item->don_gia;
        });

        return [
            'net_revenue' => (float) $order->tong_tien * ($retainedLineTotal / $grossLineTotal),
            'allocation_factor' => (float) $order->tong_tien / $grossLineTotal,
            'returned_quantities' => $returned,
        ];
    }

    public function netRevenueForOrders(iterable $orders): float
    {
        $total = 0.0;
        foreach ($orders as $order) {
            $total += $this->orderRevenueBreakdown($order)['net_revenue'];
        }

        return $total;
    }

    private function revenueOrderMetrics(?Carbon $from = null, ?Carbon $to = null): Builder
    {
        $query = DB::table('don_hang as dh')
            ->leftJoinSub($this->lineTotalsQuery(), 'line_totals', function ($join) {
                $join->on('line_totals.ma_dh', '=', 'dh.ma_dh');
            })
            ->select([
                'dh.ma_dh',
                'dh.ma_kh',
                'dh.ngay_giao_thanh_cong',
                'dh.tong_tien',
            ])
            ->selectRaw('COALESCE(line_totals.gross_line_total, 0) as gross_line_total')
            ->selectRaw('COALESCE(line_totals.retained_line_total, 0) as retained_line_total')
            ->selectRaw('CASE WHEN COALESCE(line_totals.gross_line_total, 0) > 0 THEN (1.0 * dh.tong_tien * COALESCE(line_totals.retained_line_total, 0) / line_totals.gross_line_total) ELSE dh.tong_tien END as net_revenue');

        $this->applyRevenueEligibility($query, 'dh');

        if ($from && $to) {
            $query->whereBetween('dh.ngay_giao_thanh_cong', [$from, $to]);
        }

        return $query;
    }

    private function lineTotalsQuery(): Builder
    {
        $retainedQuantity = $this->retainedQuantitySql('ct', 'returned_items');

        return DB::table('chi_tiet_don_hang as ct')
            ->leftJoinSub($this->returnedQuantityQuery(), 'returned_items', function ($join) {
                $join->on('returned_items.ma_dh', '=', 'ct.ma_dh')
                    ->on('returned_items.ma_bien_the', '=', 'ct.ma_bien_the');
            })
            ->select('ct.ma_dh')
            ->selectRaw('SUM(ct.so_luong * ct.don_gia) as gross_line_total')
            ->selectRaw("SUM(({$retainedQuantity}) * ct.don_gia) as retained_line_total")
            ->groupBy('ct.ma_dh');
    }

    private function returnedQuantityQuery(): Builder
    {
        return DB::table('yeu_cau_tra_hang as returns')
            ->join('chi_tiet_tra_hang as return_items', 'return_items.ma_yeu_cau', '=', 'returns.ma_yeu_cau')
            ->whereIn('returns.trang_thai', self::RETURN_STATUSES)
            ->select('returns.ma_dh', 'return_items.ma_bien_the')
            ->selectRaw('SUM(return_items.so_luong) as returned_quantity')
            ->groupBy('returns.ma_dh', 'return_items.ma_bien_the');
    }

    private function applyRevenueEligibility(Builder $query, string $alias): void
    {
        $query->whereIn("{$alias}.trang_thai", self::REVENUE_STATUSES)
            ->where(function (Builder $payment) use ($alias) {
                $payment->where("{$alias}.phuong_thuc_tt", 'cod')
                    ->orWhereNull("{$alias}.trang_thai_thanh_toan")
                    ->orWhere("{$alias}.trang_thai_thanh_toan", PaymentStatus::PAID);
            });
    }

    private function retainedQuantitySql(string $lineAlias, string $returnedAlias): string
    {
        return "CASE WHEN {$lineAlias}.so_luong > COALESCE({$returnedAlias}.returned_quantity, 0) THEN {$lineAlias}.so_luong - COALESCE({$returnedAlias}.returned_quantity, 0) ELSE 0 END";
    }

    /** @return array<string, int> */
    private function returnedQuantitiesForOrder(DonHang $order): array
    {
        return $order->yeuCauTraHangs
            ->filter(fn ($return) => in_array($return->trang_thai, self::RETURN_STATUSES, true))
            ->flatMap->chiTiets
            ->groupBy('ma_bien_the')
            ->map(fn ($items) => (int) $items->sum('so_luong'))
            ->all();
    }

    private function maskContact(?string $phone, ?string $email): string
    {
        $phone = preg_replace('/\D+/', '', (string) $phone) ?? '';
        if (strlen($phone) >= 5) {
            return substr($phone, 0, 2).str_repeat('*', strlen($phone) - 4).substr($phone, -2);
        }

        if ($email && str_contains($email, '@')) {
            [$local, $domain] = explode('@', $email, 2);

            return mb_substr($local, 0, 1).'***@'.$domain;
        }

        return '-';
    }
}
