<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Models\KhachHang;
use App\Models\DanhMuc;
use App\Models\SanPham;
use App\Models\BienTheSanPham;
use App\Models\HinhAnhSanPham;
use App\Models\GioHang;
use App\Models\ThuocTinh;
use App\Models\GiaTriThuocTinh;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Admins ───────────────────────────────────────────────────
        $admin = KhachHang::create([
            'ten_kh'     => 'Quản trị viên',
            'email'      => 'admin@tienprosport.vn',
            'mat_khau'   => Hash::make('admin123'),
            'dien_thoai' => '0900000000',
            'vai_tro'    => true,
            'trang_thai' => true,
            'ngay_tao'   => now()->subYear(),
        ]);
        GioHang::create(['ma_kh' => $admin->ma_kh]);

        // ─── Customers ────────────────────────────────────────────────
        $customers = [
            ['Nguyễn Văn An',    'an.nguyen@gmail.com',   '0912345678'],
            ['Trần Thị Bích',    'bich.tran@yahoo.com',   '0987654321'],
            ['Lê Minh Cường',    'cuong.le@hotmail.com',  '0905111222'],
            ['Phạm Thị Dung',    'dung.pham@gmail.com',   '0932888999'],
            ['Hoàng Văn Em',     'em.hoang@gmail.com',    '0976444555'],
            ['Vũ Thị Phượng',   'phuong.vu@gmail.com',   '0908777666'],
        ];

        $customerModels = [];
        foreach ($customers as $c) {
            $kh = KhachHang::create([
                'ten_kh'     => $c[0],
                'email'      => $c[1],
                'mat_khau'   => Hash::make('user123'),
                'dien_thoai' => $c[2],
                'vai_tro'    => false,
                'trang_thai' => true,
                'ngay_tao'   => now()->subMonths(rand(1, 12)),
            ]);
            GioHang::create(['ma_kh' => $kh->ma_kh]);
            $customerModels[] = $kh;
        }

        // ─── Demo user ────────────────────────────────────────────────
        $demoUser = KhachHang::create([
            'ten_kh'     => 'Nguyễn Demo',
            'email'      => 'user@example.com',
            'mat_khau'   => Hash::make('user123'),
            'dien_thoai' => '0909123456',
            'vai_tro'    => false,
            'trang_thai' => true,
            'ngay_tao'   => now()->subMonths(6),
        ]);
        GioHang::create(['ma_kh' => $demoUser->ma_kh]);

        // ─── Categories ───────────────────────────────────────────────
        $categoryNames = [
            'Gym & Fitness', 'Chạy bộ', 'Bóng đá', 'Bóng rổ',
            'Tennis', 'Bơi lội', 'Đạp xe', 'Phụ kiện',
        ];
        $categories = [];
        foreach ($categoryNames as $name) {
            $categories[$name] = DanhMuc::create(['ten_dm' => $name]);
        }

        // ─── Attributes ───────────────────────────────────────────────
        $sizeAttr  = ThuocTinh::create(['ten_tt' => 'Size']);
        $colorAttr = ThuocTinh::create(['ten_tt' => 'Màu sắc']);
        $weightAttr = ThuocTinh::create(['ten_tt' => 'Trọng lượng']);

        // Size values
        $sizes = ['S', 'M', 'L', 'XL', '38', '39', '40', '41', '42', '43', '44', '45'];
        $sizeValues = [];
        foreach ($sizes as $s) {
            $sizeValues[$s] = GiaTriThuocTinh::create(['ma_tt' => $sizeAttr->ma_tt, 'gia_tri' => $s]);
        }

        // Color values
        $colors = ['Đen', 'Trắng', 'Xanh dương', 'Đỏ', 'Xám'];
        $colorValues = [];
        foreach ($colors as $c) {
            $colorValues[$c] = GiaTriThuocTinh::create(['ma_tt' => $colorAttr->ma_tt, 'gia_tri' => $c]);
        }

        // ─── Products ─────────────────────────────────────────────────
        $U = fn($id) => "https://images.unsplash.com/{$id}?w=600&auto=format&q=80";

        $productsData = [
            [
                'category' => 'Gym & Fitness',
                'ten_sp' => 'Tạ Đơn Cao Su 5kg',
                'mo_ta' => 'Tạ đơn bọc cao su chất lượng cao, bảo vệ sàn tập, thiết kế ergonomic giúp cầm nắm chắc chắn.',
                'gia_co_ban' => 420000,
                'image' => $U('photo-1562771242-a02d9090c90c'),
                'variants' => [
                    ['sku' => 'TA-5KG-DEN', 'gia_ban' => 350000, 'stock' => 25, 'color' => 'Đen', 'weight' => '5kg'],
                ],
            ],
            [
                'category' => 'Gym & Fitness',
                'ten_sp' => 'Bộ Tạ Đĩa 20kg',
                'mo_ta' => 'Bộ tạ đĩa 20kg gồm đòn tạ dài 120cm và các đĩa tạ nhiều trọng lượng.',
                'gia_co_ban' => 850000,
                'image' => $U('photo-1603077492340-e6e62b2a688b'),
                'variants' => [
                    ['sku' => 'TA-DIA-20KG', 'gia_ban' => 850000, 'stock' => 18, 'color' => 'Xám'],
                ],
            ],
            [
                'category' => 'Gym & Fitness',
                'ten_sp' => 'Thảm Tập Yoga 6mm',
                'mo_ta' => 'Thảm yoga cao su TPE 6mm chống trơn trượt, không mùi, siêu nhẹ.',
                'gia_co_ban' => 350000,
                'image' => $U('photo-1637157216470-d92cd2edb2e8'),
                'variants' => [
                    ['sku' => 'YOGA-MAT-TIM',   'gia_ban' => 285000, 'stock' => 30, 'color' => 'Xanh dương'],
                    ['sku' => 'YOGA-MAT-DEN',   'gia_ban' => 285000, 'stock' => 32, 'color' => 'Đen'],
                ],
            ],
            [
                'category' => 'Chạy bộ',
                'ten_sp' => 'Giày Chạy Bộ Ultra Pro X',
                'mo_ta' => 'Giày chạy bộ công nghệ đệm khí Ultra Pro, đế ngoài cao su chống mài mòn.',
                'gia_co_ban' => 1500000,
                'image' => $U('photo-1709258228137-19a8c193be39'),
                'variants' => [
                    ['sku' => 'SHOE-ULTRA-40-TRANG', 'gia_ban' => 1250000, 'stock' => 8, 'size' => '40', 'color' => 'Trắng'],
                    ['sku' => 'SHOE-ULTRA-41-TRANG', 'gia_ban' => 1250000, 'stock' => 10,'size' => '41', 'color' => 'Trắng'],
                    ['sku' => 'SHOE-ULTRA-42-DEN',   'gia_ban' => 1250000, 'stock' => 6, 'size' => '42', 'color' => 'Đen'],
                    ['sku' => 'SHOE-ULTRA-43-DEN',   'gia_ban' => 1250000, 'stock' => 4, 'size' => '43', 'color' => 'Đen'],
                ],
            ],
            [
                'category' => 'Chạy bộ',
                'ten_sp' => 'Giày Thể Thao Air Boost',
                'mo_ta' => 'Giày thể thao đa năng Air Boost với công nghệ đệm khí nén.',
                'gia_co_ban' => 980000,
                'image' => $U('photo-1637437757614-6491c8e915b5'),
                'variants' => [
                    ['sku' => 'SHOE-AIR-40-XANH', 'gia_ban' => 980000, 'stock' => 12, 'size' => '40', 'color' => 'Xanh dương'],
                    ['sku' => 'SHOE-AIR-41-XANH', 'gia_ban' => 980000, 'stock' => 15, 'size' => '41', 'color' => 'Xanh dương'],
                    ['sku' => 'SHOE-AIR-42-DO',   'gia_ban' => 980000, 'stock' => 10, 'size' => '42', 'color' => 'Đỏ'],
                ],
            ],
            [
                'category' => 'Bóng đá',
                'ten_sp' => 'Bóng Đá PVC Size 5',
                'mo_ta' => 'Bóng đá PVC size 5 tiêu chuẩn FIFA, chất liệu PVC bền bỉ.',
                'gia_co_ban' => 380000,
                'image' => $U('photo-1602472097151-72eeec7a3185'),
                'variants' => [
                    ['sku' => 'BONG-DA-SIZE5-TRANG', 'gia_ban' => 320000, 'stock' => 78, 'color' => 'Trắng'],
                ],
            ],
            [
                'category' => 'Bóng rổ',
                'ten_sp' => 'Bóng Rổ Cao Su Size 7',
                'mo_ta' => 'Bóng rổ cao su size 7 tiêu chuẩn, phù hợp sân trong và ngoài trời.',
                'gia_co_ban' => 380000,
                'image' => $U('photo-1595795279832-13f0df36fbb9'),
                'variants' => [
                    ['sku' => 'BONG-RO-S7-CAM', 'gia_ban' => 380000, 'stock' => 55, 'color' => 'Đỏ'],
                ],
            ],
            [
                'category' => 'Tennis',
                'ten_sp' => 'Vợt Tennis Carbon T100',
                'mo_ta' => 'Vợt tennis khung carbon nguyên chất T100, trọng lượng 300g.',
                'gia_co_ban' => 2200000,
                'image' => $U('photo-1591311630200-ffa9120a540f'),
                'variants' => [
                    ['sku' => 'VOTT-T100-DEN', 'gia_ban' => 1850000, 'stock' => 20, 'color' => 'Đen'],
                ],
            ],
            [
                'category' => 'Gym & Fitness',
                'ten_sp' => 'Găng Tay Boxing Leather',
                'mo_ta' => 'Găng tay boxing da thật cao cấp, lớp đệm dày 2cm.',
                'gia_co_ban' => 650000,
                'image' => $U('photo-1518611012118-696072aa579a'),
                'variants' => [
                    ['sku' => 'GANG-BOX-S-DO',  'gia_ban' => 650000, 'stock' => 12, 'size' => 'S', 'color' => 'Đỏ'],
                    ['sku' => 'GANG-BOX-M-DO',  'gia_ban' => 650000, 'stock' => 15, 'size' => 'M', 'color' => 'Đỏ'],
                    ['sku' => 'GANG-BOX-L-DEN', 'gia_ban' => 650000, 'stock' => 11, 'size' => 'L', 'color' => 'Đen'],
                ],
            ],
            [
                'category' => 'Bơi lội',
                'ten_sp' => 'Kính Bơi Anti-UV Pro',
                'mo_ta' => 'Kính bơi chống tia UV, tráng lớp anti-fog, silicon mềm không gây dị ứng.',
                'gia_co_ban' => 275000,
                'image' => $U('photo-1584735935682-2f2b69dff9d2'),
                'variants' => [
                    ['sku' => 'KINH-BOI-DEN', 'gia_ban' => 275000, 'stock' => 67, 'color' => 'Đen'],
                    ['sku' => 'KINH-BOI-XANH', 'gia_ban' => 275000, 'stock' => 40, 'color' => 'Xanh dương'],
                ],
            ],
            [
                'category' => 'Phụ kiện',
                'ten_sp' => 'Bình Nước Thể Thao 750ml',
                'mo_ta' => 'Bình nước thể thao inox 304 dung tích 750ml, giữ lạnh 24h.',
                'gia_co_ban' => 155000,
                'image' => $U('photo-1562771242-a02d9090c90c'),
                'variants' => [
                    ['sku' => 'BINH-750-XANH', 'gia_ban' => 155000, 'stock' => 85, 'color' => 'Xanh dương'],
                    ['sku' => 'BINH-750-DEN',  'gia_ban' => 155000, 'stock' => 70, 'color' => 'Đen'],
                ],
            ],
            [
                'category' => 'Phụ kiện',
                'ten_sp' => 'Túi Thể Thao Đa Năng 30L',
                'mo_ta' => 'Túi thể thao dung tích 30L, polyester 900D chống nước, ngăn giày riêng biệt.',
                'gia_co_ban' => 420000,
                'image' => $U('photo-1595909315417-2edd382a56dc'),
                'variants' => [
                    ['sku' => 'TUI-30L-DEN',  'gia_ban' => 420000, 'stock' => 44, 'color' => 'Đen'],
                    ['sku' => 'TUI-30L-XANH', 'gia_ban' => 420000, 'stock' => 30, 'color' => 'Xanh dương'],
                ],
            ],
            [
                'category' => 'Gym & Fitness',
                'ten_sp' => 'Dây Nhảy Tốc Độ Pro',
                'mo_ta' => 'Dây nhảy tốc độ cao cấp, vòng bi thép không gỉ 3 ổ.',
                'gia_co_ban' => 200000,
                'image' => $U('photo-1584735935682-2f2b69dff9d2'),
                'variants' => [
                    ['sku' => 'DAY-NHAY-PRO', 'gia_ban' => 165000, 'stock' => 70, 'color' => 'Đen'],
                ],
            ],
            [
                'category' => 'Gym & Fitness',
                'ten_sp' => 'Bộ Dây Kháng Lực 5 Cấp',
                'mo_ta' => 'Bộ 5 dây kháng lực cao su latex với 5 mức độ từ nhẹ đến nặng.',
                'gia_co_ban' => 120000,
                'image' => $U('photo-1591291621164-2c6367723315'),
                'variants' => [
                    ['sku' => 'DAY-KHANG-SET5', 'gia_ban' => 120000, 'stock' => 95, 'color' => 'Đỏ'],
                ],
            ],
            [
                'category' => 'Đạp xe',
                'ten_sp' => 'Mũ Bảo Hiểm Xe Đạp',
                'mo_ta' => 'Mũ bảo hiểm xe đạp tiêu chuẩn CE, vỏ ABS cứng, lớp xốp EPS giảm xung.',
                'gia_co_ban' => 560000,
                'image' => $U('photo-1646656130630-07af3a262a9b'),
                'variants' => [
                    ['sku' => 'MU-XE-DAP-S-TRANG', 'gia_ban' => 485000, 'stock' => 15, 'size' => 'S', 'color' => 'Trắng'],
                    ['sku' => 'MU-XE-DAP-M-TRANG', 'gia_ban' => 485000, 'stock' => 14, 'size' => 'M', 'color' => 'Trắng'],
                    ['sku' => 'MU-XE-DAP-L-DEN',   'gia_ban' => 485000, 'stock' => 0,  'size' => 'L', 'color' => 'Đen'],
                ],
            ],
            [
                'category' => 'Bơi lội',
                'ten_sp' => 'Mũ Bơi Silicone Cao Cấp',
                'mo_ta' => 'Mũ bơi silicone 100% cao cấp, chống thấm nước tuyệt đối.',
                'gia_co_ban' => 95000,
                'image' => $U('photo-1589955898954-9c8d4bb86823'),
                'variants' => [
                    ['sku' => 'MU-BOI-M-XANH',  'gia_ban' => 95000, 'stock' => 120, 'size' => 'M', 'color' => 'Xanh dương'],
                    ['sku' => 'MU-BOI-L-TRANG',  'gia_ban' => 95000, 'stock' => 80,  'size' => 'L', 'color' => 'Trắng'],
                    ['sku' => 'MU-BOI-XL-DEN',   'gia_ban' => 95000, 'stock' => 60,  'size' => 'XL','color' => 'Đen'],
                ],
            ],
        ];

        foreach ($productsData as $pData) {
            $sp = SanPham::create([
                'ma_dm'      => $categories[$pData['category']]->ma_dm,
                'ten_sp'     => $pData['ten_sp'],
                'mo_ta'      => $pData['mo_ta'],
                'gia_co_ban' => $pData['gia_co_ban'],
                'trang_thai' => 'active',
                'ngay_tao'   => now()->subDays(rand(1, 180)),
            ]);

            HinhAnhSanPham::create([
                'ma_sp'    => $sp->ma_sp,
                'url'      => $pData['image'],
                'anh_chinh'=> true,
            ]);

            foreach ($pData['variants'] as $vData) {
                $bt = BienTheSanPham::create([
                    'ma_sp'        => $sp->ma_sp,
                    'sku'          => $vData['sku'],
                    'gia_ban'      => $vData['gia_ban'],
                    'so_luong_ton' => $vData['stock'],
                    'trang_thai'   => true,
                ]);

                // Link attributes
                $gtIds = [];
                if (isset($vData['size']) && isset($sizeValues[$vData['size']])) {
                    $gtIds[] = $sizeValues[$vData['size']]->ma_gt;
                }
                if (isset($vData['color']) && isset($colorValues[$vData['color']])) {
                    $gtIds[] = $colorValues[$vData['color']]->ma_gt;
                }
                if (!empty($gtIds)) {
                    DB::table('lien_ket_bien_the_gia_tri')->insert(
                        array_map(fn($id) => ['ma_bt' => $bt->ma_bt, 'ma_gt' => $id], $gtIds)
                    );
                }
            }
        }

        $this->command->info('✅ Database seeded successfully!');
        $this->command->info('👤 Admin: admin@tienprosport.vn / admin123');
        $this->command->info('👤 User:  user@example.com / user123');
    }
}
