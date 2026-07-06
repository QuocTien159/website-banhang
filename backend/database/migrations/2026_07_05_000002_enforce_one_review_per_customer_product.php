<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('danh_gia', 'ngay_cap_nhat')) {
            Schema::table('danh_gia', function (Blueprint $table) {
                $table->dateTime('ngay_cap_nhat')->nullable()->after('ngay_danh_gia');
            });
        }

        $duplicates = DB::table('danh_gia')
            ->select('ma_kh', 'ma_sp', DB::raw('COUNT(*) as total'))
            ->groupBy('ma_kh', 'ma_sp')
            ->having('total', '>', 1)
            ->get();

        foreach ($duplicates as $duplicate) {
            $reviews = DB::table('danh_gia')
                ->where('ma_kh', $duplicate->ma_kh)
                ->where('ma_sp', $duplicate->ma_sp)
                ->orderByRaw('COALESCE(ngay_cap_nhat, ngay_danh_gia) DESC')
                ->orderByDesc('ma_danh_gia')
                ->pluck('ma_danh_gia');

            $keepId = $reviews->first();
            $removeIds = $reviews->filter(fn ($id) => $id !== $keepId)->values();

            if ($removeIds->isNotEmpty()) {
                DB::table('danh_gia')->whereIn('ma_danh_gia', $removeIds)->delete();
            }
        }

        try {
            Schema::table('danh_gia', function (Blueprint $table) {
                $table->dropUnique('review_order_product_unique');
            });
        } catch (Throwable) {
            // Older databases may not have this index.
        }

        Schema::table('danh_gia', function (Blueprint $table) {
            $table->unique(['ma_kh', 'ma_sp'], 'review_customer_product_unique');
        });
    }

    public function down(): void
    {
        try {
            Schema::table('danh_gia', function (Blueprint $table) {
                $table->dropUnique('review_customer_product_unique');
            });
        } catch (Throwable) {
            // Index may already be absent.
        }

        try {
            Schema::table('danh_gia', function (Blueprint $table) {
                $table->unique(['ma_dh', 'ma_sp'], 'review_order_product_unique');
            });
        } catch (Throwable) {
            // Duplicate old data can make restoring the legacy index impossible.
        }
    }
};
