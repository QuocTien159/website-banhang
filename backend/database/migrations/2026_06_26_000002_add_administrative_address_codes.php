<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('don_hang', function (Blueprint $table) {
            if (!Schema::hasColumn('don_hang', 'ma_tinh_thanh')) {
                $table->string('ma_tinh_thanh', 20)->nullable()->after('dia_chi_giao');
            }
            if (!Schema::hasColumn('don_hang', 'ma_quan_huyen')) {
                $table->string('ma_quan_huyen', 20)->nullable()->after('ma_tinh_thanh');
            }
            if (!Schema::hasColumn('don_hang', 'ma_phuong_xa')) {
                $table->string('ma_phuong_xa', 20)->nullable()->after('ma_quan_huyen');
            }
        });

        Schema::table('cau_hinh_thanh_toan_van_chuyen', function (Blueprint $table) {
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'ma_tinh_thanh_shop')) {
                $table->string('ma_tinh_thanh_shop', 20)->default('79')->after('tinh_thanh_shop');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'ma_quan_huyen_noi_thanh')) {
                $table->json('ma_quan_huyen_noi_thanh')->nullable()->after('quan_huyen_noi_thanh');
            }
        });

        DB::table('cau_hinh_thanh_toan_van_chuyen')->update([
            'ma_tinh_thanh_shop' => '79',
            'ma_quan_huyen_noi_thanh' => json_encode(['760', '770', '773', '774', '771', '772', '765', '768', '766', '764']),
        ]);
    }

    public function down(): void
    {
        Schema::table('cau_hinh_thanh_toan_van_chuyen', function (Blueprint $table) {
            foreach (['ma_quan_huyen_noi_thanh', 'ma_tinh_thanh_shop'] as $column) {
                if (Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('don_hang', function (Blueprint $table) {
            foreach (['ma_phuong_xa', 'ma_quan_huyen', 'ma_tinh_thanh'] as $column) {
                if (Schema::hasColumn('don_hang', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
