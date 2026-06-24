<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yeu_cau_tra_hang', function (Blueprint $table) {
            $table->char('ma_yeu_cau', 10)->primary();
            $table->char('ma_dh', 10);
            $table->text('ly_do');
            $table->char('trang_thai', 20)->default('pending')
                ->comment('pending|approved|rejected|refunded');
            $table->dateTime('ngay_yeu_cau');

            $table->foreign('ma_dh')->references('ma_dh')->on('don_hang')->onDelete('cascade');
        });

        Schema::create('chi_tiet_tra_hang', function (Blueprint $table) {
            $table->char('ma_yeu_cau', 10);
            $table->char('ma_bien_the', 10);
            $table->integer('so_luong');
            $table->string('ghi_chu', 255)->nullable();
            $table->primary(['ma_yeu_cau', 'ma_bien_the']);

            $table->foreign('ma_yeu_cau')->references('ma_yeu_cau')->on('yeu_cau_tra_hang')->onDelete('cascade');
            $table->foreign('ma_bien_the')->references('ma_bt')->on('bien_the_san_pham')->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chi_tiet_tra_hang');
        Schema::dropIfExists('yeu_cau_tra_hang');
    }
};
