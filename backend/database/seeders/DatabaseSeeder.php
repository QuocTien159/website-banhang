<?php

namespace Database\Seeders;

use App\Models\BienTheSanPham;
use App\Models\DanhMuc;
use App\Models\GiaTriThuocTinh;
use App\Models\GioHang;
use App\Models\HinhAnhSanPham;
use App\Models\KhachHang;
use App\Models\SanPham;
use App\Models\ThuocTinh;
use App\Models\MaKhuyenMai;
use App\Models\ThongBao;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedUsers();

        $categories = collect(['Áo', 'Quần', 'Giày', 'Phụ kiện'])
            ->mapWithKeys(fn (string $name) => [$name => DanhMuc::create(['ten_dm' => $name])]);

        $attributeValues = [];
        foreach ($this->attributeDefinitions() as $attributeName => $values) {
            $attribute = ThuocTinh::create(['ten_tt' => $attributeName]);
            foreach ($values as $value) {
                $attributeValues[$attributeName][$value] = GiaTriThuocTinh::create([
                    'ma_tt' => $attribute->ma_tt,
                    'gia_tri' => $value,
                ]);
            }
        }

        foreach ($this->products() as $index => $data) {
            $product = SanPham::create([
                'ma_dm' => $categories[$data['category']]->ma_dm,
                'ten_sp' => $data['name'],
                'mo_ta' => $data['description'],
                'gia_co_ban' => $data['original_price'],
                'trang_thai' => 'active',
                'ngay_tao' => now()->subDays($index + 1),
            ]);

            foreach ($data['images'] as $imageIndex => $url) {
                HinhAnhSanPham::create([
                    'ma_sp' => $product->ma_sp,
                    'url' => $url,
                    'anh_chinh' => $imageIndex === 0,
                ]);
            }

            foreach ($data['variants'] as $variantData) {
                $attributes = collect($variantData['attributes'])
                    ->map(fn (string $value, string $name) => ['name' => $name, 'value' => $value])
                    ->values()
                    ->all();

                $variant = BienTheSanPham::create([
                    'ma_sp' => $product->ma_sp,
                    'sku' => $variantData['sku'],
                    'variant_signature' => BienTheSanPham::signatureFor($attributes),
                    'gia_ban' => $variantData['price'],
                    'so_luong_ton' => $variantData['stock'],
                    'trang_thai' => true,
                ]);

                DB::table('lien_ket_bien_the_gia_tri')->insert(
                    collect($variantData['attributes'])
                        ->map(fn (string $value, string $name) => [
                            'ma_bt' => $variant->ma_bt,
                            'ma_gt' => $attributeValues[$name][$value]->ma_gt,
                        ])
                        ->values()
                        ->all()
                );
            }
        }

        $this->call(CommerceFeatureSeeder::class);

        $this->command?->info('Đã tạo 4 danh mục và '.count($this->products()).' sản phẩm thể thao.');
        $this->command?->info('Admin: admin@tienprosport.vn / admin123');
        $this->command?->info('User: user@example.com / user123');
    }

    private function seedUsers(): void
    {
        foreach ([
            ['Quản trị viên', 'admin@tienprosport.vn', '0900000000', true],
            ['Khách hàng Demo', 'user@example.com', '0909123456', false],
        ] as [$name, $email, $phone, $isAdmin]) {
            $user = KhachHang::create([
                'ten_kh' => $name,
                'email' => $email,
                'mat_khau' => Hash::make($isAdmin ? 'admin123' : 'user123'),
                'dien_thoai' => $phone,
                'vai_tro' => $isAdmin,
                'trang_thai' => true,
                'ngay_tao' => now()->subMonths(6),
            ]);
            GioHang::create(['ma_kh' => $user->ma_kh]);
        }
    }

    private function attributeDefinitions(): array
    {
        return [
            'Thương hiệu' => ['Nike', 'Adidas', 'Puma', 'Under Armour', 'Reebok', 'Vifa Sport', 'GoodFit'],
            'Màu sắc' => ['Đen', 'Trắng', 'Đỏ', 'Xanh dương', 'Xanh lá', 'Xám', 'Hồng', 'Tím', 'Vàng'],
            'Kích thước' => ['S', 'M', 'L', 'XL', '39', '40', '41', '42', '43'],
            'Khối lượng' => ['3 kg', '5 kg', '7.5 kg', '10 kg'],
            'Độ đàn hồi' => ['Nhẹ', 'Trung bình', 'Nặng', 'Rất nặng'],
        ];
    }

    private function products(): array
    {
        $image = fn (string $id) => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w=900&q=85";

        return [
            [
                'category' => 'Áo', 'name' => 'Áo Ba Lỗ Nike Dri-FIT',
                'description' => 'Áo ba lỗ tập luyện Nike Dri-FIT thoáng khí, thấm hút mồ hôi nhanh, phù hợp chạy bộ và tập gym.',
                'original_price' => 690000,
                'images' => [$image('photo-1581009146145-b5ef050c2e1e')],
                'variants' => $this->colorSizeVariants('NK-TANK', 'Nike', 549000, ['Đen', 'Trắng'], ['M', 'L', 'XL']),
            ],
            [
                'category' => 'Áo', 'name' => 'Áo Bóng Đá Adidas Tiro',
                'description' => 'Áo bóng đá Adidas Tiro chất liệu tái chế, form thể thao và công nghệ thoát ẩm Aeroready.',
                'original_price' => 890000,
                'images' => [$image('photo-1517466787929-bc90951d0974')],
                'variants' => $this->colorSizeVariants('AD-TIRO', 'Adidas', 749000, ['Đỏ', 'Xanh dương'], ['S', 'M', 'L']),
            ],
            [
                'category' => 'Áo', 'name' => 'Áo Thun Thể Thao Puma Active',
                'description' => 'Áo thun Puma Active mềm nhẹ, co giãn tốt, thích hợp mặc hằng ngày hoặc tập luyện.',
                'original_price' => 650000,
                'images' => [$image('photo-1521572163474-6864f9cf17ab')],
                'variants' => $this->colorSizeVariants('PM-ACT', 'Puma', 520000, ['Đen', 'Xám'], ['M', 'L', 'XL']),
            ],
            [
                'category' => 'Quần', 'name' => 'Quần Short Nike Flex',
                'description' => 'Quần short Nike Flex co giãn bốn chiều, cạp đàn hồi chắc chắn và túi hai bên tiện dụng.',
                'original_price' => 790000,
                'images' => [$image('photo-1591195853828-11db59a44f6b')],
                'variants' => $this->colorSizeVariants('NK-SHORT', 'Nike', 629000, ['Đen', 'Xám'], ['M', 'L', 'XL']),
            ],
            [
                'category' => 'Quần', 'name' => 'Quần Dài Adidas Essentials',
                'description' => 'Quần dài Adidas Essentials dáng thể thao, vải nỉ nhẹ và bo ống gọn gàng.',
                'original_price' => 1100000,
                'images' => [$image('photo-1552902865-b72c031ac5ea')],
                'variants' => $this->colorSizeVariants('AD-PANT', 'Adidas', 899000, ['Đen', 'Xanh dương'], ['S', 'M', 'L']),
            ],
            [
                'category' => 'Quần', 'name' => 'Quần Jogger Puma Training',
                'description' => 'Quần jogger Puma Training thoáng khí, phù hợp tập gym, chạy nhẹ và di chuyển hằng ngày.',
                'original_price' => 950000,
                'images' => [$image('photo-1506629082955-511b1aa562c8')],
                'variants' => $this->colorSizeVariants('PM-JOG', 'Puma', 760000, ['Đen', 'Xám'], ['M', 'L']),
            ],
            [
                'category' => 'Giày', 'name' => 'Giày Chạy Bộ Nike Revolution',
                'description' => 'Giày Nike Revolution có đệm foam êm, thân lưới thoáng khí và đế cao su bền cho chạy bộ hằng ngày.',
                'original_price' => 2100000,
                'images' => [$image('photo-1542291026-7eec264c27ff')],
                'variants' => $this->colorSizeVariants('NK-REV', 'Nike', 1790000, ['Đen', 'Trắng'], ['39', '40', '41', '42']),
            ],
            [
                'category' => 'Giày', 'name' => 'Giày Bóng Đá Adidas Predator',
                'description' => 'Giày bóng đá Adidas Predator hỗ trợ kiểm soát bóng, đế đinh phù hợp sân cỏ nhân tạo.',
                'original_price' => 2600000,
                'images' => [$image('photo-1511886929837-354d827aae26')],
                'variants' => $this->colorSizeVariants('AD-PRED', 'Adidas', 2290000, ['Đỏ', 'Đen'], ['40', '41', '42', '43']),
            ],
            [
                'category' => 'Giày', 'name' => 'Giày Tập Puma PWRFrame',
                'description' => 'Giày Puma PWRFrame ổn định bàn chân khi tập sức mạnh, đế bám tốt và thân giày bền.',
                'original_price' => 2300000,
                'images' => [$image('photo-1608231387042-66d1773070a5')],
                'variants' => $this->colorSizeVariants('PM-PWR', 'Puma', 1950000, ['Trắng', 'Xanh dương'], ['40', '41', '42']),
            ],
            [
                'category' => 'Phụ kiện', 'name' => 'Tạ Đơn Bọc Cao Su',
                'description' => 'Tạ đơn lõi gang bọc cao su, tay cầm chống trượt. Chọn khối lượng phù hợp với bài tập.',
                'original_price' => 590000,
                'images' => [$image('photo-1583454110551-21f2fa2afe61')],
                'variants' => [
                    $this->variant('VF-DB-3KG', 390000, 18, ['Thương hiệu' => 'Vifa Sport', 'Khối lượng' => '3 kg']),
                    $this->variant('VF-DB-5KG', 520000, 22, ['Thương hiệu' => 'Vifa Sport', 'Khối lượng' => '5 kg']),
                    $this->variant('VF-DB-75KG', 720000, 8, ['Thương hiệu' => 'Vifa Sport', 'Khối lượng' => '7.5 kg']),
                    $this->variant('VF-DB-10KG', 890000, 0, ['Thương hiệu' => 'Vifa Sport', 'Khối lượng' => '10 kg']),
                ],
            ],
            [
                'category' => 'Phụ kiện', 'name' => 'Thảm Tập Yoga TPE 6mm',
                'description' => 'Thảm yoga TPE dày 6 mm, bám sàn tốt, không mùi và dễ vệ sinh.',
                'original_price' => 450000,
                'images' => [$image('photo-1601925260368-ae2f83cf8b7f')],
                'variants' => [
                    $this->variant('GF-YOGA-TIM', 369000, 25, ['Thương hiệu' => 'GoodFit', 'Màu sắc' => 'Tím']),
                    $this->variant('GF-YOGA-HONG', 369000, 17, ['Thương hiệu' => 'GoodFit', 'Màu sắc' => 'Hồng']),
                    $this->variant('GF-YOGA-XANH', 369000, 0, ['Thương hiệu' => 'GoodFit', 'Màu sắc' => 'Xanh dương']),
                ],
            ],
            [
                'category' => 'Phụ kiện', 'name' => 'Dây Kháng Lực GoodFit',
                'description' => 'Dây kháng lực latex dùng cho tập mông, chân và phục hồi. Mỗi màu đại diện một mức đàn hồi.',
                'original_price' => 240000,
                'images' => [$image('photo-1598289431512-b97b0917affc')],
                'variants' => [
                    $this->variant('GF-BAND-VANG', 129000, 35, ['Thương hiệu' => 'GoodFit', 'Màu sắc' => 'Vàng', 'Độ đàn hồi' => 'Nhẹ']),
                    $this->variant('GF-BAND-DO', 149000, 28, ['Thương hiệu' => 'GoodFit', 'Màu sắc' => 'Đỏ', 'Độ đàn hồi' => 'Trung bình']),
                    $this->variant('GF-BAND-XANH', 169000, 14, ['Thương hiệu' => 'GoodFit', 'Màu sắc' => 'Xanh lá', 'Độ đàn hồi' => 'Nặng']),
                    $this->variant('GF-BAND-DEN', 189000, 0, ['Thương hiệu' => 'GoodFit', 'Màu sắc' => 'Đen', 'Độ đàn hồi' => 'Rất nặng']),
                ],
            ],
        ];
    }

    private function colorSizeVariants(
        string $skuPrefix,
        string $brand,
        int $price,
        array $colors,
        array $sizes
    ): array {
        $variants = [];
        foreach ($colors as $colorIndex => $color) {
            foreach ($sizes as $sizeIndex => $size) {
                $stock = ($colorIndex === count($colors) - 1 && $sizeIndex === count($sizes) - 1)
                    ? 0
                    : 6 + (($colorIndex + 1) * ($sizeIndex + 3));
                $variants[] = $this->variant(
                    $skuPrefix.'-'.$this->slug($color).'-'.$size,
                    $price,
                    $stock,
                    ['Thương hiệu' => $brand, 'Màu sắc' => $color, 'Kích thước' => $size]
                );
            }
        }

        return $variants;
    }

    private function variant(string $sku, int $price, int $stock, array $attributes): array
    {
        return compact('sku', 'price', 'stock', 'attributes');
    }

    private function slug(string $value): string
    {
        return str($value)->ascii()->upper()->replace(' ', '-')->toString();
    }
}
