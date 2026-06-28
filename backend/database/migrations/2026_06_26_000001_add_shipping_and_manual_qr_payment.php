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
            if (!Schema::hasColumn('don_hang', 'tinh_thanh')) {
                $table->string('tinh_thanh', 100)->nullable()->after('dia_chi_giao');
            }
            if (!Schema::hasColumn('don_hang', 'quan_huyen')) {
                $table->string('quan_huyen', 100)->nullable()->after('tinh_thanh');
            }
            if (!Schema::hasColumn('don_hang', 'phuong_xa')) {
                $table->string('phuong_xa', 100)->nullable()->after('quan_huyen');
            }
            if (!Schema::hasColumn('don_hang', 'dia_chi_chi_tiet')) {
                $table->string('dia_chi_chi_tiet', 255)->nullable()->after('phuong_xa');
            }
            if (!Schema::hasColumn('don_hang', 'loai_khu_vuc_giao')) {
                $table->string('loai_khu_vuc_giao', 30)->nullable()->after('phi_van_chuyen');
            }
            if (!Schema::hasColumn('don_hang', 'trang_thai_thanh_toan')) {
                $table->string('trang_thai_thanh_toan', 30)->default('unpaid')->after('phuong_thuc_tt');
            }
            if (!Schema::hasColumn('don_hang', 'noi_dung_chuyen_khoan')) {
                $table->string('noi_dung_chuyen_khoan', 255)->nullable()->after('trang_thai_thanh_toan');
            }
            if (!Schema::hasColumn('don_hang', 'qr_code_url')) {
                $table->text('qr_code_url')->nullable()->after('noi_dung_chuyen_khoan');
            }
            if (!Schema::hasColumn('don_hang', 'khach_bao_da_chuyen_at')) {
                $table->dateTime('khach_bao_da_chuyen_at')->nullable()->after('qr_code_url');
            }
            if (!Schema::hasColumn('don_hang', 'thanh_toan_xac_nhan_at')) {
                $table->dateTime('thanh_toan_xac_nhan_at')->nullable()->after('khach_bao_da_chuyen_at');
            }
            if (!Schema::hasColumn('don_hang', 'thanh_toan_xac_nhan_boi')) {
                $table->char('thanh_toan_xac_nhan_boi', 10)->nullable()->after('thanh_toan_xac_nhan_at');
            }
        });

        Schema::create('cau_hinh_thanh_toan_van_chuyen', function (Blueprint $table) {
            $table->id();
            $table->decimal('phi_noi_thanh', 12, 2)->default(20000);
            $table->decimal('phi_ngoai_thanh', 12, 2)->default(30000);
            $table->decimal('phi_tinh_khac', 12, 2)->default(45000);
            $table->boolean('mien_phi_ship_bat')->default(true);
            $table->decimal('nguong_mien_phi_ship', 12, 2)->default(500000);
            $table->string('tinh_thanh_shop', 100)->default('TP Hồ Chí Minh');
            $table->json('quan_huyen_noi_thanh')->nullable();
            $table->string('ma_ngan_hang', 30)->default('VCB');
            $table->string('ten_ngan_hang', 100)->default('Vietcombank');
            $table->string('so_tai_khoan', 50)->default('1234567890');
            $table->string('ten_chu_tai_khoan', 150)->default('TIENPROSPORT CO., LTD');
            $table->string('mau_noi_dung_chuyen_khoan', 150)->default('TienProSport {{order_code}}');
            $table->timestamps();
        });

        DB::table('cau_hinh_thanh_toan_van_chuyen')->insert([
            'phi_noi_thanh' => 20000,
            'phi_ngoai_thanh' => 30000,
            'phi_tinh_khac' => 45000,
            'mien_phi_ship_bat' => true,
            'nguong_mien_phi_ship' => 500000,
            'tinh_thanh_shop' => 'TP Hồ Chí Minh',
            'quan_huyen_noi_thanh' => json_encode([
                'Quận 1', 'Quận 3', 'Quận 4', 'Quận 5', 'Quận 10', 'Quận 11',
                'Bình Thạnh', 'Phú Nhuận', 'Tân Bình', 'Gò Vấp',
            ], JSON_UNESCAPED_UNICODE),
            'ma_ngan_hang' => 'VCB',
            'ten_ngan_hang' => 'Vietcombank',
            'so_tai_khoan' => '1234567890',
            'ten_chu_tai_khoan' => 'TIENPROSPORT CO., LTD',
            'mau_noi_dung_chuyen_khoan' => 'TienProSport {{order_code}}',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('cau_hinh_thanh_toan_van_chuyen');

        Schema::table('don_hang', function (Blueprint $table) {
            foreach ([
                'thanh_toan_xac_nhan_boi', 'thanh_toan_xac_nhan_at', 'khach_bao_da_chuyen_at',
                'qr_code_url', 'noi_dung_chuyen_khoan', 'trang_thai_thanh_toan',
                'loai_khu_vuc_giao', 'dia_chi_chi_tiet', 'phuong_xa', 'quan_huyen', 'tinh_thanh',
            ] as $column) {
                if (Schema::hasColumn('don_hang', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
