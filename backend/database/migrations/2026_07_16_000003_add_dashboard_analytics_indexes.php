<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('don_hang', function (Blueprint $table) {
            $table->index(['trang_thai', 'ngay_dat'], 'orders_dashboard_status_date_index');
            $table->index(['ma_kh', 'ngay_dat'], 'orders_dashboard_customer_date_index');
        });

        Schema::table('yeu_cau_tra_hang', function (Blueprint $table) {
            $table->index(['ma_dh', 'trang_thai'], 'return_requests_dashboard_order_status_index');
        });
    }

    public function down(): void
    {
        Schema::table('yeu_cau_tra_hang', function (Blueprint $table) {
            $table->dropIndex('return_requests_dashboard_order_status_index');
        });

        Schema::table('don_hang', function (Blueprint $table) {
            $table->dropIndex('orders_dashboard_customer_date_index');
            $table->dropIndex('orders_dashboard_status_date_index');
        });
    }
};
