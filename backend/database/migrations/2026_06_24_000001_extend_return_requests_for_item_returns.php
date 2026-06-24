<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('yeu_cau_tra_hang', function (Blueprint $table) {
            $table->char('ma_kh', 10)->nullable()->after('ma_dh');
            $table->text('mo_ta')->nullable()->after('ly_do');
            $table->text('ghi_chu_admin')->nullable()->after('trang_thai');
            $table->text('ly_do_tu_choi')->nullable()->after('ghi_chu_admin');
            $table->string('trang_thai_hoan_tien', 30)->default('not_refunded')->after('ly_do_tu_choi');
            $table->boolean('da_nhap_kho')->default(false)->after('trang_thai_hoan_tien');
            $table->dateTime('ngay_cap_nhat')->nullable()->after('ngay_yeu_cau');

            $table->foreign('ma_kh')->references('ma_kh')->on('khach_hang')->onDelete('restrict');
            $table->index(['trang_thai', 'trang_thai_hoan_tien']);
        });

        Schema::table('chi_tiet_tra_hang', function (Blueprint $table) {
            $table->char('ma_sp', 10)->nullable()->after('ma_bien_the');
            $table->string('ly_do', 255)->nullable()->after('so_luong');
            $table->text('mo_ta')->nullable()->after('ly_do');

            $table->foreign('ma_sp')->references('ma_sp')->on('san_pham')->onDelete('restrict');
        });

        Schema::create('hinh_anh_tra_hang', function (Blueprint $table) {
            $table->char('ma_anh_th', 10)->primary();
            $table->char('ma_yeu_cau', 10);
            $table->char('ma_bien_the', 10)->nullable();
            $table->string('url_anh', 500);
            $table->dateTime('ngay_tao');

            $table->foreign('ma_yeu_cau')->references('ma_yeu_cau')->on('yeu_cau_tra_hang')->onDelete('cascade');
            $table->foreign('ma_bien_the')->references('ma_bt')->on('bien_the_san_pham')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('hinh_anh_tra_hang');

        Schema::table('chi_tiet_tra_hang', function (Blueprint $table) {
            $table->dropForeign(['ma_sp']);
            $table->dropColumn(['ma_sp', 'ly_do', 'mo_ta']);
        });

        Schema::table('yeu_cau_tra_hang', function (Blueprint $table) {
            $table->dropForeign(['ma_kh']);
            $table->dropIndex(['trang_thai', 'trang_thai_hoan_tien']);
            $table->dropColumn([
                'ma_kh',
                'mo_ta',
                'ghi_chu_admin',
                'ly_do_tu_choi',
                'trang_thai_hoan_tien',
                'da_nhap_kho',
                'ngay_cap_nhat',
            ]);
        });
    }
};
