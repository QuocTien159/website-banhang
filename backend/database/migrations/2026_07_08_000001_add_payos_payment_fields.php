<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('don_hang', function (Blueprint $table) {
            if (!Schema::hasColumn('don_hang', 'payment_provider')) {
                $table->string('payment_provider', 30)->nullable()->after('phuong_thuc_tt');
            }
            if (!Schema::hasColumn('don_hang', 'payos_order_code')) {
                $table->unsignedBigInteger('payos_order_code')->nullable()->unique()->after('payment_provider');
            }
            if (!Schema::hasColumn('don_hang', 'payment_link_id')) {
                $table->string('payment_link_id', 100)->nullable()->after('payos_order_code');
            }
            if (!Schema::hasColumn('don_hang', 'payment_checkout_url')) {
                $table->text('payment_checkout_url')->nullable()->after('payment_link_id');
            }
            if (!Schema::hasColumn('don_hang', 'paid_at')) {
                $table->dateTime('paid_at')->nullable()->after('thanh_toan_xac_nhan_at');
            }
        });

        if (!Schema::hasTable('payment_logs')) {
            Schema::create('payment_logs', function (Blueprint $table) {
                $table->id();
                $table->char('ma_dh', 10)->nullable();
                $table->string('provider', 30);
                $table->string('event_type', 80);
                $table->json('raw_payload');
                $table->boolean('verified')->default(false);
                $table->timestamps();

                $table->foreign('ma_dh')->references('ma_dh')->on('don_hang')->nullOnDelete();
                $table->index(['provider', 'event_type']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_logs');

        Schema::table('don_hang', function (Blueprint $table) {
            foreach (['paid_at', 'payment_checkout_url', 'payment_link_id', 'payos_order_code', 'payment_provider'] as $column) {
                if (Schema::hasColumn('don_hang', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
