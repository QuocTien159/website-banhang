<?php

namespace App\Services;

use App\Models\PaymentShippingSetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GhnShippingService
{
    public function isConfigured(?PaymentShippingSetting $setting = null): bool
    {
        $setting ??= PaymentShippingSetting::current();

        return filled($this->token())
            && filled($this->shopId($setting))
            && filled($this->baseUrl());
    }

    public function hasPickupAddress(?PaymentShippingSetting $setting = null): bool
    {
        $setting ??= PaymentShippingSetting::current();

        return $this->isConfigured($setting)
            && filled($setting->pickup_district_id)
            && filled($setting->pickup_ward_code);
    }

    public function provinces(): array
    {
        return Cache::remember('ghn_provinces', now()->addDays(7), function () {
            $data = $this->request('get', '/shiip/public-api/master-data/province');

            return collect($data)
                ->map(fn (array $province) => [
                    'code' => (string) ($province['ProvinceID'] ?? ''),
                    'id' => (int) ($province['ProvinceID'] ?? 0),
                    'name' => $province['ProvinceName'] ?? '',
                    'provider' => 'ghn',
                ])
                ->filter(fn (array $province) => $province['id'] > 0 && $province['name'] !== '')
                ->sortBy('name')
                ->values()
                ->all();
        });
    }

    public function districts(int|string $provinceId): array
    {
        $provinceId = (int) $provinceId;

        return Cache::remember("ghn_districts_{$provinceId}", now()->addDays(7), function () use ($provinceId) {
            $data = $this->request('post', '/shiip/public-api/master-data/district', [
                'province_id' => $provinceId,
            ]);

            return collect($data)
                ->filter(fn (array $district) => (int) ($district['ProvinceID'] ?? 0) === $provinceId)
                ->map(fn (array $district) => [
                    'code' => (string) ($district['DistrictID'] ?? ''),
                    'id' => (int) ($district['DistrictID'] ?? 0),
                    'name' => $district['DistrictName'] ?? '',
                    'province_id' => (int) ($district['ProvinceID'] ?? 0),
                    'provider' => 'ghn',
                ])
                ->filter(fn (array $district) => $district['id'] > 0 && $district['name'] !== '')
                ->sortBy('name')
                ->values()
                ->all();
        });
    }

    public function wards(int|string $districtId): array
    {
        $districtId = (int) $districtId;

        return Cache::remember("ghn_wards_{$districtId}", now()->addDays(7), function () use ($districtId) {
            $data = $this->request('post', '/shiip/public-api/master-data/ward', [
                'district_id' => $districtId,
            ]);

            return collect($data)
                ->map(fn (array $ward) => [
                    'code' => (string) ($ward['WardCode'] ?? ''),
                    'id' => (string) ($ward['WardCode'] ?? ''),
                    'name' => $ward['WardName'] ?? '',
                    'district_id' => $districtId,
                    'provider' => 'ghn',
                ])
                ->filter(fn (array $ward) => $ward['code'] !== '' && $ward['name'] !== '')
                ->sortBy('name')
                ->values()
                ->all();
        });
    }

    public function findProvince(int|string $provinceId): ?array
    {
        return collect($this->provinces())->first(fn (array $province) => (int) $province['id'] === (int) $provinceId);
    }

    public function findDistrict(int|string $provinceId, int|string $districtId): ?array
    {
        return collect($this->districts($provinceId))->first(fn (array $district) => (int) $district['id'] === (int) $districtId);
    }

    public function findWard(int|string $districtId, string $wardCode): ?array
    {
        return collect($this->wards($districtId))->first(fn (array $ward) => (string) $ward['code'] === (string) $wardCode);
    }

    public function calculateFee(array $payload, ?PaymentShippingSetting $setting = null): array
    {
        $setting ??= PaymentShippingSetting::current();

        if (!$this->hasPickupAddress($setting)) {
            throw new RuntimeException('Chưa cấu hình đầy đủ địa chỉ kho lấy hàng GHN.');
        }

        $dimensions = $this->dimensions($payload['items'] ?? [], $setting);
        $data = $this->request('post', '/shiip/public-api/v2/shipping-order/fee', [
            'service_type_id' => (int) ($payload['service_type_id'] ?? 2),
            'insurance_value' => min(5000000, max(0, (int) ($payload['insurance_value'] ?? 0))),
            'from_district_id' => (int) $setting->pickup_district_id,
            'from_ward_code' => (string) $setting->pickup_ward_code,
            'to_district_id' => (int) $payload['to_district_id'],
            'to_ward_code' => (string) $payload['to_ward_code'],
            'weight' => $dimensions['weight'],
            'length' => $dimensions['length'],
            'width' => $dimensions['width'],
            'height' => $dimensions['height'],
        ], $setting);

        return [
            'total' => (int) ($data['total'] ?? 0),
            'service_fee' => (int) ($data['service_fee'] ?? ($data['total'] ?? 0)),
            'raw' => $data,
            'dimensions' => $dimensions,
        ];
    }

    public function dimensions(array $items, PaymentShippingSetting $setting): array
    {
        $defaultWeight = max(1, (int) ($setting->default_weight_gram ?: 500));
        $defaultLength = max(1, (int) ($setting->default_length_cm ?: 25));
        $defaultWidth = max(1, (int) ($setting->default_width_cm ?: 20));
        $defaultHeight = max(1, (int) ($setting->default_height_cm ?: 10));

        $quantity = max(1, collect($items)->sum(fn ($item) => max(1, (int) ($item['quantity'] ?? 1))));

        return [
            'weight' => max(1, $defaultWeight * $quantity),
            'length' => $defaultLength,
            'width' => $defaultWidth,
            'height' => max($defaultHeight, $defaultHeight * min($quantity, 5)),
        ];
    }

    public function friendlyMessage(\Throwable $exception): string
    {
        if (str_contains($exception->getMessage(), 'địa chỉ kho lấy hàng')) {
            return 'GHN chưa được cấu hình đầy đủ địa chỉ kho lấy hàng.';
        }

        return 'Không thể kết nối GHN để tính phí vận chuyển. Vui lòng thử lại.';
    }

    private function request(string $method, string $path, array $payload = [], ?PaymentShippingSetting $setting = null): array
    {
        $setting ??= PaymentShippingSetting::current();
        $response = Http::timeout(12)
            ->retry(1, 200)
            ->withOptions(['verify' => (bool) config('services.ghn.verify_ssl', true)])
            ->withHeaders([
                'Token' => (string) $this->token(),
                'ShopId' => (string) $this->shopId($setting),
                'Content-Type' => 'application/json',
            ])
            ->{$method}(rtrim($this->baseUrl(), '/').$path, $payload);

        if (!$response->ok()) {
            $message = $response->json('message') ?: $response->body();
            throw new RuntimeException('GHN API lỗi HTTP '.$response->status().': '.$message);
        }

        $json = $response->json();
        $code = (int) ($json['code'] ?? 0);
        if ($code !== 200) {
            throw new RuntimeException($json['message'] ?? 'GHN API trả về lỗi.');
        }

        return $json['data'] ?? [];
    }

    private function token(): ?string
    {
        return config('services.ghn.token');
    }

    private function shopId(PaymentShippingSetting $setting): ?string
    {
        return $setting->ghn_shop_id ?: config('services.ghn.shop_id');
    }

    private function baseUrl(): ?string
    {
        return config('services.ghn.base_url');
    }
}
