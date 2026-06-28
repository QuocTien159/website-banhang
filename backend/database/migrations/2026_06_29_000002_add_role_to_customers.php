<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khach_hang', function (Blueprint $table) {
            if (!Schema::hasColumn('khach_hang', 'role')) {
                $table->string('role', 20)->default('customer')->after('vai_tro');
            }
        });

        DB::table('khach_hang')
            ->where('vai_tro', true)
            ->update(['role' => 'admin']);

        DB::table('khach_hang')
            ->where('vai_tro', false)
            ->where(function ($query) {
                $query->whereNull('role')->orWhere('role', '');
            })
            ->update(['role' => 'customer']);
    }

    public function down(): void
    {
        Schema::table('khach_hang', function (Blueprint $table) {
            if (Schema::hasColumn('khach_hang', 'role')) {
                $table->dropColumn('role');
            }
        });
    }
};
