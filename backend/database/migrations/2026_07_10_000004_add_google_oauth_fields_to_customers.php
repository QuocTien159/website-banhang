<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('khach_hang', function (Blueprint $table) {
            $table->string('google_id', 255)->nullable()->unique()->after('email');
            $table->string('google_avatar', 500)->nullable()->after('google_id');
            $table->dateTime('google_linked_at')->nullable()->after('google_avatar');
        });
    }

    public function down(): void
    {
        Schema::table('khach_hang', function (Blueprint $table) {
            $table->dropUnique(['google_id']);
            $table->dropColumn(['google_id', 'google_avatar', 'google_linked_at']);
        });
    }
};
