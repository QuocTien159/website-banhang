<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('danh_gia', function (Blueprint $table) {
            $table->char('ma_danh_gia', 10)->primary();
            $table->char('ma_kh', 10);
            $table->char('ma_sp', 10);
            $table->tinyInteger('so_sao')->comment('1-5');
            $table->text('noi_dung');
            $table->dateTime('ngay_danh_gia');

            $table->foreign('ma_kh')->references('ma_kh')->on('khach_hang')->onDelete('cascade');
            $table->foreign('ma_sp')->references('ma_sp')->on('san_pham')->onDelete('cascade');
        });

        Schema::create('hinh_anh_danh_gia', function (Blueprint $table) {
            $table->char('ma_anh_dg', 10)->primary();
            $table->char('ma_danh_gia', 10);
            $table->string('url_anh', 255);
            $table->dateTime('ngay_tao');

            $table->foreign('ma_danh_gia')->references('ma_danh_gia')->on('danh_gia')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hinh_anh_danh_gia');
        Schema::dropIfExists('danh_gia');
    }
};
