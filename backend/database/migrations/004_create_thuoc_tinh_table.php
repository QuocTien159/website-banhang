<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('thuoc_tinh', function (Blueprint $table) {
            $table->char('ma_tt', 10)->primary();
            $table->string('ten_tt', 50);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thuoc_tinh');
    }
};
