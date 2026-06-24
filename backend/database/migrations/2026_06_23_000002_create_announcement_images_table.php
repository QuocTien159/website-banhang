<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('hinh_anh_thong_bao', function (Blueprint $table) {
            $table->char('ma_anh_tb', 10)->primary();
            $table->char('ma_tb', 10);
            $table->string('url', 500);
            $table->string('duong_dan', 255)->nullable();
            $table->unsignedInteger('thu_tu')->default(0);
            $table->dateTime('ngay_tao');

            $table->foreign('ma_tb')->references('ma_tb')->on('thong_bao')->onDelete('cascade');
            $table->index(['ma_tb', 'thu_tu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hinh_anh_thong_bao');
    }
};
