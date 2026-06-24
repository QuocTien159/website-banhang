<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gia_tri_thuoc_tinh', function (Blueprint $table) {
            $table->char('ma_gt', 10)->primary();
            $table->char('ma_tt', 10);
            $table->string('gia_tri', 50);

            $table->foreign('ma_tt')->references('ma_tt')->on('thuoc_tinh')->onDelete('cascade');
        });

        Schema::create('bien_the_san_pham', function (Blueprint $table) {
            $table->char('ma_bt', 10)->primary();
            $table->char('ma_sp', 10);
            $table->char('sku', 20)->unique();
            $table->decimal('gia_ban', 12, 2);
            $table->integer('so_luong_ton')->default(0);
            $table->boolean('trang_thai')->default(true);

            $table->foreign('ma_sp')->references('ma_sp')->on('san_pham')->onDelete('cascade');
        });

        Schema::create('lien_ket_bien_the_gia_tri', function (Blueprint $table) {
            $table->char('ma_bt', 10);
            $table->char('ma_gt', 10);
            $table->primary(['ma_bt', 'ma_gt']);

            $table->foreign('ma_bt')->references('ma_bt')->on('bien_the_san_pham')->onDelete('cascade');
            $table->foreign('ma_gt')->references('ma_gt')->on('gia_tri_thuoc_tinh')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lien_ket_bien_the_gia_tri');
        Schema::dropIfExists('bien_the_san_pham');
        Schema::dropIfExists('gia_tri_thuoc_tinh');
    }
};
