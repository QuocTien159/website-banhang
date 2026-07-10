<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hinh_anh_san_pham', function (Blueprint $table) {
            $table->string('provider', 30)->nullable()->after('url');
            $table->string('cloudinary_public_id', 255)->nullable()->after('provider');
            $table->unsignedInteger('chieu_rong')->nullable()->after('cloudinary_public_id');
            $table->unsignedInteger('chieu_cao')->nullable()->after('chieu_rong');
            $table->unsignedInteger('thu_tu')->default(0)->after('anh_chinh');
            $table->index('cloudinary_public_id');
        });

        Schema::table('hinh_anh_thong_bao', function (Blueprint $table) {
            $table->string('provider', 30)->nullable()->after('url');
            $table->string('cloudinary_public_id', 255)->nullable()->after('provider');
            $table->unsignedInteger('chieu_rong')->nullable()->after('cloudinary_public_id');
            $table->unsignedInteger('chieu_cao')->nullable()->after('chieu_rong');
            $table->index('cloudinary_public_id');
        });
    }

    public function down(): void
    {
        Schema::table('hinh_anh_san_pham', function (Blueprint $table) {
            $table->dropIndex(['cloudinary_public_id']);
            $table->dropColumn(['provider', 'cloudinary_public_id', 'chieu_rong', 'chieu_cao', 'thu_tu']);
        });
        Schema::table('hinh_anh_thong_bao', function (Blueprint $table) {
            $table->dropIndex(['cloudinary_public_id']);
            $table->dropColumn(['provider', 'cloudinary_public_id', 'chieu_rong', 'chieu_cao']);
        });
    }
};
