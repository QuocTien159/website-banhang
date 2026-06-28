<?php

namespace App\Services;

use App\Models\DonHang;
use App\Models\PaymentShippingSetting;

class ShippingPaymentService
{
    public function __construct(private VietnamAdministrativeService $administrativeService)
    {
    }

    public function setting(): PaymentShippingSetting
    {
        return PaymentShippingSetting::current();
    }

    public function calculateShipping(
        float $subtotal,
        ?string $provinceType,
        ?string $districtCode,
        ?string $wardCode,
        ?string $addressDetail
    ): array {
        $provinceType = trim((string) $provinceType);
        $addressDetail = trim((string) $addressDetail);

        if (!in_array($provinceType, ['hcm', 'hanoi', 'other'], true)) {
            return $this->invalid('Vui lòng chọn tỉnh/thành phố.');
        }
        if ($addressDetail === '') {
            return $this->invalid('Vui lòng nhập địa chỉ nhà.');
        }
        if ($provinceType !== 'other' && !$districtCode) {
            return $this->invalid('Vui lòng chọn quận/huyện.');
        }
        if ($provinceType !== 'other' && !$wardCode) {
            return $this->invalid('Vui lòng chọn phường/xã.');
        }

        $address = $this->administrativeService->validateAddress($provinceType, $districtCode, $wardCode);
        if (!$address) {
            return $this->invalid('Địa chỉ hành chính không hợp lệ. Vui lòng chọn lại tỉnh/thành, quận/huyện và phường/xã.');
        }

        $zone = $address['shipping_zone'];
        $shippingFee = match ($zone) {
            'inner_city' => 0,
            'suburban' => 30000,
            default => 50000,
        };

        return [
            'valid' => true,
            'address' => $address,
            'area_type' => $zone,
            'shipping_zone' => $zone,
            'area_label' => $this->zoneLabel($zone),
            'shipping_fee' => $shippingFee,
            'base_shipping_fee' => $shippingFee,
            'free_shipping_applied' => $zone === 'inner_city',
            'free_shipping_min_order_value' => null,
            'message' => $zone === 'inner_city' ? 'Miễn phí vận chuyển.' : null,
        ];
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

        return sprintf(
            'https://img.vietqr.io/image/%s-%s-qr_only.png?amount=%s&addInfo=%s&accountName=%s',
            rawurlencode($setting->ma_ngan_hang),
            rawurlencode($setting->so_tai_khoan),
            (int) round((float) $order->tong_tien),
            rawurlencode($content),
            rawurlencode($setting->ten_chu_tai_khoan)
        );
    }

    public function bankInfo(): array
    {
        $setting = $this->setting();

        return [
            'bank_code' => $setting->ma_ngan_hang,
            'bank_name' => $setting->ten_ngan_hang,
            'account_number' => $setting->so_tai_khoan,
            'account_name' => $setting->ten_chu_tai_khoan,
            'transfer_template' => $setting->mau_noi_dung_chuyen_khoan,
        ];
    }

    private function invalid(string $message): array
    {
        return [
            'valid' => false,
            'address' => null,
            'area_type' => null,
            'shipping_zone' => null,
            'area_label' => null,
            'shipping_fee' => null,
            'base_shipping_fee' => null,
            'free_shipping_applied' => false,
            'message' => $message,
        ];
    }

    private function zoneLabel(string $zone): string
    {
        return match ($zone) {
            'inner_city' => 'Nội thành',
            'suburban' => 'Ngoại thành',
            default => 'Tỉnh khác',
        };
    }
}
