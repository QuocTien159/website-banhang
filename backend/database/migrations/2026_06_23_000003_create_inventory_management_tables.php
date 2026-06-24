<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bien_the_san_pham', function (Blueprint $table) {
            $table->unsignedInteger('nguong_canh_bao_ton')->default(5)->after('so_luong_ton');
        });

        Schema::create('phieu_nhap_kho', function (Blueprint $table) {
            $table->char('ma_pnk', 10)->primary();
            $table->string('ma_phieu', 50)->unique();
            $table->date('ngay_nhap');
            $table->char('ma_nguoi_nhap', 10);
            $table->text('ghi_chu')->nullable();
            $table->dateTime('ngay_tao');

            $table->foreign('ma_nguoi_nhap')->references('ma_kh')->on('khach_hang')->onDelete('restrict');
        });

        Schema::create('chi_tiet_phieu_nhap_kho', function (Blueprint $table) {
            $table->char('ma_pnk', 10);
            $table->char('ma_bien_the', 10);
            $table->unsignedInteger('so_luong');
            $table->text('ghi_chu')->nullable();
            $table->primary(['ma_pnk', 'ma_bien_the']);

            $table->foreign('ma_pnk')->references('ma_pnk')->on('phieu_nhap_kho')->onDelete('cascade');
            $table->foreign('ma_bien_the')->references('ma_bt')->on('bien_the_san_pham')->onDelete('restrict');
        });

        Schema::create('lich_su_bien_dong_kho', function (Blueprint $table) {
            $table->char('ma_ls_kho', 10)->primary();
            $table->char('ma_bien_the', 10);
            $table->string('loai_bien_dong', 30);
            $table->integer('so_luong_thay_doi');
            $table->unsignedInteger('ton_kho_truoc');
            $table->unsignedInteger('ton_kho_sau');
            $table->char('ma_nguoi_thuc_hien', 10)->nullable();
            $table->dateTime('thoi_gian');
            $table->text('ghi_chu')->nullable();
            $table->string('ma_tham_chieu', 50)->nullable();

            $table->foreign('ma_bien_the')->references('ma_bt')->on('bien_the_san_pham')->onDelete('restrict');
            $table->foreign('ma_nguoi_thuc_hien')->references('ma_kh')->on('khach_hang')->onDelete('set null');
            $table->index(['loai_bien_dong', 'thoi_gian']);
            $table->index('ma_tham_chieu');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lich_su_bien_dong_kho');
        Schema::dropIfExists('chi_tiet_phieu_nhap_kho');
        Schema::dropIfExists('phieu_nhap_kho');

        Schema::table('bien_the_san_pham', function (Blueprint $table) {
            $table->dropColumn('nguong_canh_bao_ton');
        });
    }
};
