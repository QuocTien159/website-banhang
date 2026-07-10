<?php

namespace App\Services;

use App\Models\DonHang;
use App\Models\PaymentShippingSetting;

class ShippingPaymentService
{
    public function __construct(private GhnShippingService $ghnShippingService)
    {
    }

    public function setting(): PaymentShippingSetting
    {
        return PaymentShippingSetting::current();
    }

    /** Shipping is calculated server-side using GHN only. */
    public function calculateShipping(float $subtotal, ?string $districtCode, ?string $wardCode, ?string $addressDetail, ?string $provinceId, array $items = []): array
    {
        $addressDetail = trim((string) $addressDetail);
        if (!$provinceId || !$districtCode || !$wardCode || $addressDetail === '') {
            return $this->invalid('Vui lòng chọn đầy đủ tỉnh/thành, quận/huyện, phường/xã và nhập địa chỉ chi tiết để tính phí vận chuyển.');
        }

        $setting = $this->setting();
        if ($setting->shipping_provider !== 'ghn' || !$setting->ghn_enabled) {
            return $this->invalid('Vận chuyển GHN hiện chưa được bật. Vui lòng liên hệ cửa hàng.');
        }
        if (!$this->ghnShippingService->hasPickupAddress($setting)) {
            return $this->invalid('GHN chưa được cấu hình đầy đủ địa chỉ kho lấy hàng. Vui lòng thử lại sau hoặc liên hệ cửa hàng.');
        }

        try {
            $province = $this->ghnShippingService->findProvince($provinceId);
            $district = $this->ghnShippingService->findDistrict($provinceId, $districtCode);
            $ward = $this->ghnShippingService->findWard($districtCode, $wardCode);
            if (!$province || !$district || !$ward) {
                return $this->invalid('Địa chỉ giao hàng không còn hợp lệ theo dữ liệu GHN. Vui lòng chọn lại.');
            }

            $fee = $this->ghnShippingService->calculateFee([
                'to_district_id' => (int) $districtCode,
                'to_ward_code' => $wardCode,
                'insurance_value' => $subtotal,
                'items' => $items,
            ], $setting);
            $baseShippingFee = (float) $fee['total'];
            $freeShipping = (bool) $setting->mien_phi_ship_bat && $subtotal >= (float) $setting->nguong_mien_phi_ship;

            return [
                'valid' => true, 'provider' => 'ghn',
                'address' => [
                    'province_type' => 'ghn', 'province_code' => (string) $province['id'], 'province_name' => $province['name'],
                    'district_code' => (string) $district['id'], 'district_name' => $district['name'],
                    'ward_code' => (string) $ward['code'], 'ward_name' => $ward['name'],
                ],
                'shipping_fee' => $freeShipping ? 0 : $baseShippingFee,
                'base_shipping_fee' => $baseShippingFee,
                'free_shipping_applied' => $freeShipping,
                'free_shipping_min_order_value' => $setting->mien_phi_ship_bat ? (float) $setting->nguong_mien_phi_ship : null,
                'service_id' => null, 'service_type_id' => 2, 'service_name' => 'GHN',
                'fee_breakdown' => ['raw' => $fee['raw'], 'dimensions' => $fee['dimensions']],
                'message' => $freeShipping ? 'Đơn hàng được miễn phí vận chuyển.' : null,
            ];
        } catch (\Throwable $exception) {
            return $this->invalid($this->ghnShippingService->friendlyMessage($exception));
        }
    }

    public function transferContent(DonHang $order): string
    {
        $template = $this->setting()->mau_noi_dung_chuyen_khoan ?: 'TienProSport {{order_code}}';
        return str_replace(['{{order_code}}', '{{order_id}}'], $order->ma_dh, $template);
    }

    public function qrUrl(DonHang $order): string
    {
        $setting = $this->setting();
        $content = $order->noi_dung_chuyen_khoan ?: $this->transferContent($order);
        return sprintf('https://img.vietqr.io/image/%s-%s-qr_only.png?amount=%s&addInfo=%s&accountName=%s', rawurlencode($setting->ma_ngan_hang), rawurlencode($setting->so_tai_khoan), (int) round((float) $order->tong_tien), rawurlencode($content), rawurlencode($setting->ten_chu_tai_khoan));
    }

    public function bankInfo(): array
    {
        $setting = $this->setting();
        return ['bank_code' => $setting->ma_ngan_hang, 'bank_name' => $setting->ten_ngan_hang, 'account_number' => $setting->so_tai_khoan, 'account_name' => $setting->ten_chu_tai_khoan, 'transfer_template' => $setting->mau_noi_dung_chuyen_khoan];
    }

    private function invalid(string $message): array
    {
        return ['valid' => false, 'provider' => 'ghn', 'address' => null, 'shipping_fee' => null, 'base_shipping_fee' => null, 'free_shipping_applied' => false, 'free_shipping_min_order_value' => null, 'service_id' => null, 'service_type_id' => null, 'service_name' => null, 'fee_breakdown' => null, 'message' => $message];
    }
}
