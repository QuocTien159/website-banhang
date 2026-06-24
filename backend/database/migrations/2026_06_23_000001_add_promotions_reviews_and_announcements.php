<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ma_khuyen_mai', function (Blueprint $table) {
            $table->char('ma_km', 10)->primary();
            $table->string('code', 50)->unique();
            $table->string('loai_giam', 20);
            $table->decimal('gia_tri', 12, 2);
            $table->decimal('don_toi_thieu', 12, 2)->default(0);
            $table->decimal('giam_toi_da', 12, 2)->nullable();
            $table->dateTime('bat_dau');
            $table->dateTime('ket_thuc');
            $table->unsignedInteger('gioi_han_su_dung')->nullable();
            $table->unsignedInteger('da_su_dung')->default(0);
            $table->boolean('trang_thai')->default(true);
        });

        Schema::create('lich_su_khuyen_mai', function (Blueprint $table) {
            $table->char('ma_km', 10);
            $table->char('ma_kh', 10);
            $table->char('ma_dh', 10)->unique();
            $table->decimal('so_tien_giam', 12, 2);
            $table->dateTime('ngay_su_dung');
            $table->primary(['ma_km', 'ma_kh']);
            $table->foreign('ma_km')->references('ma_km')->on('ma_khuyen_mai')->onDelete('restrict');
            $table->foreign('ma_kh')->references('ma_kh')->on('khach_hang')->onDelete('restrict');
            $table->foreign('ma_dh')->references('ma_dh')->on('don_hang')->onDelete('restrict');
        });

        Schema::table('don_hang', function (Blueprint $table) {
            $table->decimal('tam_tinh', 12, 2)->nullable()->after('ngay_dat');
            $table->decimal('phi_van_chuyen', 12, 2)->default(0)->after('tam_tinh');
            $table->char('ma_km', 10)->nullable()->after('phi_van_chuyen');
            $table->string('ma_khuyen_mai', 50)->nullable()->after('ma_km');
            $table->decimal('so_tien_giam', 12, 2)->default(0)->after('ma_khuyen_mai');
            $table->foreign('ma_km')->references('ma_km')->on('ma_khuyen_mai')->nullOnDelete();
        });

        Schema::table('danh_gia', function (Blueprint $table) {
            $table->char('ma_dh', 10)->nullable()->after('ma_sp');
            $table->string('trang_thai', 20)->default('pending')->after('noi_dung');
            $table->text('phan_hoi_admin')->nullable()->after('trang_thai');
            $table->dateTime('ngay_phan_hoi')->nullable()->after('phan_hoi_admin');
            $table->foreign('ma_dh')->references('ma_dh')->on('don_hang')->nullOnDelete();
            $table->unique(['ma_dh', 'ma_sp'], 'review_order_product_unique');
        });

        Schema::create('thong_bao', function (Blueprint $table) {
            $table->char('ma_tb', 10)->primary();
            $table->string('tieu_de', 150);
            $table->text('noi_dung');
            $table->string('loai', 20)->default('general');
            $table->string('trang_thai', 20)->default('draft');
            $table->dateTime('ngay_xuat_ban')->nullable();
            $table->dateTime('ngay_tao');
            $table->dateTime('ngay_cap_nhat')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thong_bao');
        Schema::table('danh_gia', function (Blueprint $table) {
            $table->dropUnique('review_order_product_unique');
            $table->dropForeign(['ma_dh']);
            $table->dropColumn(['ma_dh', 'trang_thai', 'phan_hoi_admin', 'ngay_phan_hoi']);
        });
        Schema::table('don_hang', function (Blueprint $table) {
            $table->dropForeign(['ma_km']);
            $table->dropColumn(['tam_tinh', 'phi_van_chuyen', 'ma_km', 'ma_khuyen_mai', 'so_tien_giam']);
        });
        Schema::dropIfExists('lich_su_khuyen_mai');
        Schema::dropIfExists('ma_khuyen_mai');
    }
};
