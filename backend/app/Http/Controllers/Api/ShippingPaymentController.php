<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GioHang;
use App\Services\ShippingPaymentService;
use App\Services\VietnamAdministrativeService;
use Illuminate\Http\Request;

class ShippingPaymentController extends Controller
{
    public function calculate(Request $request, ShippingPaymentService $service)
    {
        $data = $request->validate([
            'province_type' => ['nullable', 'string', 'max:20'],
            'district_code' => ['nullable', 'string', 'max:20'],
            'ward_code' => ['nullable', 'string', 'max:20'],
            'address_detail' => ['nullable', 'string', 'max:255'],
        ]);

        $cart = GioHang::with('chiTiets.bienThe')
            ->where('ma_kh', $request->user()->ma_kh)
            ->first();
        $subtotal = $cart?->chiTiets?->sum(fn ($item) => $item->bienThe?->gia_ban * $item->so_luong) ?? 0;

        return response()->json($service->calculateShipping(
            (float) $subtotal,
            $data['province_type'] ?? null,
            $data['district_code'] ?? null,
            $data['ward_code'] ?? null,
            $data['address_detail'] ?? null
        ));
    }

    public function bankInfo(ShippingPaymentService $service)
    {
        return response()->json($service->bankInfo());
    }

    public function provinces(VietnamAdministrativeService $service)
    {
        return response()->json($service->provinces());
    }

    public function districts(Request $request, VietnamAdministrativeService $service)
    {
        $data = $request->validate(['province_type' => ['required', 'string', 'max:20']]);
        return response()->json($service->districts($data['province_type']));
    }

    public function wards(Request $request, VietnamAdministrativeService $service)
    {
        $data = $request->validate(['district_code' => ['required', 'string', 'max:20']]);
        return response()->json($service->wards($data['district_code']));
    }
}
