<?php

namespace Database\Seeders;

use App\Models\MaKhuyenMai;
use App\Models\ThongBao;
use Illuminate\Database\Seeder;

class CommerceFeatureSeeder extends Seeder
{
    public function run(): void
    {
        MaKhuyenMai::firstOrCreate(['code' => 'SPORT20'], [
            'loai_giam' => 'percent', 'gia_tri' => 20, 'don_toi_thieu' => 500000,
            'giam_toi_da' => 300000, 'bat_dau' => now()->subMonth(), 'ket_thuc' => now()->addYear(),
            'gioi_han_su_dung' => 1000, 'da_su_dung' => 0, 'trang_thai' => true,
        ]);

        ThongBao::firstOrCreate(['tieu_de' => 'Chào mừng đến TienProSport'], [
            'noi_dung' => 'Hệ thống đã bổ sung wishlist, mã khuyến mãi và đánh giá sản phẩm có kiểm duyệt.',
            'loai' => 'update', 'trang_thai' => 'published',
            'ngay_xuat_ban' => now(), 'ngay_tao' => now(),
        ]);
    }
}
