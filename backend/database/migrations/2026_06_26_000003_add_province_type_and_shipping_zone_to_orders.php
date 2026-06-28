<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('don_hang', function (Blueprint $table) {
            if (!Schema::hasColumn('don_hang', 'province_type')) {
                $table->string('province_type', 20)->nullable()->after('dia_chi_giao');
            }
            if (!Schema::hasColumn('don_hang', 'shipping_zone')) {
                $table->string('shipping_zone', 30)->nullable()->after('loai_khu_vuc_giao');
            }
        });
    }

    public function down(): void
    {
        Schema::table('don_hang', function (Blueprint $table) {
            foreach (['shipping_zone', 'province_type'] as $column) {
                if (Schema::hasColumn('don_hang', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
