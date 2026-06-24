<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gio_hang', function (Blueprint $table) {
            $table->char('ma_gio_hang', 10)->primary();
            $table->char('ma_kh', 10)->unique();

            $table->foreign('ma_kh')->references('ma_kh')->on('khach_hang')->onDelete('cascade');
        });

        Schema::create('chi_tiet_gio_hang', function (Blueprint $table) {
            $table->char('ma_gio_hang', 10);
            $table->char('ma_bien_the', 10);
            $table->integer('so_luong')->default(1);
            $table->primary(['ma_gio_hang', 'ma_bien_the']);

            $table->foreign('ma_gio_hang')->references('ma_gio_hang')->on('gio_hang')->onDelete('cascade');
            $table->foreign('ma_bien_the')->references('ma_bt')->on('bien_the_san_pham')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chi_tiet_gio_hang');
        Schema::dropIfExists('gio_hang');
    }
};
