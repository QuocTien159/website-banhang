<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hinh_anh_san_pham', function (Blueprint $table) {
            $table->char('ma_bt', 10)->nullable()->after('ma_sp');
            $table->foreign('ma_bt')->references('ma_bt')->on('bien_the_san_pham')->nullOnDelete();
            $table->index('ma_bt');
        });
    }

    public function down(): void
    {
        Schema::table('hinh_anh_san_pham', function (Blueprint $table) {
            $table->dropForeign(['ma_bt']);
            $table->dropIndex(['ma_bt']);
            $table->dropColumn('ma_bt');
        });
    }
};
