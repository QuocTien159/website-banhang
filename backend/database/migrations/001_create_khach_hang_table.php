<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('khach_hang', function (Blueprint $table) {
            $table->char('ma_kh', 10)->primary();
            $table->char('ten_kh', 50);
            $table->char('email', 100)->unique();
            $table->string('mat_khau', 255);
            $table->char('dien_thoai', 11)->unique()->nullable();
            $table->boolean('vai_tro')->default(false)->comment('false=KhachHang, true=Admin');
            $table->boolean('trang_thai')->default(true)->comment('true=HoatDong, false=BiKhoa');
            $table->dateTime('ngay_tao');
            $table->rememberToken();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('khach_hang');
    }
};
