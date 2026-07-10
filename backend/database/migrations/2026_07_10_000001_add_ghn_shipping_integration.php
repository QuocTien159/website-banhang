<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cau_hinh_thanh_toan_van_chuyen', function (Blueprint $table) {
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'shipping_provider')) {
                $table->string('shipping_provider', 30)->default('manual')->after('id');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'ghn_enabled')) {
                $table->boolean('ghn_enabled')->default(false)->after('shipping_provider');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'ghn_environment')) {
                $table->string('ghn_environment', 20)->default('sandbox')->after('ghn_enabled');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'ghn_shop_id')) {
                $table->string('ghn_shop_id', 50)->nullable()->after('ghn_environment');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'pickup_name')) {
                $table->string('pickup_name', 150)->nullable()->after('ghn_shop_id');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'pickup_phone')) {
                $table->string('pickup_phone', 20)->nullable()->after('pickup_name');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'pickup_province_id')) {
                $table->integer('pickup_province_id')->nullable()->after('pickup_phone');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'pickup_province_name')) {
                $table->string('pickup_province_name', 100)->nullable()->after('pickup_province_id');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'pickup_district_id')) {
                $table->integer('pickup_district_id')->nullable()->after('pickup_province_name');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'pickup_district_name')) {
                $table->string('pickup_district_name', 100)->nullable()->after('pickup_district_id');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'pickup_ward_code')) {
                $table->string('pickup_ward_code', 20)->nullable()->after('pickup_district_name');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'pickup_ward_name')) {
                $table->string('pickup_ward_name', 100)->nullable()->after('pickup_ward_code');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'pickup_address')) {
                $table->string('pickup_address', 255)->nullable()->after('pickup_ward_name');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'default_weight_gram')) {
                $table->integer('default_weight_gram')->default(500)->after('pickup_address');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'default_length_cm')) {
                $table->integer('default_length_cm')->default(25)->after('default_weight_gram');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'default_width_cm')) {
                $table->integer('default_width_cm')->default(20)->after('default_length_cm');
            }
            if (!Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', 'default_height_cm')) {
                $table->integer('default_height_cm')->default(10)->after('default_width_cm');
            }
        });

        Schema::table('don_hang', function (Blueprint $table) {
            if (!Schema::hasColumn('don_hang', 'shipping_provider')) {
                $table->string('shipping_provider', 30)->nullable()->after('shipping_zone');
            }
            if (!Schema::hasColumn('don_hang', 'shipping_service_id')) {
                $table->string('shipping_service_id', 50)->nullable()->after('shipping_provider');
            }
            if (!Schema::hasColumn('don_hang', 'shipping_service_type_id')) {
                $table->string('shipping_service_type_id', 50)->nullable()->after('shipping_service_id');
            }
            if (!Schema::hasColumn('don_hang', 'shipping_service_name')) {
                $table->string('shipping_service_name', 100)->nullable()->after('shipping_service_type_id');
            }
            if (!Schema::hasColumn('don_hang', 'shipping_order_code')) {
                $table->string('shipping_order_code', 100)->nullable()->after('shipping_service_name');
            }
            if (!Schema::hasColumn('don_hang', 'shipping_status')) {
                $table->string('shipping_status', 50)->nullable()->after('shipping_order_code');
            }
            if (!Schema::hasColumn('don_hang', 'shipping_fee_breakdown')) {
                $table->json('shipping_fee_breakdown')->nullable()->after('shipping_status');
            }
            if (!Schema::hasColumn('don_hang', 'shipping_expected_delivery_at')) {
                $table->dateTime('shipping_expected_delivery_at')->nullable()->after('shipping_fee_breakdown');
            }
        });
    }

    public function down(): void
    {
        Schema::table('don_hang', function (Blueprint $table) {
            foreach ([
                'shipping_expected_delivery_at', 'shipping_fee_breakdown', 'shipping_status',
                'shipping_order_code', 'shipping_service_name', 'shipping_service_type_id',
                'shipping_service_id', 'shipping_provider',
            ] as $column) {
                if (Schema::hasColumn('don_hang', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('cau_hinh_thanh_toan_van_chuyen', function (Blueprint $table) {
            foreach ([
                'default_height_cm', 'default_width_cm', 'default_length_cm', 'default_weight_gram',
                'pickup_address', 'pickup_ward_name', 'pickup_ward_code', 'pickup_district_name',
                'pickup_district_id', 'pickup_province_name', 'pickup_province_id', 'pickup_phone',
                'pickup_name', 'ghn_shop_id', 'ghn_environment', 'ghn_enabled', 'shipping_provider',
            ] as $column) {
                if (Schema::hasColumn('cau_hinh_thanh_toan_van_chuyen', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
