<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\GiaTriThuocTinh;
use App\Models\ThuocTinh;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminAttributeController extends Controller
{
    public function index(Request $request)
    {
        $query = ThuocTinh::withCount('giaTriThuocTinhs');

        if ($search = $request->input('search')) {
            $query->where(function ($builder) use ($search) {
                $builder->where('ten_tt', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%");
            });
        }

        if ($request->filled('active')) {
            $query->where('trang_thai', filter_var($request->input('active'), FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json($query->orderBy('ten_tt')->get()->map(
            fn (ThuocTinh $attribute) => $this->formatAttribute($attribute)
        ));
    }

    public function show(string $id)
    {
        $attribute = ThuocTinh::with(['giaTriThuocTinhs' => fn ($query) => $query
            ->orderBy('thu_tu')
            ->orderBy('gia_tri')])
            ->findOrFail($id);

        return response()->json($this->formatAttribute($attribute, true));
    }

    public function store(Request $request)
    {
        $data = $this->validateAttribute($request);
        $slug = $this->normalizeSlug($data['slug'] ?? null, $data['name']);

        if (ThuocTinh::where('slug', $slug)->exists()) {
            throw ValidationException::withMessages(['slug' => 'Slug thuộc tính đã tồn tại.']);
        }

        if (ThuocTinh::where('ten_tt', trim($data['name']))->exists()) {
            throw ValidationException::withMessages(['name' => 'Tên thuộc tính đã tồn tại.']);
        }

        $attribute = ThuocTinh::create([
            'ten_tt' => trim($data['name']),
            'slug' => $slug,
            'loai_hien_thi' => 'select',
            'trang_thai' => $data['active'] ?? true,
            'mo_ta' => $data['description'] ?? null,
            'ngay_tao' => now(),
            'ngay_cap_nhat' => now(),
        ]);

        foreach ($data['values'] ?? [] as $valueData) {
            $this->createValue($attribute, $valueData);
        }

        return response()->json($this->formatAttribute($attribute->fresh('giaTriThuocTinhs'), true), 201);
    }

    public function update(Request $request, string $id)
    {
        $attribute = ThuocTinh::findOrFail($id);
        $data = $this->validateAttribute($request, $attribute);
        $slug = $this->normalizeSlug($data['slug'] ?? null, $data['name']);

        if (ThuocTinh::where('slug', $slug)->where('ma_tt', '!=', $attribute->ma_tt)->exists()) {
            throw ValidationException::withMessages(['slug' => 'Slug thuộc tính đã tồn tại.']);
        }

        if (ThuocTinh::where('ten_tt', trim($data['name']))->where('ma_tt', '!=', $attribute->ma_tt)->exists()) {
            throw ValidationException::withMessages(['name' => 'Tên thuộc tính đã tồn tại.']);
        }

        $attribute->update([
            'ten_tt' => trim($data['name']),
            'slug' => $slug,
            'loai_hien_thi' => $attribute->loai_hien_thi ?: 'select',
            'trang_thai' => $data['active'] ?? true,
            'mo_ta' => $data['description'] ?? null,
            'ngay_cap_nhat' => now(),
        ]);

        return response()->json($this->formatAttribute($attribute->fresh('giaTriThuocTinhs'), true));
    }

    public function destroy(string $id)
    {
        $attribute = ThuocTinh::with('giaTriThuocTinhs.bienThes')->findOrFail($id);

        if ($attribute->giaTriThuocTinhs->contains(fn (GiaTriThuocTinh $value) => $value->bienThes->isNotEmpty())) {
            throw ValidationException::withMessages([
                'attribute' => 'Thuộc tính đang được sản phẩm sử dụng nên không thể xóa. Hãy chuyển sang trạng thái tạm ẩn.',
            ]);
        }

        $attribute->giaTriThuocTinhs()->delete();
        $attribute->delete();

        return response()->json(['message' => 'Đã xóa thuộc tính.']);
    }

    public function storeValue(Request $request, string $id)
    {
        $attribute = ThuocTinh::findOrFail($id);
        $value = $this->createValue($attribute, $this->validateValue($request));

        return response()->json($this->formatValue($value), 201);
    }

    public function updateValue(Request $request, string $id, string $valueId)
    {
        $attribute = ThuocTinh::findOrFail($id);
        $value = $attribute->giaTriThuocTinhs()->findOrFail($valueId);
        $data = $this->validateValue($request);
        $slug = $this->normalizeSlug($data['slug'] ?? null, $data['value']);

        if ($this->valueSlugExists($attribute, $slug, $value->ma_gt)) {
            throw ValidationException::withMessages(['slug' => 'Slug giá trị thuộc tính đã tồn tại trong thuộc tính này.']);
        }

        if ($this->valueNameExists($attribute, $data['value'], $value->ma_gt)) {
            throw ValidationException::withMessages(['value' => 'Giá trị thuộc tính đã tồn tại.']);
        }

        $value->update([
            'gia_tri' => trim($data['value']),
            'slug' => $slug,
            'ma_mau' => $data['color_code'] ?? null,
            'thu_tu' => $data['sort_order'] ?? 0,
            'trang_thai' => $data['active'] ?? true,
            'ngay_cap_nhat' => now(),
        ]);

        return response()->json($this->formatValue($value->fresh()));
    }

    public function destroyValue(string $id, string $valueId)
    {
        $attribute = ThuocTinh::findOrFail($id);
        $value = $attribute->giaTriThuocTinhs()->findOrFail($valueId);

        if ($value->bienThes()->exists()) {
            throw ValidationException::withMessages([
                'value' => 'Giá trị thuộc tính đang được sản phẩm sử dụng nên không thể xóa. Hãy chuyển sang trạng thái tạm ẩn.',
            ]);
        }

        $value->delete();

        return response()->json(['message' => 'Đã xóa giá trị thuộc tính.']);
    }

    private function validateAttribute(Request $request, ?ThuocTinh $attribute = null): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:50'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/'],
            'active' => ['sometimes', 'boolean'],
            'description' => ['nullable', 'string', 'max:1000'],
            'values' => ['sometimes', 'array', 'max:100'],
            'values.*.value' => ['required_with:values', 'string', 'max:50'],
            'values.*.slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/'],
            'values.*.color_code' => ['nullable', 'string', 'max:20'],
            'values.*.sort_order' => ['nullable', 'integer', 'min:0'],
            'values.*.active' => ['sometimes', 'boolean'],
        ]);
    }

    private function validateValue(Request $request): array
    {
        return $request->validate([
            'value' => ['required', 'string', 'max:50'],
            'slug' => ['nullable', 'string', 'max:80', 'regex:/^[a-z0-9-]+$/'],
            'color_code' => ['nullable', 'string', 'max:20'],
            'sort_order' => ['nullable', 'integer', 'min:0'],
            'active' => ['sometimes', 'boolean'],
        ]);
    }

    private function createValue(ThuocTinh $attribute, array $data): GiaTriThuocTinh
    {
        $slug = $this->normalizeSlug($data['slug'] ?? null, $data['value']);

        if ($this->valueSlugExists($attribute, $slug)) {
            throw ValidationException::withMessages(['slug' => "Slug {$slug} đã tồn tại trong thuộc tính này."]);
        }

        if ($this->valueNameExists($attribute, $data['value'])) {
            throw ValidationException::withMessages(['value' => "Giá trị {$data['value']} đã tồn tại."]);
        }

        return GiaTriThuocTinh::create([
            'ma_tt' => $attribute->ma_tt,
            'gia_tri' => trim($data['value']),
            'slug' => $slug,
            'ma_mau' => $data['color_code'] ?? null,
            'thu_tu' => $data['sort_order'] ?? 0,
            'trang_thai' => $data['active'] ?? true,
            'ngay_tao' => now(),
            'ngay_cap_nhat' => now(),
        ]);
    }

    private function valueSlugExists(ThuocTinh $attribute, string $slug, ?string $ignoreId = null): bool
    {
        return $attribute->giaTriThuocTinhs()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($query) => $query->where('ma_gt', '!=', $ignoreId))
            ->exists();
    }

    private function valueNameExists(ThuocTinh $attribute, string $value, ?string $ignoreId = null): bool
    {
        return $attribute->giaTriThuocTinhs()
            ->where('gia_tri', trim($value))
            ->when($ignoreId, fn ($query) => $query->where('ma_gt', '!=', $ignoreId))
            ->exists();
    }

    private function normalizeSlug(?string $slug, string $fallback): string
    {
        return Str::slug($slug ?: $fallback) ?: Str::lower(Str::random(8));
    }

    private function formatAttribute(ThuocTinh $attribute, bool $includeValues = false): array
    {
        $payload = [
            'id' => $attribute->ma_tt,
            'name' => $attribute->ten_tt,
            'slug' => $attribute->slug,
            'active' => (bool) ($attribute->trang_thai ?? true),
            'description' => $attribute->mo_ta,
            'value_count' => $attribute->gia_tri_thuoc_tinhs_count ?? $attribute->giaTriThuocTinhs()->count(),
            'created_at' => optional($attribute->ngay_tao)->toISOString(),
        ];

        if ($includeValues) {
            $payload['values'] = $attribute->giaTriThuocTinhs
                ->sortBy([['thu_tu', 'asc'], ['gia_tri', 'asc']])
                ->map(fn (GiaTriThuocTinh $value) => $this->formatValue($value))
                ->values();
        }

        return $payload;
    }

    private function formatValue(GiaTriThuocTinh $value): array
    {
        return [
            'id' => $value->ma_gt,
            'attribute_id' => $value->ma_tt,
            'value' => $value->gia_tri,
            'slug' => $value->slug,
            'color_code' => $value->ma_mau,
            'sort_order' => $value->thu_tu,
            'active' => (bool) ($value->trang_thai ?? true),
            'created_at' => optional($value->ngay_tao)->toISOString(),
        ];
    }
}
