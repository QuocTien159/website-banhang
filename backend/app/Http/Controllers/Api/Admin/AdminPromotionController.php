<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\MaKhuyenMai;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AdminPromotionController extends Controller
{
    public function index(Request $request)
    {
        $query = MaKhuyenMai::query();
        if ($search = $request->input('search')) $query->where('code', 'like', "%{$search}%");
        return response()->json($query->orderByDesc('bat_dau')->get());
    }

    public function store(Request $request) { return $this->save($request, new MaKhuyenMai, true); }
    public function update(Request $request, string $id) { return $this->save($request, MaKhuyenMai::findOrFail($id), false); }

    public function destroy(string $id)
    {
        $promotion = MaKhuyenMai::findOrFail($id);
        $promotion->update(['trang_thai' => false]);
        return response()->json(['message' => 'Đã vô hiệu hóa mã khuyến mãi.']);
    }

    private function save(Request $request, MaKhuyenMai $promotion, bool $creating)
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:50', Rule::unique('ma_khuyen_mai', 'code')->ignore($promotion->ma_km, 'ma_km')],
            'type' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'numeric', 'min:0'],
            'minimum_order' => ['required', 'numeric', 'min:0'],
            'maximum_discount' => ['nullable', 'numeric', 'min:0'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['required', 'date', 'after:starts_at'],
            'usage_limit' => ['nullable', 'integer', 'min:1'],
            'active' => ['required', 'boolean'],
        ]);
        if ($data['type'] === 'percent' && $data['value'] > 100) abort(422, 'Phần trăm giảm không được vượt quá 100.');
        $promotion->fill([
            'code' => mb_strtoupper(trim($data['code'])), 'loai_giam' => $data['type'], 'gia_tri' => $data['value'],
            'don_toi_thieu' => $data['minimum_order'], 'giam_toi_da' => $data['maximum_discount'],
            'bat_dau' => $data['starts_at'], 'ket_thuc' => $data['ends_at'],
            'gioi_han_su_dung' => $data['usage_limit'], 'trang_thai' => $data['active'],
        ])->save();
        return response()->json($promotion, $creating ? 201 : 200);
    }
}
