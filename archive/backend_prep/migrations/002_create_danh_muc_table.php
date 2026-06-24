<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('danh_muc', function (Blueprint $table) {
            $table->char('ma_dm', 10)->primary();
            $table->string('ten_dm', 50)->unique();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('danh_muc');
    }
};
