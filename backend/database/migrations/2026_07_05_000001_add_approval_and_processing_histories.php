<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('phieu_nhap_kho', function (Blueprint $table) {
            $table->string('trang_thai', 20)->default('pending')->after('ghi_chu');
            $table->char('ma_nguoi_duyet', 10)->nullable()->after('trang_thai');
            $table->dateTime('ngay_duyet')->nullable()->after('ma_nguoi_duyet');
            $table->text('ghi_chu_duyet')->nullable()->after('ngay_duyet');

            $table->foreign('ma_nguoi_duyet')->references('ma_kh')->on('khach_hang')->onDelete('set null');
        });

        Schema::create('lich_su_xu_ly_don_hang', function (Blueprint $table) {
            $table->char('ma_ls_xl_dh', 10)->primary();
            $table->char('ma_dh', 10);
            $table->string('trang_thai_cu', 30)->nullable();
            $table->string('trang_thai_moi', 30);
            $table->char('ma_nguoi_xu_ly', 10)->nullable();
            $table->dateTime('thoi_gian_xu_ly');
            $table->text('ghi_chu')->nullable();

            $table->foreign('ma_dh')->references('ma_dh')->on('don_hang')->onDelete('cascade');
            $table->foreign('ma_nguoi_xu_ly')->references('ma_kh')->on('khach_hang')->onDelete('set null');
            $table->index(['ma_dh', 'thoi_gian_xu_ly']);
        });

        Schema::create('lich_su_xu_ly_tra_hang', function (Blueprint $table) {
            $table->char('ma_ls_xl_th', 10)->primary();
            $table->char('ma_yeu_cau', 10);
            $table->string('loai_thao_tac', 50);
            $table->string('gia_tri_cu', 255)->nullable();
            $table->string('gia_tri_moi', 255)->nullable();
            $table->char('ma_nguoi_xu_ly', 10)->nullable();
            $table->dateTime('thoi_gian_xu_ly');
            $table->text('ghi_chu')->nullable();

            $table->foreign('ma_yeu_cau')->references('ma_yeu_cau')->on('yeu_cau_tra_hang')->onDelete('cascade');
            $table->foreign('ma_nguoi_xu_ly')->references('ma_kh')->on('khach_hang')->onDelete('set null');
            $table->index(['ma_yeu_cau', 'thoi_gian_xu_ly']);
        });

        Schema::create('lich_su_xu_ly_danh_gia', function (Blueprint $table) {
            $table->char('ma_ls_xl_dg', 10)->primary();
            $table->char('ma_danh_gia', 10)->nullable();
            $table->string('loai_thao_tac', 50);
            $table->text('gia_tri_cu')->nullable();
            $table->text('gia_tri_moi')->nullable();
            $table->char('ma_nguoi_xu_ly', 10)->nullable();
            $table->dateTime('thoi_gian_xu_ly');
            $table->text('ghi_chu')->nullable();

            $table->foreign('ma_danh_gia')->references('ma_danh_gia')->on('danh_gia')->onDelete('set null');
            $table->foreign('ma_nguoi_xu_ly')->references('ma_kh')->on('khach_hang')->onDelete('set null');
            $table->index(['ma_danh_gia', 'thoi_gian_xu_ly']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lich_su_xu_ly_danh_gia');
        Schema::dropIfExists('lich_su_xu_ly_tra_hang');
        Schema::dropIfExists('lich_su_xu_ly_don_hang');

        Schema::table('phieu_nhap_kho', function (Blueprint $table) {
            $table->dropForeign(['ma_nguoi_duyet']);
            $table->dropColumn(['trang_thai', 'ma_nguoi_duyet', 'ngay_duyet', 'ghi_chu_duyet']);
        });
    }
};
