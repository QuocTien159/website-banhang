<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('khach_hang') && Schema::hasColumn('khach_hang', 'email')) {
            DB::table('khach_hang')
                ->where('email', 'admin@sportzone.vn')
                ->update(['email' => 'admin@tienprosport.vn']);
        }

        if (Schema::hasTable('thong_bao')) {
            foreach (['tieu_de', 'noi_dung'] as $column) {
                if (Schema::hasColumn('thong_bao', $column)) {
                    DB::table('thong_bao')
                        ->where($column, 'like', '%SportZone%')
                        ->update([$column => DB::raw("REPLACE({$column}, 'SportZone', 'TienProSport')")]);
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('khach_hang') && Schema::hasColumn('khach_hang', 'email')) {
            DB::table('khach_hang')
                ->where('email', 'admin@tienprosport.vn')
                ->update(['email' => 'admin@sportzone.vn']);
        }

        if (Schema::hasTable('thong_bao')) {
            foreach (['tieu_de', 'noi_dung'] as $column) {
                if (Schema::hasColumn('thong_bao', $column)) {
                    DB::table('thong_bao')
                        ->where($column, 'like', '%TienProSport%')
                        ->update([$column => DB::raw("REPLACE({$column}, 'TienProSport', 'SportZone')")]);
                }
            }
        }
    }
};
