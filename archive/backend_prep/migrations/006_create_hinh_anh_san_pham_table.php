<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hinh_anh_san_pham', function (Blueprint $table) {
            $table->char('ma_anh', 10)->primary();
            $table->char('ma_sp', 10);
            $table->string('url', 255);
            $table->boolean('anh_chinh')->default(false);

            $table->foreign('ma_sp')->references('ma_sp')->on('san_pham')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hinh_anh_san_pham');
    }
};
