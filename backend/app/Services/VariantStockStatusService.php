<?php

namespace App\Services;

use App\Models\BienTheSanPham;
use Illuminate\Database\Eloquent\Builder;

class VariantStockStatusService
{
    public function alertQuery(): Builder
    {
        return BienTheSanPham::query()
            ->where('trang_thai', true)
            ->where('trang_thai_ban', 'active')
            ->whereHas('sanPham', fn (Builder $query) => $query->where('trang_thai', 'active'))
            ->whereColumn('so_luong_ton', '<=', 'nguong_canh_bao_ton');
    }

    public function status(BienTheSanPham $variant): string
    {
        return $variant->stockStatus();
    }

    public function isAlert(BienTheSanPham $variant): bool
    {
        return in_array($this->status($variant), ['out_of_stock', 'low_stock'], true);
    }
}
