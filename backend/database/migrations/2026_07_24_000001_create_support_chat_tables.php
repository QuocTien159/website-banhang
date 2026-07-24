<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cuoc_tro_chuyen_ho_tro', function (Blueprint $table) {
            $table->string('ma_ct', 10)->primary();
            $table->string('ma_kh', 10)->unique();
            $table->string('ma_nv_phu_trach', 10)->nullable();
            $table->string('trang_thai', 20)->default('waiting');
            $table->timestamp('khach_hang_da_doc_luc')->nullable();
            $table->timestamp('nhan_vien_da_doc_luc')->nullable();
            $table->timestamp('tin_nhan_cuoi_luc')->nullable();
            $table->timestamp('ngay_tao');
            $table->timestamp('ngay_cap_nhat')->nullable();

            $table->foreign('ma_kh')->references('ma_kh')->on('khach_hang')->cascadeOnDelete();
            $table->foreign('ma_nv_phu_trach')->references('ma_kh')->on('khach_hang')->nullOnDelete();
            $table->index(['trang_thai', 'tin_nhan_cuoi_luc']);
            $table->index(['ma_nv_phu_trach', 'trang_thai']);
        });

        Schema::create('tin_nhan_ho_tro', function (Blueprint $table) {
            $table->string('ma_tn', 10)->primary();
            $table->string('ma_ct', 10);
            $table->string('ma_nguoi_gui', 10)->nullable();
            $table->string('vai_tro_nguoi_gui', 20);
            $table->text('noi_dung');
            $table->timestamp('ngay_gui');

            $table->foreign('ma_ct')->references('ma_ct')->on('cuoc_tro_chuyen_ho_tro')->cascadeOnDelete();
            $table->foreign('ma_nguoi_gui')->references('ma_kh')->on('khach_hang')->nullOnDelete();
            $table->index(['ma_ct', 'ngay_gui']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tin_nhan_ho_tro');
        Schema::dropIfExists('cuoc_tro_chuyen_ho_tro');
    }
};
