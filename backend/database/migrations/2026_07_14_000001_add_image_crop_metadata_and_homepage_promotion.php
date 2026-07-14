<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['hinh_anh_san_pham', 'hinh_anh_thong_bao'] as $tableName) {
            if (!Schema::hasColumn($tableName, 'original_url')) Schema::table($tableName, function (Blueprint $table) {
                $table->string('original_url', 500)->nullable();
                $table->unsignedBigInteger('kich_thuoc_byte')->nullable();
                $table->string('dinh_dang', 20)->nullable();
                $table->unsignedInteger('crop_x')->nullable();
                $table->unsignedInteger('crop_y')->nullable();
                $table->unsignedInteger('crop_width')->nullable();
                $table->unsignedInteger('crop_height')->nullable();
                $table->decimal('goc_xoay', 6, 2)->default(0);
                $table->string('ty_le_khung_hinh', 10)->nullable();
                $table->string('vai_tro_anh', 30)->nullable();
            });
        }

        DB::table('hinh_anh_san_pham')->update([
            'original_url' => DB::raw('url'),
            'vai_tro_anh' => DB::raw("CASE WHEN ma_bt IS NULL THEN 'product' ELSE 'variant' END"),
        ]);
        DB::table('hinh_anh_thong_bao')->update([
            'original_url' => DB::raw('url'),
            'vai_tro_anh' => 'announcement_content',
        ]);

        if (!Schema::hasTable('uu_dai_trang_chu')) Schema::create('uu_dai_trang_chu', function (Blueprint $table) {
            $table->char('ma_uu_dai', 10)->primary();
            $table->boolean('kich_hoat')->default(false);
            $table->char('ma_km', 10)->nullable();
            $table->string('nhan', 80)->nullable();
            $table->string('tieu_de', 180)->nullable();
            $table->string('mo_ta', 500)->nullable();
            $table->string('cta_text', 80)->nullable();
            $table->string('cta_url', 255)->nullable();
            $table->dateTime('bat_dau_hien_thi')->nullable();
            $table->dateTime('ket_thuc_hien_thi')->nullable();
            $table->unsignedInteger('thu_tu')->default(0);
            $table->string('banner_url', 500)->nullable();
            $table->string('banner_original_url', 500)->nullable();
            $table->string('banner_provider', 30)->nullable();
            $table->string('banner_cloudinary_public_id', 255)->nullable();
            $table->unsignedInteger('banner_width')->nullable();
            $table->unsignedInteger('banner_height')->nullable();
            $table->unsignedBigInteger('banner_kich_thuoc_byte')->nullable();
            $table->string('banner_dinh_dang', 20)->nullable();
            $table->unsignedInteger('banner_crop_x')->nullable();
            $table->unsignedInteger('banner_crop_y')->nullable();
            $table->unsignedInteger('banner_crop_width')->nullable();
            $table->unsignedInteger('banner_crop_height')->nullable();
            $table->decimal('banner_goc_xoay', 6, 2)->default(0);
            $table->string('banner_ty_le_khung_hinh', 10)->nullable();
            $table->dateTime('ngay_tao');
            $table->dateTime('ngay_cap_nhat')->nullable();
            $table->foreign('ma_km')->references('ma_km')->on('ma_khuyen_mai')->nullOnDelete();
        });
        Schema::table('uu_dai_trang_chu', function (Blueprint $table) {
            $table->index(['kich_hoat', 'bat_dau_hien_thi', 'ket_thuc_hien_thi'], 'home_promo_active_window_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('uu_dai_trang_chu');
        foreach (['hinh_anh_san_pham', 'hinh_anh_thong_bao'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropColumn([
                    'original_url', 'kich_thuoc_byte', 'dinh_dang', 'crop_x', 'crop_y',
                    'crop_width', 'crop_height', 'goc_xoay', 'ty_le_khung_hinh', 'vai_tro_anh',
                ]);
            });
        }
    }
};
