<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('san_pham', function (Blueprint $table) {
            $table->char('ma_sp', 10)->primary();
            $table->char('ma_dm', 10);
            $table->string('ten_sp', 100);
            $table->text('mo_ta')->nullable();
            $table->decimal('gia_co_ban', 12, 2);
            $table->char('trang_thai', 20)->default('active')
                ->comment('active|inactive|out_of_stock');
            $table->dateTime('ngay_tao')->nullable();

            $table->foreign('ma_dm')->references('ma_dm')->on('danh_muc')
                ->onDelete('restrict');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('san_pham');
    }
};
