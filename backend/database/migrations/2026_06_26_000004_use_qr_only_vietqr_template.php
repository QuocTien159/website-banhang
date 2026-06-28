<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('don_hang') && Schema::hasColumn('don_hang', 'qr_code_url')) {
            DB::table('don_hang')
                ->where('qr_code_url', 'like', '%-compact2.png%')
                ->update(['qr_code_url' => DB::raw("REPLACE(qr_code_url, '-compact2.png', '-qr_only.png')")]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('don_hang') && Schema::hasColumn('don_hang', 'qr_code_url')) {
            DB::table('don_hang')
                ->where('qr_code_url', 'like', '%-qr_only.png%')
                ->update(['qr_code_url' => DB::raw("REPLACE(qr_code_url, '-qr_only.png', '-compact2.png')")]);
        }
    }
};
