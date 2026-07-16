<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('van_don_van_chuyen', function (Blueprint $table) {
            $table->char('ma_van_chuyen', 10)->primary();
            $table->char('ma_dh', 10)->unique();
            $table->string('nha_van_chuyen', 30)->default('ghn');
            $table->string('moi_truong', 20)->default('sandbox');
            $table->string('ma_don_khach_hang', 100);
            $table->string('ma_van_don_ghn', 100)->nullable()->unique();
            $table->string('trang_thai_ghn_goc', 50)->nullable();
            $table->string('trang_thai_van_chuyen', 50)->nullable();
            $table->dateTime('ngay_cap_nhat_ghn')->nullable();
            $table->decimal('phi_van_chuyen', 12, 2)->nullable();
            $table->dateTime('thoi_gian_giao_du_kien')->nullable();
            $table->json('du_lieu_gui')->nullable();
            $table->json('du_lieu_phan_hoi')->nullable();
            $table->text('loi_dong_bo_cuoi')->nullable();
            $table->dateTime('ngay_dong_bo')->nullable();
            $table->string('trang_thai_tao', 30)->default('chua_tao');
            $table->unsignedInteger('so_lan_tao')->default(0);
            $table->dateTime('lan_tao_cuoi')->nullable();
            $table->dateTime('ngay_tao');
            $table->dateTime('ngay_cap_nhat');

            $table->foreign('ma_dh')->references('ma_dh')->on('don_hang')->cascadeOnDelete();
            $table->index(['nha_van_chuyen', 'trang_thai_van_chuyen']);
            $table->index('ngay_dong_bo');
        });

        Schema::create('su_kien_van_chuyen', function (Blueprint $table) {
            $table->char('ma_su_kien', 10)->primary();
            $table->char('ma_van_chuyen', 10);
            $table->char('ma_dh', 10);
            $table->string('nguon', 30);
            $table->string('trang_thai_ghn_goc', 50)->nullable();
            $table->string('trang_thai_van_chuyen', 50)->nullable();
            $table->dateTime('thoi_gian_su_kien');
            $table->char('ma_bam_payload', 64)->nullable();
            $table->json('du_lieu_payload')->nullable();
            $table->text('ghi_chu')->nullable();
            $table->boolean('da_bo_qua')->default(false);
            $table->dateTime('ngay_tao');

            $table->foreign('ma_van_chuyen')->references('ma_van_chuyen')->on('van_don_van_chuyen')->cascadeOnDelete();
            $table->foreign('ma_dh')->references('ma_dh')->on('don_hang')->cascadeOnDelete();
            $table->unique(['ma_van_chuyen', 'ma_bam_payload'], 'shipping_event_payload_unique');
            $table->index(['ma_van_chuyen', 'thoi_gian_su_kien']);
            $table->index(['ma_dh', 'thoi_gian_su_kien']);
        });

        Schema::table('lich_su_xu_ly_don_hang', function (Blueprint $table) {
            if (!Schema::hasColumn('lich_su_xu_ly_don_hang', 'nguon')) {
                $table->string('nguon', 30)->default('noi_bo')->after('ghi_chu');
            }
            if (!Schema::hasColumn('lich_su_xu_ly_don_hang', 'ma_van_chuyen')) {
                $table->char('ma_van_chuyen', 10)->nullable()->after('nguon');
                $table->index(['ma_dh', 'nguon', 'thoi_gian_xu_ly'], 'order_processing_source_index');
            }
        });
    }

    public function down(): void
    {
        Schema::table('lich_su_xu_ly_don_hang', function (Blueprint $table) {
            if (Schema::hasColumn('lich_su_xu_ly_don_hang', 'ma_van_chuyen')) {
                $table->dropIndex('order_processing_source_index');
                $table->dropColumn('ma_van_chuyen');
            }
            if (Schema::hasColumn('lich_su_xu_ly_don_hang', 'nguon')) {
                $table->dropColumn('nguon');
            }
        });

        Schema::dropIfExists('su_kien_van_chuyen');
        Schema::dropIfExists('van_don_van_chuyen');
    }
};
