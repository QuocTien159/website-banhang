<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ChiTietDonHang;
use App\Models\ChiTietTraHang;
use App\Models\DonHang;
use App\Models\HinhAnhTraHang;
use App\Models\YeuCauTraHang;
use App\Support\OrderStatus;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

class ReturnRequestController extends Controller
{
    public function index(Request $request)
    {
        $returns = YeuCauTraHang::with(['donHang', 'chiTiets.bienThe', 'hinhAnhs'])
            ->where('ma_kh', $request->user()->ma_kh)
            ->orderByDesc('ngay_yeu_cau')
            ->get();

        return response()->json($returns->map(fn (YeuCauTraHang $return) => $this->format($return)));
    }

    public function show(Request $request, string $id)
    {
        $return = YeuCauTraHang::with(['donHang', 'chiTiets.bienThe', 'hinhAnhs'])
            ->where('ma_kh', $request->user()->ma_kh)
            ->where('ma_yeu_cau', $id)
            ->firstOrFail();

        return response()->json($this->format($return, true));
    }

    public function uploadImages(Request $request)
    {
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:5'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=200,min_height=200'],
        ]);

        $images = collect($data['images'])->map(function (UploadedFile $image) {
            return Storage::disk('public')->url($image->store('returns', 'public'));
        })->values();

        return response()->json(['images' => $images], 201);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'order_id' => ['required', 'exists:don_hang,ma_dh'],
            'reason' => ['required', 'string', 'max:1000'],
            'description' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.variant_id' => ['required', 'exists:bien_the_san_pham,ma_bt'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.reason' => ['required', 'string', 'max:255'],
            'items.*.description' => ['nullable', 'string', 'max:1000'],
            'items.*.images' => ['nullable', 'array', 'max:5'],
            'items.*.images.*' => ['url', 'max:500'],
        ]);

        $user = $request->user();
        $order = DonHang::with('chiTiets.bienThe')
            ->where('ma_dh', $data['order_id'])
            ->where('ma_kh', $user->ma_kh)
            ->firstOrFail();

        if (!OrderStatus::isFulfilled($order->trang_thai)) {
            throw ValidationException::withMessages(['order_id' => 'Chỉ đơn đã giao thành công mới được yêu cầu trả hàng.']);
        }

        if ($order->yeuCauTraHangs()->where('trang_thai', 'pending')->exists()) {
            throw ValidationException::withMessages(['order_id' => 'Đơn hàng đang có yêu cầu trả hàng chờ xử lý.']);
        }

        $items = collect($data['items']);
        if ($items->pluck('variant_id')->unique()->count() !== $items->count()) {
            throw ValidationException::withMessages(['items' => 'Không được chọn trùng cùng một sản phẩm trong yêu cầu trả hàng.']);
        }

        foreach ($items as $index => $item) {
            $orderItem = $order->chiTiets->firstWhere('ma_bien_the', $item['variant_id']);
            if (!$orderItem) {
                throw ValidationException::withMessages(["items.{$index}.variant_id" => 'Sản phẩm không thuộc đơn hàng này.']);
            }

            $alreadyReturned = ChiTietTraHang::where('ma_bien_the', $item['variant_id'])
                ->whereHas('yeuCauTraHang', fn ($query) => $query
                    ->where('ma_dh', $order->ma_dh)
                    ->whereNotIn('trang_thai', ['rejected', 'cancelled']))
                ->sum('so_luong');
            $available = $orderItem->so_luong - $alreadyReturned;

            if ($item['quantity'] > $available) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => "Số lượng có thể trả còn lại là {$available}.",
                ]);
            }
        }

        $return = DB::transaction(function () use ($data, $user, $order, $items) {
            $return = YeuCauTraHang::create([
                'ma_dh' => $order->ma_dh,
                'ma_kh' => $user->ma_kh,
                'ly_do' => $data['reason'],
                'mo_ta' => $data['description'] ?? null,
                'trang_thai' => 'pending',
                'trang_thai_hoan_tien' => 'not_refunded',
                'ngay_yeu_cau' => now(),
                'ngay_cap_nhat' => now(),
            ]);

            foreach ($items as $item) {
                $orderItem = $order->chiTiets->firstWhere('ma_bien_the', $item['variant_id']);
                ChiTietTraHang::create([
                    'ma_yeu_cau' => $return->ma_yeu_cau,
                    'ma_bien_the' => $item['variant_id'],
                    'ma_sp' => $orderItem->bienThe?->ma_sp,
                    'so_luong' => $item['quantity'],
                    'ly_do' => $item['reason'],
                    'mo_ta' => $item['description'] ?? null,
                ]);

                foreach ($item['images'] ?? [] as $url) {
                    HinhAnhTraHang::create([
                        'ma_yeu_cau' => $return->ma_yeu_cau,
                        'ma_bien_the' => $item['variant_id'],
                        'url_anh' => $url,
                        'ngay_tao' => now(),
                    ]);
                }
            }

            return $return;
        });

        return response()->json([
            'message' => 'Đã gửi yêu cầu trả hàng. Yêu cầu đang chờ xử lý.',
            'return_request' => $this->format($return->fresh(['donHang', 'chiTiets.bienThe', 'hinhAnhs']), true),
        ], 201);
    }

    public function cancel(Request $request, string $id)
    {
        $return = YeuCauTraHang::where('ma_yeu_cau', $id)
            ->where('ma_kh', $request->user()->ma_kh)
            ->firstOrFail();

        if ($return->trang_thai !== 'pending') {
            throw ValidationException::withMessages(['status' => 'Chỉ có thể hủy yêu cầu đang chờ xử lý.']);
        }

        $return->update(['trang_thai' => 'cancelled', 'ngay_cap_nhat' => now()]);

        return response()->json(['message' => 'Đã hủy yêu cầu trả hàng.']);
    }

    protected function format(YeuCauTraHang $return, bool $detail = false): array
    {
        return [
            'id' => $return->ma_yeu_cau,
            'code' => $return->ma_yeu_cau,
            'order_id' => $return->ma_dh,
            'status' => $return->trang_thai,
            'refund_status' => $return->trang_thai_hoan_tien,
            'reason' => $return->ly_do,
            'description' => $return->mo_ta,
            'admin_note' => $return->ghi_chu_admin,
            'reject_reason' => $return->ly_do_tu_choi,
            'created_at' => $return->ngay_yeu_cau?->toISOString(),
            'items_count' => $return->chiTiets->sum('so_luong'),
            'items' => $return->chiTiets->map(fn (ChiTietTraHang $item) => [
                'variant_id' => $item->ma_bien_the,
                'product_id' => $item->ma_sp ?? $item->bienThe?->ma_sp,
                'product_name' => $item->bienThe?->sanPham?->ten_sp,
                'sku' => $item->bienThe?->sku,
                'image' => $item->bienThe?->sanPham?->anhChinh?->url,
                'quantity' => $item->so_luong,
                'reason' => $item->ly_do,
                'description' => $item->mo_ta,
                'images' => $return->hinhAnhs->where('ma_bien_the', $item->ma_bien_the)->pluck('url_anh')->values(),
            ])->values(),
        ];
    }
}
