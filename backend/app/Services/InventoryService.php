<?php

namespace App\Services;

use App\Models\BienTheSanPham;
use App\Models\LichSuBienDongKho;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function changeStock(
        string $variantId,
        int $quantityChange,
        string $type,
        ?string $actorId = null,
        ?string $note = null,
        ?string $reference = null
    ): BienTheSanPham {
        if ($quantityChange === 0) {
            throw ValidationException::withMessages(['quantity' => 'Số lượng thay đổi phải khác 0.']);
        }

        $variant = BienTheSanPham::where('ma_bt', $variantId)->lockForUpdate()->firstOrFail();
        $before = (int) $variant->so_luong_ton;
        $after = $before + $quantityChange;

        if ($after < 0) {
            throw ValidationException::withMessages([
                'quantity' => "Tồn kho của SKU {$variant->sku} không đủ để trừ {$quantityChange}.",
            ]);
        }

        $variant->update(['so_luong_ton' => $after]);

        LichSuBienDongKho::create([
            'ma_bien_the' => $variant->ma_bt,
            'loai_bien_dong' => $type,
            'so_luong_thay_doi' => $quantityChange,
            'ton_kho_truoc' => $before,
            'ton_kho_sau' => $after,
            'ma_nguoi_thuc_hien' => $actorId,
            'thoi_gian' => now(),
            'ghi_chu' => $note,
            'ma_tham_chieu' => $reference,
        ]);

        return $variant->fresh();
    }

    public function adjustStock(
        string $variantId,
        int $targetStock,
        string $actorId,
        string $reason
    ): BienTheSanPham {
        $variant = BienTheSanPham::where('ma_bt', $variantId)->lockForUpdate()->firstOrFail();
        $change = $targetStock - (int) $variant->so_luong_ton;

        if ($change === 0) {
            throw ValidationException::withMessages(['stock' => 'Tồn kho mới không thay đổi so với hiện tại.']);
        }

        return $this->changeStock($variantId, $change, 'manual_adjustment', $actorId, $reason, 'ADJUST');
    }
}
