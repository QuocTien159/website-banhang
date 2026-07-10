<?php

namespace Database\Seeders;

use App\Models\BienTheSanPham;
use App\Models\DanhMuc;
use App\Models\GiaTriThuocTinh;
use App\Models\HinhAnhSanPham;
use App\Models\SanPham;
use App\Models\ThuocTinh;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class SportsCatalogSeeder extends Seeder
{
    public function run(): void
    {
        $categories = DanhMuc::query()->pluck('ma_dm', 'ten_dm');
        $values = GiaTriThuocTinh::query()
            ->with('thuocTinh')
            ->get()
            ->mapWithKeys(fn (GiaTriThuocTinh $value) => [
                $value->thuocTinh?->ten_tt.'|'.$value->gia_tri => $value->ma_gt,
            ]);

        foreach ($this->products() as $data) {
            if (SanPham::where('ten_sp', $data['name'])->exists()) {
                continue;
            }

            $categoryId = $categories[$data['category']] ?? null;
            if (!$categoryId) {
                throw new RuntimeException("Không tìm thấy danh mục {$data['category']}.");
            }

            DB::transaction(function () use ($data, $categoryId, $values) {
                $product = SanPham::create([
                    'ma_dm' => $categoryId,
                    'ten_sp' => $data['name'],
                    'mo_ta' => $data['description'],
                    'gia_co_ban' => $data['original_price'],
                    'trang_thai' => 'active',
                    'ngay_tao' => now(),
                ]);

                foreach ($data['variants'] as $index => $variantData) {
                    if (BienTheSanPham::where('sku', $variantData['sku'])->exists()) {
                        throw new RuntimeException("SKU {$variantData['sku']} đã tồn tại.");
                    }

                    $attributes = collect($variantData['attributes'])
                        ->map(fn (string $value, string $name) => ['name' => $name, 'value' => $value])
                        ->values()
                        ->all();
                    $attributeIds = collect($variantData['attributes'])->map(function (string $value, string $name) use ($values) {
                        $id = $values[$name.'|'.$value] ?? null;
                        if (!$id) throw new RuntimeException("Không tìm thấy giá trị thuộc tính {$name}: {$value}.");
                        return $id;
                    });

                    $variant = BienTheSanPham::create([
                        'ma_sp' => $product->ma_sp,
                        'sku' => $variantData['sku'],
                        'variant_signature' => BienTheSanPham::signatureFor($attributes),
                        'gia_ban' => $variantData['price'],
                        'so_luong_ton' => $variantData['stock'],
                        'nguong_canh_bao_ton' => $variantData['low_stock_threshold'],
                        'trang_thai' => true,
                    ]);
                    $variant->giaTriThuocTinhs()->sync($attributeIds->all());

                    HinhAnhSanPham::create([
                        'ma_sp' => $product->ma_sp,
                        'ma_bt' => $variant->ma_bt,
                        'url' => $data['images'][$index],
                        'anh_chinh' => $index === 0,
                    ]);
                }
            });
        }
    }

    private function products(): array
    {
        $image = fn (string $id) => "https://images.unsplash.com/{$id}?auto=format&fit=crop&w=900&q=85";

        return [
            [
                'category' => 'Áo',
                'name' => 'Áo Chạy Bộ Under Armour Tech 2.0',
                'description' => 'Áo chạy bộ nhẹ, nhanh khô với bề mặt Tech 2.0 thoáng khí. Form vận động linh hoạt, phù hợp chạy hằng ngày và các buổi tập cardio cường độ cao.',
                'original_price' => 890000,
                'images' => [$image('photo-1552674605-db6ffd4facb5'), $image('photo-1538805060514-97d9cc17730c')],
                'variants' => [
                    $this->variant('UA-TECH-BLK-M', 749000, 24, ['Thương hiệu' => 'Under Armour', 'Màu sắc' => 'Đen', 'Kích thước' => 'M']),
                    $this->variant('UA-TECH-BLU-L', 749000, 18, ['Thương hiệu' => 'Under Armour', 'Màu sắc' => 'Xanh dương', 'Kích thước' => 'L']),
                ],
            ],
            [
                'category' => 'Áo',
                'name' => 'Áo Tập Gym Reebok Speedwick',
                'description' => 'Áo tập gym Reebok Speedwick co giãn nhẹ, hỗ trợ thoát mồ hôi và giữ cơ thể khô ráo khi tập tạ, HIIT hoặc luyện tập trong nhà.',
                'original_price' => 790000,
                'images' => [$image('photo-1518611012118-696072aa579a'), $image('photo-1517838277536-f5f99be50118')],
                'variants' => [
                    $this->variant('RB-SW-BLK-M', 649000, 20, ['Thương hiệu' => 'Reebok', 'Màu sắc' => 'Đen', 'Kích thước' => 'M']),
                    $this->variant('RB-SW-GRY-L', 649000, 16, ['Thương hiệu' => 'Reebok', 'Màu sắc' => 'Xám', 'Kích thước' => 'L']),
                ],
            ],
            [
                'category' => 'Quần',
                'name' => 'Quần Short Chạy Adidas Own The Run',
                'description' => 'Quần short chạy bộ có lớp vải nhẹ, đường xẻ hỗ trợ sải bước và cạp co giãn chắc chắn. Thiết kế phù hợp chạy đường dài, chạy máy và tập luyện ngoài trời.',
                'original_price' => 990000,
                'images' => [$image('photo-1538805060514-97d9cc17730c'), $image('photo-1552674605-db6ffd4facb5')],
                'variants' => [
                    $this->variant('AD-OTR-BLK-M', 829000, 22, ['Thương hiệu' => 'Adidas', 'Màu sắc' => 'Đen', 'Kích thước' => 'M']),
                    $this->variant('AD-OTR-BLU-L', 829000, 15, ['Thương hiệu' => 'Adidas', 'Màu sắc' => 'Xanh dương', 'Kích thước' => 'L']),
                ],
            ],
            [
                'category' => 'Quần',
                'name' => 'Quần Legging Tập Puma FormKnit',
                'description' => 'Quần legging tập luyện ôm vừa, co giãn bốn chiều và hỗ trợ vận động linh hoạt. Chất vải FormKnit thoáng, phù hợp yoga, gym và các bài tập sức bền.',
                'original_price' => 1090000,
                'images' => [$image('photo-1517838277536-f5f99be50118'), $image('photo-1518611012118-696072aa579a')],
                'variants' => [
                    $this->variant('PM-FK-BLK-M', 899000, 17, ['Thương hiệu' => 'Puma', 'Màu sắc' => 'Đen', 'Kích thước' => 'M']),
                    $this->variant('PM-FK-PNK-L', 899000, 13, ['Thương hiệu' => 'Puma', 'Màu sắc' => 'Hồng', 'Kích thước' => 'L']),
                ],
            ],
            [
                'category' => 'Giày',
                'name' => 'Giày Bóng Rổ Nike Precision 7',
                'description' => 'Giày bóng rổ cổ thấp với đệm foam êm và đế ngoài bám sân, hỗ trợ đổi hướng nhanh trong các pha tăng tốc. Phù hợp tập luyện và thi đấu sân trong nhà.',
                'original_price' => 2490000,
                'images' => [$image('photo-1600185365483-26d7a4cc7519'), $image('photo-1518065896235-a4c93e088e7a')],
                'variants' => [
                    $this->variant('NK-P7-BLK-41', 2190000, 12, ['Thương hiệu' => 'Nike', 'Màu sắc' => 'Đen', 'Kích thước' => '41']),
                    $this->variant('NK-P7-WHT-42', 2190000, 10, ['Thương hiệu' => 'Nike', 'Màu sắc' => 'Trắng', 'Kích thước' => '42']),
                ],
            ],
            [
                'category' => 'Giày',
                'name' => 'Giày Chạy Trail Adidas Terrex Soulstride',
                'description' => 'Giày chạy địa hình có đế cao su bám tốt, lớp đệm ổn định và thân giày thoáng. Phù hợp đường mòn nhẹ, chạy công viên và các buổi chạy ngoài trời.',
                'original_price' => 2790000,
                'images' => [$image('photo-1517836357463-d25dfeac3438'), $image('photo-1461896836934-ffe607ba8211')],
                'variants' => [
                    $this->variant('AD-TRX-GRY-40', 2390000, 11, ['Thương hiệu' => 'Adidas', 'Màu sắc' => 'Xám', 'Kích thước' => '40']),
                    $this->variant('AD-TRX-GRN-42', 2390000, 9, ['Thương hiệu' => 'Adidas', 'Màu sắc' => 'Xanh lá', 'Kích thước' => '42']),
                ],
            ],
            [
                'category' => 'Phụ kiện',
                'name' => 'Bóng Đá Adidas Tiro League',
                'description' => 'Bóng đá luyện tập và thi đấu phong trào với bề mặt bền, độ nảy ổn định và các mảng ghép chắc chắn. Thích hợp cho sân cỏ nhân tạo hoặc sân futsal.',
                'original_price' => 790000,
                'images' => [$image('photo-1575361204480-aadea25e6e68'), $image('photo-1553778263-73a83bab9b0c')],
                'variants' => [
                    $this->variant('AD-TIRO-WHT', 690000, 28, ['Thương hiệu' => 'Adidas', 'Màu sắc' => 'Trắng']),
                    $this->variant('AD-TIRO-BLU', 690000, 21, ['Thương hiệu' => 'Adidas', 'Màu sắc' => 'Xanh dương']),
                ],
            ],
            [
                'category' => 'Phụ kiện',
                'name' => 'Bóng Rổ Nike Everyday Playground',
                'description' => 'Bóng rổ cao su có rãnh sâu, cầm chắc tay và chịu mài mòn tốt. Phù hợp tập dẫn bóng, ném rổ và chơi trên sân ngoài trời.',
                'original_price' => 690000,
                'images' => [$image('photo-1518065896235-a4c93e088e7a'), $image('photo-1504450758481-7338eba7524a')],
                'variants' => [
                    $this->variant('NK-BB-ORG', 590000, 26, ['Thương hiệu' => 'Nike', 'Màu sắc' => 'Vàng']),
                    $this->variant('NK-BB-BLK', 590000, 19, ['Thương hiệu' => 'Nike', 'Màu sắc' => 'Đen']),
                ],
            ],
        ];
    }

    private function variant(string $sku, int $price, int $stock, array $attributes): array
    {
        return compact('sku', 'price', 'stock', 'attributes') + ['low_stock_threshold' => 5];
    }
}
