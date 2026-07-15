<?php

namespace App\Console\Commands;

use App\Models\HinhAnhSanPham;
use App\Models\SanPham;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveRedundantVariantImages extends Command
{
    protected $signature = 'catalog:remove-redundant-variant-images {--dry-run : Only report redundant image mappings}';

    protected $description = 'Remove duplicate image-to-SKU mappings while keeping one reusable image per original asset';

    public function handle(): int
    {
        $removed = 0;

        SanPham::query()->with('hinhAnhs')->orderBy('ma_sp')->each(function (SanPham $product) use (&$removed) {
            $duplicates = $product->hinhAnhs
                ->groupBy(fn (HinhAnhSanPham $image) => $image->original_url ?: $image->url)
                ->flatMap(function ($images) {
                    if ($images->count() < 2) return collect();

                    $genericImages = $images->whereNull('ma_bt');
                    if ($genericImages->isNotEmpty()) {
                        return $images->whereNotNull('ma_bt');
                    }

                    // Keep the oldest mapping for an asset. The product page can reuse it
                    // for visual attributes such as color instead of duplicating it by size.
                    return $images->sortBy([['thu_tu', 'asc'], ['ma_anh', 'asc']])->slice(1);
                })
                ->values();

            if ($duplicates->isEmpty()) return;
            if ($this->option('dry-run')) {
                $this->line("{$product->ma_sp}: có {$duplicates->count()} liên kết ảnh trùng.");
                return;
            }

            DB::transaction(fn () => HinhAnhSanPham::whereIn('ma_anh', $duplicates->pluck('ma_anh'))->delete());
            $removed += $duplicates->count();
            $this->line("{$product->ma_sp}: đã bỏ {$duplicates->count()} liên kết ảnh trùng.");
        });

        $this->info("Hoàn tất: đã bỏ {$removed} liên kết ảnh trùng. Asset gốc không bị xóa.");
        return self::SUCCESS;
    }
}
