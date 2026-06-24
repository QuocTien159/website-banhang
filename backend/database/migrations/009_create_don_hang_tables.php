<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('don_hang', function (Blueprint $table) {
            $table->char('ma_dh', 10)->primary();
            $table->char('ma_kh', 10);
            $table->dateTime('ngay_dat');
            $table->decimal('tong_tien', 12, 2);
            $table->string('phuong_thuc_tt', 20)->comment('cod|banking');
            $table->string('dia_chi_giao', 255);
            $table->char('trang_thai', 20)->default('pending')
                ->comment('pending|confirmed|shipping|delivered|cancelled');
            $table->text('ghi_chu')->nullable();

            $table->foreign('ma_kh')->references('ma_kh')->on('khach_hang')->onDelete('restrict');
        });

        Schema::create('chi_tiet_don_hang', function (Blueprint $table) {
            $table->char('ma_dh', 10);
            $table->char('ma_bien_the', 10);
            $table->integer('so_luong');
            $table->decimal('don_gia', 12, 2);
            $table->primary(['ma_dh', 'ma_bien_the']);

            $table->foreign('ma_dh')->references('ma_dh')->on('don_hang')->onDelete('cascade');
            $table->foreign('ma_bien_the')->references('ma_bt')->on('bien_the_san_pham')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chi_tiet_don_hang');
        Schema::dropIfExists('don_hang');
    }
};
