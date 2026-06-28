<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PaymentShippingSetting;
use Illuminate\Http\Request;

class AdminPaymentShippingSettingController extends Controller
{
    public function show()
    {
        return response()->json($this->format(PaymentShippingSetting::current()));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'inner_city_fee' => ['required', 'numeric', 'min:0'],
            'outer_city_fee' => ['required', 'numeric', 'min:0'],
            'other_province_fee' => ['required', 'numeric', 'min:0'],
            'free_shipping_enabled' => ['required', 'boolean'],
            'free_shipping_min_order_value' => ['required', 'numeric', 'min:0'],
            'shop_province' => ['required', 'string', 'max:100'],
            'shop_province_code' => ['required', 'string', 'max:20'],
            'inner_city_districts' => ['array'],
            'inner_city_districts.*' => ['string', 'max:100'],
            'inner_city_district_codes' => ['array'],
            'inner_city_district_codes.*' => ['string', 'max:20'],
            'bank_code' => ['required', 'string', 'max:30'],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_name' => ['required', 'string', 'max:150'],
            'transfer_template' => ['required', 'string', 'max:150'],
        ]);

        $setting = PaymentShippingSetting::current();
        $setting->update([
            'phi_noi_thanh' => $data['inner_city_fee'],
            'phi_ngoai_thanh' => $data['outer_city_fee'],
            'phi_tinh_khac' => $data['other_province_fee'],
            'mien_phi_ship_bat' => $data['free_shipping_enabled'],
            'nguong_mien_phi_ship' => $data['free_shipping_min_order_value'],
            'tinh_thanh_shop' => $data['shop_province'],
            'ma_tinh_thanh_shop' => $data['shop_province_code'],
            'quan_huyen_noi_thanh' => $data['inner_city_districts'] ?? [],
            'ma_quan_huyen_noi_thanh' => $data['inner_city_district_codes'] ?? [],
            'ma_ngan_hang' => $data['bank_code'],
            'ten_ngan_hang' => $data['bank_name'],
            'so_tai_khoan' => $data['account_number'],
            'ten_chu_tai_khoan' => $data['account_name'],
            'mau_noi_dung_chuyen_khoan' => $data['transfer_template'],
        ]);

        return response()->json([
            'message' => 'Đã cập nhật cấu hình vận chuyển và thanh toán.',
            'settings' => $this->format($setting->fresh()),
        ]);
    }

    private function format(PaymentShippingSetting $setting): array
    {
        return [
            'inner_city_fee' => (float) $setting->phi_noi_thanh,
            'outer_city_fee' => (float) $setting->phi_ngoai_thanh,
            'other_province_fee' => (float) $setting->phi_tinh_khac,
            'free_shipping_enabled' => (bool) $setting->mien_phi_ship_bat,
            'free_shipping_min_order_value' => (float) $setting->nguong_mien_phi_ship,
            'shop_province' => $setting->tinh_thanh_shop,
            'shop_province_code' => $setting->ma_tinh_thanh_shop,
            'inner_city_districts' => $setting->quan_huyen_noi_thanh ?: [],
            'inner_city_district_codes' => $setting->ma_quan_huyen_noi_thanh ?: [],
            'bank_code' => $setting->ma_ngan_hang,
            'bank_name' => $setting->ten_ngan_hang,
            'account_number' => $setting->so_tai_khoan,
            'account_name' => $setting->ten_chu_tai_khoan,
            'transfer_template' => $setting->mau_noi_dung_chuyen_khoan,
        ];
    }
}
