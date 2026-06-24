<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('danh_sach_yeu_thich', function (Blueprint $table) {
            $table->char('ma_kh', 10);
            $table->char('ma_sp', 10);
            $table->primary(['ma_kh', 'ma_sp']);

            $table->foreign('ma_kh')->references('ma_kh')->on('khach_hang')->onDelete('cascade');
            $table->foreign('ma_sp')->references('ma_sp')->on('san_pham')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('danh_sach_yeu_thich');
    }
};
