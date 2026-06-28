<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thuoc_tinh', function (Blueprint $table) {
            if (!Schema::hasColumn('thuoc_tinh', 'slug')) {
                $table->string('slug', 80)->nullable()->after('ten_tt');
            }
            if (!Schema::hasColumn('thuoc_tinh', 'loai_hien_thi')) {
                $table->string('loai_hien_thi', 20)->default('select')->after('slug');
            }
            if (!Schema::hasColumn('thuoc_tinh', 'trang_thai')) {
                $table->boolean('trang_thai')->default(true)->after('loai_hien_thi');
            }
            if (!Schema::hasColumn('thuoc_tinh', 'mo_ta')) {
                $table->text('mo_ta')->nullable()->after('trang_thai');
            }
            if (!Schema::hasColumn('thuoc_tinh', 'ngay_tao')) {
                $table->timestamp('ngay_tao')->nullable()->after('mo_ta');
            }
            if (!Schema::hasColumn('thuoc_tinh', 'ngay_cap_nhat')) {
                $table->timestamp('ngay_cap_nhat')->nullable()->after('ngay_tao');
            }
        });

        Schema::table('gia_tri_thuoc_tinh', function (Blueprint $table) {
            if (!Schema::hasColumn('gia_tri_thuoc_tinh', 'slug')) {
                $table->string('slug', 80)->nullable()->after('gia_tri');
            }
            if (!Schema::hasColumn('gia_tri_thuoc_tinh', 'ma_mau')) {
                $table->string('ma_mau', 20)->nullable()->after('slug');
            }
            if (!Schema::hasColumn('gia_tri_thuoc_tinh', 'thu_tu')) {
                $table->unsignedInteger('thu_tu')->default(0)->after('ma_mau');
            }
            if (!Schema::hasColumn('gia_tri_thuoc_tinh', 'trang_thai')) {
                $table->boolean('trang_thai')->default(true)->after('thu_tu');
            }
            if (!Schema::hasColumn('gia_tri_thuoc_tinh', 'ngay_tao')) {
                $table->timestamp('ngay_tao')->nullable()->after('trang_thai');
            }
            if (!Schema::hasColumn('gia_tri_thuoc_tinh', 'ngay_cap_nhat')) {
                $table->timestamp('ngay_cap_nhat')->nullable()->after('ngay_tao');
            }
        });

        $now = now();

        DB::table('thuoc_tinh')->orderBy('ma_tt')->get()->each(function ($attribute) use ($now) {
            $baseSlug = Str::slug($attribute->ten_tt) ?: Str::lower($attribute->ma_tt);
            $slug = $baseSlug;
            $index = 2;
            while (DB::table('thuoc_tinh')->where('slug', $slug)->where('ma_tt', '!=', $attribute->ma_tt)->exists()) {
                $slug = "{$baseSlug}-{$index}";
                $index++;
            }

            DB::table('thuoc_tinh')->where('ma_tt', $attribute->ma_tt)->update([
                'slug' => $attribute->slug ?: $slug,
                'loai_hien_thi' => $attribute->loai_hien_thi ?: 'select',
                'trang_thai' => $attribute->trang_thai ?? true,
                'ngay_tao' => $attribute->ngay_tao ?? $now,
                'ngay_cap_nhat' => $attribute->ngay_cap_nhat ?? $now,
            ]);
        });

        DB::table('gia_tri_thuoc_tinh')->orderBy('ma_gt')->get()->each(function ($value) use ($now) {
            $baseSlug = Str::slug($value->gia_tri) ?: Str::lower($value->ma_gt);
            $slug = $baseSlug;
            $index = 2;
            while (
                DB::table('gia_tri_thuoc_tinh')
                    ->where('ma_tt', $value->ma_tt)
                    ->where('slug', $slug)
                    ->where('ma_gt', '!=', $value->ma_gt)
                    ->exists()
            ) {
                $slug = "{$baseSlug}-{$index}";
                $index++;
            }

            DB::table('gia_tri_thuoc_tinh')->where('ma_gt', $value->ma_gt)->update([
                'slug' => $value->slug ?: $slug,
                'trang_thai' => $value->trang_thai ?? true,
                'ngay_tao' => $value->ngay_tao ?? $now,
                'ngay_cap_nhat' => $value->ngay_cap_nhat ?? $now,
            ]);
        });
    }

    public function down(): void
    {
        Schema::table('gia_tri_thuoc_tinh', function (Blueprint $table) {
            foreach (['ngay_cap_nhat', 'ngay_tao', 'trang_thai', 'thu_tu', 'ma_mau', 'slug'] as $column) {
                if (Schema::hasColumn('gia_tri_thuoc_tinh', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('thuoc_tinh', function (Blueprint $table) {
            foreach (['ngay_cap_nhat', 'ngay_tao', 'mo_ta', 'trang_thai', 'loai_hien_thi', 'slug'] as $column) {
                if (Schema::hasColumn('thuoc_tinh', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
