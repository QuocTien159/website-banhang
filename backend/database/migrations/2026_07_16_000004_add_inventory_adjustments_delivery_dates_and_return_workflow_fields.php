<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('yeu_cau_dieu_chinh_kho', function (Blueprint $table) {
            $table->char('ma_ycdck', 10)->primary();
            $table->char('ma_bien_the', 10);
            $table->unsignedInteger('ton_kho_tai_luc_tao');
            $table->unsignedInteger('ton_kho_de_xuat');
            $table->text('ly_do');
            $table->string('trang_thai', 20)->default('pending');
            $table->char('ma_nguoi_tao', 10);
            $table->char('ma_nguoi_duyet', 10)->nullable();
            $table->text('ghi_chu_duyet')->nullable();
            $table->dateTime('ngay_tao');
            $table->dateTime('ngay_duyet')->nullable();

            $table->foreign('ma_bien_the')->references('ma_bt')->on('bien_the_san_pham')->onDelete('restrict');
            $table->foreign('ma_nguoi_tao')->references('ma_kh')->on('khach_hang')->onDelete('restrict');
            $table->foreign('ma_nguoi_duyet')->references('ma_kh')->on('khach_hang')->onDelete('set null');
            $table->index(['trang_thai', 'ngay_tao']);
            $table->index('ma_bien_the');
        });

        Schema::table('don_hang', function (Blueprint $table) {
            $table->dateTime('ngay_giao_thanh_cong')->nullable()->after('ngay_dat');
            $table->index('ngay_giao_thanh_cong');
        });

        Schema::table('yeu_cau_tra_hang', function (Blueprint $table) {
            $table->dateTime('ngay_nhan_hang')->nullable()->after('ngay_cap_nhat');
            $table->dateTime('ngay_hoan_tien')->nullable()->after('ngay_nhan_hang');
            $table->index('ngay_nhan_hang');
        });

        DB::table('don_hang')
            ->where('phuong_thuc_tt', 'banking')
            ->update(['phuong_thuc_tt' => 'bank_transfer_qr']);

        DB::table('don_hang')
            ->whereIn('trang_thai', ['completed', 'delivered'])
            ->whereNull('ngay_giao_thanh_cong')
            ->orderBy('ma_dh')
            ->select(['ma_dh', 'ngay_dat'])
            ->chunk(100, function ($orders) {
                foreach ($orders as $order) {
                    $fulfilledAt = DB::table('lich_su_xu_ly_don_hang')
                        ->where('ma_dh', $order->ma_dh)
                        ->whereIn('trang_thai_moi', ['completed', 'delivered'])
                        ->min('thoi_gian_xu_ly');

                    DB::table('don_hang')
                        ->where('ma_dh', $order->ma_dh)
                        ->update(['ngay_giao_thanh_cong' => $fulfilledAt ?: $order->ngay_dat]);
                }
            });
    }

    public function down(): void
    {
        Schema::table('yeu_cau_tra_hang', function (Blueprint $table) {
            $table->dropIndex(['ngay_nhan_hang']);
            $table->dropColumn(['ngay_nhan_hang', 'ngay_hoan_tien']);
        });

        Schema::table('don_hang', function (Blueprint $table) {
            $table->dropIndex(['ngay_giao_thanh_cong']);
            $table->dropColumn('ngay_giao_thanh_cong');
        });

        Schema::dropIfExists('yeu_cau_dieu_chinh_kho');
    }
};
