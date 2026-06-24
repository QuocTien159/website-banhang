<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('thuoc_tinh', function (Blueprint $table) {
            $table->unique('ten_tt', 'thuoc_tinh_ten_unique');
        });

        Schema::table('gia_tri_thuoc_tinh', function (Blueprint $table) {
            $table->unique(['ma_tt', 'gia_tri'], 'gia_tri_thuoc_tinh_unique');
        });

        Schema::table('bien_the_san_pham', function (Blueprint $table) {
            $table->string('variant_signature', 64)->nullable()->after('sku');
        });

        $variants = DB::table('bien_the_san_pham')->select('ma_bt', 'ma_sp')->get();
        foreach ($variants as $variant) {
            $parts = DB::table('lien_ket_bien_the_gia_tri as lk')
                ->join('gia_tri_thuoc_tinh as gt', 'lk.ma_gt', '=', 'gt.ma_gt')
                ->join('thuoc_tinh as tt', 'gt.ma_tt', '=', 'tt.ma_tt')
                ->where('lk.ma_bt', $variant->ma_bt)
                ->orderBy('tt.ten_tt')
                ->orderBy('gt.gia_tri')
                ->get(['tt.ten_tt', 'gt.gia_tri'])
                ->map(fn ($row) => mb_strtolower(trim($row->ten_tt)).'='.mb_strtolower(trim($row->gia_tri)))
                ->all();

            DB::table('bien_the_san_pham')
                ->where('ma_bt', $variant->ma_bt)
                ->update(['variant_signature' => hash('sha256', implode('|', $parts))]);
        }

        Schema::table('bien_the_san_pham', function (Blueprint $table) {
            $table->unique(['ma_sp', 'variant_signature'], 'bien_the_product_signature_unique');
        });
    }

    public function down(): void
    {
        Schema::table('bien_the_san_pham', function (Blueprint $table) {
            $table->dropUnique('bien_the_product_signature_unique');
            $table->dropColumn('variant_signature');
        });

        Schema::table('gia_tri_thuoc_tinh', function (Blueprint $table) {
            $table->dropUnique('gia_tri_thuoc_tinh_unique');
        });

        Schema::table('thuoc_tinh', function (Blueprint $table) {
            $table->dropUnique('thuoc_tinh_ten_unique');
        });
    }
};
