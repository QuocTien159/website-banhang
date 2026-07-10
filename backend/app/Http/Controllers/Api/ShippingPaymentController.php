<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GioHang;
use App\Services\GhnShippingService;
use App\Services\ShippingPaymentService;
use Illuminate\Http\Request;

class ShippingPaymentController extends Controller
{
    public function calculate(Request $request, ShippingPaymentService $service)
    {
        $data = $request->validate([
            'province_id' => ['required', 'string', 'max:20'],
            'district_code' => ['required', 'string', 'max:20'],
            'ward_code' => ['required', 'string', 'max:20'],
            'address_detail' => ['required', 'string', 'max:255'],
        ]);
        $cart = GioHang::with('chiTiets.bienThe.sanPham')->where('ma_kh', $request->user()->ma_kh)->first();
        $subtotal = $cart?->chiTiets?->sum(fn ($item) => $item->bienThe?->gia_ban * $item->so_luong) ?? 0;

        return response()->json($service->calculateShipping(
            (float) $subtotal, $data['district_code'], $data['ward_code'], $data['address_detail'], $data['province_id'],
            $cart?->chiTiets?->map(fn ($item) => [
                'name' => $item->bienThe?->sanPham?->ten_sp ?? $item->ma_bien_the,
                'quantity' => (int) $item->so_luong,
                'price' => (int) round((float) ($item->bienThe?->gia_ban ?? 0)),
            ])->values()->all() ?? []
        ));
    }

    public function bankInfo(ShippingPaymentService $service)
    {
        return response()->json($service->bankInfo());
    }

    public function provinces(GhnShippingService $ghnShippingService)
    {
        return $this->addressResponse(fn () => $ghnShippingService->provinces(), $ghnShippingService);
    }

    public function districts(Request $request, GhnShippingService $ghnShippingService)
    {
        $data = $request->validate(['province_id' => ['required', 'string', 'max:20']]);
        return $this->addressResponse(fn () => $ghnShippingService->districts($data['province_id']), $ghnShippingService);
    }

    public function wards(Request $request, GhnShippingService $ghnShippingService)
    {
        $data = $request->validate(['district_code' => ['required', 'string', 'max:20']]);
        return $this->addressResponse(fn () => $ghnShippingService->wards($data['district_code']), $ghnShippingService);
    }

    private function addressResponse(callable $callback, GhnShippingService $ghnShippingService)
    {
        if (!$ghnShippingService->isConfigured()) {
            return response()->json(['data' => [], 'message' => 'Dữ liệu địa chỉ GHN chưa sẵn sàng.']);
        }

        try {
            return response()->json(['data' => $callback(), 'message' => null]);
        } catch (\Throwable $exception) {
            return response()->json(['data' => [], 'message' => $ghnShippingService->friendlyMessage($exception)]);
        }
    }
}
