<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\KhachHang;
use App\Services\RevenueAnalyticsService;
use App\Support\UserRole;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminCustomerController extends Controller
{
    /** GET /api/admin/customers */
    public function index(Request $request, RevenueAnalyticsService $revenueAnalytics)
    {
        $data = $request->validate([
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::in(['active', 'blocked'])],
            'sort' => ['nullable', Rule::in(['joined_at', 'total_spent'])],
            'from' => ['nullable', 'date_format:Y-m-d', 'required_with:to'],
            'to' => ['nullable', 'date_format:Y-m-d', 'required_with:from', 'after_or_equal:from'],
        ]);

        $from = isset($data['from']) ? Carbon::createFromFormat('Y-m-d', $data['from'])->startOfDay() : null;
        $to = isset($data['to']) ? Carbon::createFromFormat('Y-m-d', $data['to'])->endOfDay() : null;
        if ($from && $to && $from->diffInDays($to) + 1 > 366) {
            return response()->json(['message' => 'Khoảng thời gian không được vượt quá 366 ngày.'], 422);
        }

        $spendMetrics = $revenueAnalytics->customerSpendMetrics($from, $to);
        $query = KhachHang::query()
            ->leftJoinSub($spendMetrics, 'spend_metrics', function ($join) {
                $join->on('spend_metrics.ma_kh', '=', 'khach_hang.ma_kh');
            })
            ->where('khach_hang.role', UserRole::CUSTOMER)
            ->where('khach_hang.vai_tro', false)
            ->select('khach_hang.*')
            ->selectRaw('COALESCE(spend_metrics.completed_order_count, 0) as completed_order_count')
            ->selectRaw('COALESCE(spend_metrics.net_spent, 0) as calculated_total_spent');

        if ($search = $data['search'] ?? null) {
            $query->where(function ($q) use ($search) {
                $q->where('khach_hang.ma_kh', 'like', "%{$search}%")
                    ->orWhere('khach_hang.ten_kh', 'like', "%{$search}%")
                    ->orWhere('khach_hang.email', 'like', "%{$search}%")
                    ->orWhere('khach_hang.dien_thoai', 'like', "%{$search}%");
            });
        }
        if ($status = $data['status'] ?? null) {
            $query->where('khach_hang.trang_thai', $status === 'active' ? true : false);
        }

        if (($data['sort'] ?? 'joined_at') === 'total_spent') {
            $query->orderByDesc('calculated_total_spent')
                ->orderByDesc('completed_order_count')
                ->orderByDesc('khach_hang.ngay_tao');
        } else {
            $query->orderByDesc('khach_hang.ngay_tao');
        }

        $customers = $query->paginate(20);

        return response()->json([
            'data' => $customers->getCollection()->map(fn($kh) => [
                'id'          => $kh->ma_kh,
                'name'        => $kh->ten_kh,
                'email'       => $kh->email,
                'phone'       => $kh->dien_thoai,
                'status'      => $kh->trang_thai ? 'active' : 'blocked',
                'join_date'   => $kh->ngay_tao?->toDateString(),
                'order_count' => (int) $kh->completed_order_count,
                'total_spent' => (float) $kh->calculated_total_spent,
            ]),
            'meta' => [
                'total' => $customers->total(),
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'period' => $from && $to ? [
                    'from' => $from->toDateString(),
                    'to' => $to->toDateString(),
                ] : null,
            ],
        ]);
    }

    /** PUT /api/admin/customers/{id}/status — Khóa/mở tài khoản */
    public function toggleStatus(Request $request, string $id)
    {
        $customer = KhachHang::where('ma_kh', $id)
            ->where('role', UserRole::CUSTOMER)
            ->where('vai_tro', false)
            ->firstOrFail();
        $customer->update(['trang_thai' => !$customer->trang_thai]);

        $action = $customer->trang_thai ? 'mở khóa' : 'khóa';
        return response()->json([
            'message' => "Đã {$action} tài khoản thành công.",
            'status'  => $customer->trang_thai ? 'active' : 'blocked',
        ]);
    }
}
