<?php

namespace Tests;

use App\Models\PaymentShippingSetting;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.ghn.token' => 'test-ghn-token',
            'services.ghn.shop_id' => '201036',
            'services.ghn.base_url' => 'https://ghn.test',
        ]);
        if (Schema::hasTable('cau_hinh_thanh_toan_van_chuyen')) {
            PaymentShippingSetting::current()->update([
                'shipping_provider' => 'ghn', 'ghn_enabled' => true, 'ghn_shop_id' => '201036',
                'pickup_province_id' => 202, 'pickup_province_name' => 'Ho Chi Minh',
                'pickup_district_id' => 1442, 'pickup_district_name' => 'Quan 1',
                'pickup_ward_code' => '20101', 'pickup_ward_name' => 'Ben Nghe',
                'pickup_address' => '1 Test Street', 'mien_phi_ship_bat' => false,
            ]);
        }
        Http::fake($this->ghnFakes());
    }

    protected function ghnFakes(): array
    {
        return [
            'https://ghn.test/shiip/public-api/master-data/province' => Http::response(['code' => 200, 'data' => [
                ['ProvinceID' => 202, 'ProvinceName' => 'Ho Chi Minh'],
                ['ProvinceID' => 201, 'ProvinceName' => 'Ha Noi'],
            ]]),
            'https://ghn.test/shiip/public-api/master-data/district' => Http::response(['code' => 200, 'data' => [
                ['DistrictID' => 1442, 'ProvinceID' => 202, 'DistrictName' => 'Quan 1'],
            ]]),
            'https://ghn.test/shiip/public-api/master-data/ward' => Http::response(['code' => 200, 'data' => [
                ['WardCode' => '20101', 'WardName' => 'Ben Nghe'],
            ]]),
            'https://ghn.test/shiip/public-api/v2/shipping-order/fee' => Http::response(['code' => 200, 'data' => [
                'total' => 22000, 'service_fee' => 22000,
            ]]),
        ];
    }
}
