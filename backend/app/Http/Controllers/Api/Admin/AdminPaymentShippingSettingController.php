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
            'shipping_provider' => ['required', 'in:ghn'],
            'ghn_enabled' => ['required', 'boolean'],
            'ghn_environment' => ['required', 'in:sandbox,production'],
            'ghn_shop_id' => ['nullable', 'string', 'max:50'],
            'pickup_name' => ['nullable', 'string', 'max:150'],
            'pickup_phone' => ['nullable', 'string', 'max:20'],
            'pickup_province_id' => ['nullable', 'integer', 'min:1'],
            'pickup_province_name' => ['nullable', 'string', 'max:100'],
            'pickup_district_id' => ['nullable', 'integer', 'min:1'],
            'pickup_district_name' => ['nullable', 'string', 'max:100'],
            'pickup_ward_code' => ['nullable', 'string', 'max:20'],
            'pickup_ward_name' => ['nullable', 'string', 'max:100'],
            'pickup_address' => ['nullable', 'string', 'max:255'],
            'default_weight_gram' => ['required', 'integer', 'min:1', 'max:50000'],
            'default_length_cm' => ['required', 'integer', 'min:1', 'max:200'],
            'default_width_cm' => ['required', 'integer', 'min:1', 'max:200'],
            'default_height_cm' => ['required', 'integer', 'min:1', 'max:200'],
            'free_shipping_enabled' => ['required', 'boolean'],
            'free_shipping_min_order_value' => ['required', 'numeric', 'min:0'],
            'bank_code' => ['required', 'string', 'max:30'],
            'bank_name' => ['required', 'string', 'max:100'],
            'account_number' => ['required', 'string', 'max:50'],
            'account_name' => ['required', 'string', 'max:150'],
            'transfer_template' => ['required', 'string', 'max:150'],
        ]);

        $setting = PaymentShippingSetting::current();
        $setting->update([
            'shipping_provider' => $data['shipping_provider'],
            'ghn_enabled' => $data['ghn_enabled'],
            'ghn_environment' => $data['ghn_environment'],
            'ghn_shop_id' => $data['ghn_shop_id'] ?? null,
            'pickup_name' => $data['pickup_name'] ?? null,
            'pickup_phone' => $data['pickup_phone'] ?? null,
            'pickup_province_id' => $data['pickup_province_id'] ?? null,
            'pickup_province_name' => $data['pickup_province_name'] ?? null,
            'pickup_district_id' => $data['pickup_district_id'] ?? null,
            'pickup_district_name' => $data['pickup_district_name'] ?? null,
            'pickup_ward_code' => $data['pickup_ward_code'] ?? null,
            'pickup_ward_name' => $data['pickup_ward_name'] ?? null,
            'pickup_address' => $data['pickup_address'] ?? null,
            'default_weight_gram' => $data['default_weight_gram'],
            'default_length_cm' => $data['default_length_cm'],
            'default_width_cm' => $data['default_width_cm'],
            'default_height_cm' => $data['default_height_cm'],
            'mien_phi_ship_bat' => $data['free_shipping_enabled'],
            'nguong_mien_phi_ship' => $data['free_shipping_min_order_value'],
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
            'shipping_provider' => $setting->shipping_provider ?? 'manual',
            'ghn_enabled' => (bool) $setting->ghn_enabled,
            'ghn_environment' => $setting->ghn_environment ?? config('services.ghn.env', 'sandbox'),
            'ghn_shop_id' => $setting->ghn_shop_id ?: config('services.ghn.shop_id'),
            'ghn_token_configured' => filled(config('services.ghn.token')),
            'pickup_name' => $setting->pickup_name,
            'pickup_phone' => $setting->pickup_phone,
            'pickup_province_id' => $setting->pickup_province_id,
            'pickup_province_name' => $setting->pickup_province_name,
            'pickup_district_id' => $setting->pickup_district_id,
            'pickup_district_name' => $setting->pickup_district_name,
            'pickup_ward_code' => $setting->pickup_ward_code,
            'pickup_ward_name' => $setting->pickup_ward_name,
            'pickup_address' => $setting->pickup_address,
            'default_weight_gram' => (int) ($setting->default_weight_gram ?: 500),
            'default_length_cm' => (int) ($setting->default_length_cm ?: 25),
            'default_width_cm' => (int) ($setting->default_width_cm ?: 20),
            'default_height_cm' => (int) ($setting->default_height_cm ?: 10),
            'free_shipping_enabled' => (bool) $setting->mien_phi_ship_bat,
            'free_shipping_min_order_value' => (float) $setting->nguong_mien_phi_ship,
            'bank_code' => $setting->ma_ngan_hang,
            'bank_name' => $setting->ten_ngan_hang,
            'account_number' => $setting->so_tai_khoan,
            'account_name' => $setting->ten_chu_tai_khoan,
            'transfer_template' => $setting->mau_noi_dung_chuyen_khoan,
        ];
    }
}
