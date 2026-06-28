<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class VietnamAdministrativeService
{
    private const SOURCE_URL = 'https://provinces.open-api.vn/api/?depth=3';

    public function provinces(): array
    {
        return [
            ['code' => 'hcm', 'name' => 'TP.HCM', 'province_code' => '79'],
            ['code' => 'hanoi', 'name' => 'Hà Nội', 'province_code' => '1'],
            ['code' => 'other', 'name' => 'Tỉnh khác', 'province_code' => null],
        ];
    }

    public function districts(string $provinceType): array
    {
        $provinceCode = $this->provinceCodeFromType($provinceType);
        if (!$provinceCode) {
            return [];
        }
        $province = $this->findProvince($provinceCode);
        if (!$province) {
            return [];
        }

        return collect($province['districts'] ?? [])
            ->map(fn ($district) => [
                'code' => (string) $district['code'],
                'name' => $district['name'],
                'shipping_zone' => $this->districtShippingZone($provinceType, (string) $district['code']),
            ])
            ->values()
            ->all();
    }

    public function wards(string $districtCode): array
    {
        $district = $this->findDistrict($districtCode);
        if (!$district) {
            return [];
        }

        return collect($district['wards'] ?? [])
            ->map(fn ($ward) => [
                'code' => (string) $ward['code'],
                'name' => $ward['name'],
            ])
            ->values()
            ->all();
    }

    public function validateAddress(string $provinceType, ?string $districtCode, ?string $wardCode): ?array
    {
        if ($provinceType === 'other') {
            return [
                'province_type' => 'other',
                'province_code' => null,
                'province_name' => 'Tỉnh khác',
                'district_code' => null,
                'district_name' => null,
                'ward_code' => null,
                'ward_name' => null,
                'shipping_zone' => 'other_province',
            ];
        }

        $provinceCode = $this->provinceCodeFromType($provinceType);
        if (!$provinceCode || !$districtCode || !$wardCode) {
            return null;
        }

        $province = $this->findProvince($provinceCode);
        if (!$province) {
            return null;
        }

        $district = collect($province['districts'] ?? [])
            ->first(fn ($item) => $this->normalizeCode((string) $item['code']) === $this->normalizeCode((string) $districtCode));
        if (!$district) {
            return null;
        }

        $ward = collect($district['wards'] ?? [])
            ->first(fn ($item) => $this->normalizeCode((string) $item['code']) === $this->normalizeCode((string) $wardCode));
        if (!$ward) {
            return null;
        }

        return [
            'province_type' => $provinceType,
            'province_code' => (string) $province['code'],
            'province_name' => $provinceType === 'hcm' ? 'TP.HCM' : 'Hà Nội',
            'district_code' => (string) $district['code'],
            'district_name' => $district['name'],
            'ward_code' => (string) $ward['code'],
            'ward_name' => $ward['name'],
            'shipping_zone' => $this->districtShippingZone($provinceType, (string) $district['code']),
        ];
    }

    public function districtShippingZone(string $provinceType, string $districtCode): string
    {
        if ($provinceType === 'other') {
            return 'other_province';
        }

        $inner = match ($provinceType) {
            'hcm' => ['760', '761', '764', '765', '766', '767', '768', '769', '770', '771', '772', '773', '774', '775', '776', '777', '778'],
            'hanoi' => ['1', '2', '3', '4', '5', '6', '7', '8', '9', '19', '21', '268'],
            default => [],
        };

        return in_array($this->normalizeCode($districtCode), $inner, true) ? 'inner_city' : 'suburban';
    }

    private function provinceCodeFromType(string $provinceType): ?string
    {
        return match ($provinceType) {
            'hcm' => '79',
            'hanoi' => '1',
            default => null,
        };
    }

    public function provinceName(string $provinceCode): ?string
    {
        return $this->findProvince($provinceCode)['name'] ?? null;
    }

    private function findProvince(string $provinceCode): ?array
    {
        return collect($this->tree())->first(fn ($item) => $this->normalizeCode((string) $item['code']) === $this->normalizeCode($provinceCode));
    }

    private function findDistrict(string $districtCode): ?array
    {
        foreach ($this->tree() as $province) {
            foreach (($province['districts'] ?? []) as $district) {
                if ($this->normalizeCode((string) $district['code']) === $this->normalizeCode($districtCode)) {
                    return $district;
                }
            }
        }

        return null;
    }

    private function tree(): array
    {
        return Cache::remember('vietnam_administrative_units_depth_3', now()->addDays(7), function () {
            try {
                $response = Http::timeout(8)->get(self::SOURCE_URL);
                if ($response->ok() && is_array($response->json())) {
                    return $response->json();
                }
            } catch (\Throwable) {
                // Use local fallback below.
            }

            return $this->fallback();
        });
    }

    private function fallback(): array
    {
        return [
            [
                'code' => 79,
                'name' => 'Thành phố Hồ Chí Minh',
                'districts' => [
                    ['code' => 760, 'name' => 'Quận 1', 'wards' => [
                        ['code' => 26734, 'name' => 'Phường Bến Nghé'],
                        ['code' => 26737, 'name' => 'Phường Bến Thành'],
                    ]],
                    ['code' => 770, 'name' => 'Quận 3', 'wards' => [
                        ['code' => 27118, 'name' => 'Phường Võ Thị Sáu'],
                    ]],
                    ['code' => 783, 'name' => 'Huyện Củ Chi', 'wards' => [
                        ['code' => 27565, 'name' => 'Thị trấn Củ Chi'],
                    ]],
                ],
            ],
            [
                'code' => 48,
                'name' => 'Thành phố Đà Nẵng',
                'districts' => [
                    ['code' => 490, 'name' => 'Quận Hải Châu', 'wards' => [
                        ['code' => 20194, 'name' => 'Phường Hải Châu I'],
                    ]],
                ],
            ],
            [
                'code' => 1,
                'name' => 'Thành phố Hà Nội',
                'districts' => [
                    ['code' => 1, 'name' => 'Quận Ba Đình', 'wards' => [
                        ['code' => 1, 'name' => 'Phường Phúc Xá'],
                    ]],
                ],
            ],
        ];
    }

    private function normalizeCode(string $code): string
    {
        return (string) ((int) $code);
    }
}
