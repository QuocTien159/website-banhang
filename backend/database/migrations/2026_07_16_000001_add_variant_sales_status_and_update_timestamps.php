<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('bien_the_san_pham', function (Blueprint $table) {
            $table->decimal('gia_niem_yet', 12, 2)->nullable()->after('gia_ban');
            $table->string('trang_thai_ban', 20)->default('active')->after('trang_thai');
            $table->dateTime('ngay_cap_nhat')->nullable()->after('trang_thai_ban');
            $table->index(['trang_thai_ban', 'so_luong_ton'], 'variant_sales_stock_index');
        });

        Schema::table('san_pham', function (Blueprint $table) {
            $table->dateTime('ngay_cap_nhat')->nullable()->after('ngay_tao');
        });

        DB::table('bien_the_san_pham')->update([
            'gia_niem_yet' => DB::raw('gia_ban'),
            'trang_thai_ban' => DB::raw("CASE WHEN trang_thai = 1 THEN 'active' ELSE 'inactive' END"),
            'ngay_cap_nhat' => now(),
        ]);

        DB::table('san_pham')->update([
            'ngay_cap_nhat' => DB::raw('ngay_tao'),
        ]);
    }

    public function down(): void
    {
        Schema::table('bien_the_san_pham', function (Blueprint $table) {
            $table->dropIndex('variant_sales_stock_index');
            $table->dropColumn(['gia_niem_yet', 'trang_thai_ban', 'ngay_cap_nhat']);
        });

        Schema::table('san_pham', function (Blueprint $table) {
            $table->dropColumn('ngay_cap_nhat');
        });
    }
};
