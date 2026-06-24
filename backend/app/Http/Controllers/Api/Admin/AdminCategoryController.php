<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\DanhMuc;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class AdminCategoryController extends Controller
{
    public function index(Request $request)
    {
        $query = DanhMuc::withCount('sanPhams');

        if ($search = $request->input('search')) {
            $query->where('ten_dm', 'like', "%{$search}%");
        }

        return response()->json($query->orderBy('ten_dm')->get()->map(fn (DanhMuc $category) => [
            'id' => $category->ma_dm,
            'name' => $category->ten_dm,
            'active' => $category->trang_thai,
            'product_count' => $category->san_phams_count,
        ]));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:50', 'unique:danh_muc,ten_dm'],
        ]);

        $category = DanhMuc::create([
            'ten_dm' => trim($data['name']),
            'trang_thai' => true,
        ]);

        return response()->json([
            'id' => $category->ma_dm,
            'name' => $category->ten_dm,
            'active' => true,
            'product_count' => 0,
        ], 201);
    }

    public function update(Request $request, string $id)
    {
        $category = DanhMuc::findOrFail($id);
        $data = $request->validate([
            'name' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('danh_muc', 'ten_dm')->ignore($category->ma_dm, 'ma_dm'),
            ],
            'active' => ['sometimes', 'boolean'],
        ]);

        if (array_key_exists('active', $data) && !$data['active'] && $category->sanPhams()->where('trang_thai', 'active')->exists()) {
            throw ValidationException::withMessages([
                'active' => 'Không thể ẩn danh mục đang có sản phẩm hoạt động.',
            ]);
        }

        $updates = [];
        if (array_key_exists('name', $data)) {
            $updates['ten_dm'] = trim($data['name']);
        }
        if (array_key_exists('active', $data)) {
            $updates['trang_thai'] = $data['active'];
        }
        $category->update($updates);

        return response()->json([
            'id' => $category->ma_dm,
            'name' => $category->ten_dm,
            'active' => $category->trang_thai,
            'product_count' => $category->sanPhams()->count(),
        ]);
    }

    public function destroy(string $id)
    {
        $category = DanhMuc::findOrFail($id);
        if ($category->sanPhams()->exists()) {
            throw ValidationException::withMessages([
                'category' => 'Danh mục đang có sản phẩm nên không thể xóa. Hãy chuyển sản phẩm sang danh mục khác.',
            ]);
        }

        $category->delete();
        return response()->json(['message' => 'Đã xóa danh mục.']);
    }
}
