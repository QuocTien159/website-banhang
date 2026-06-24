<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GioHang;
use App\Services\PromotionService;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function validateCode(Request $request, PromotionService $service)
    {
        $data = $request->validate(['code' => 'required|string|max:50']);
        $cart = GioHang::with('chiTiets.bienThe')->where('ma_kh', $request->user()->ma_kh)->first();
        $subtotal = $cart?->chiTiets->sum(fn ($item) => $item->bienThe->gia_ban * $item->so_luong) ?? 0;
        $result = $service->validate($data['code'], $request->user()->ma_kh, $subtotal);
        return response()->json([
            'code' => $result['promotion']->code,
            'discount' => $result['discount'],
            'subtotal' => $subtotal,
            'total_after_discount' => max(0, $subtotal - $result['discount']),
        ]);
    }
}
