<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HinhAnhThongBao;
use App\Models\ThongBao;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminAnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $query = ThongBao::with('hinhAnhs');
        if ($search = $request->input('search')) $query->where('tieu_de', 'like', "%{$search}%");
        if ($status = $request->input('status')) $query->where('trang_thai', $status);
        return response()->json($query->orderByDesc('ngay_tao')->get()->map(fn ($item) => $this->format($item)));
    }

    public function uploadImages(Request $request)
    {
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:min_width=300,min_height=200,max_width=5000,max_height=5000'],
        ]);

        return response()->json([
            'images' => collect($data['images'])->map(function ($image) {
                $path = $image->store('announcements', 'public');
                return ['url' => Storage::disk('public')->url($path), 'path' => $path];
            }),
        ], 201);
    }

    public function deleteUploadedImage(Request $request)
    {
        $data = $request->validate(['path' => ['required', 'string', 'starts_with:announcements/']]);
        Storage::disk('public')->delete($data['path']);
        return response()->json(['message' => 'Đã xóa hình ảnh.']);
    }

    public function store(Request $request) { return $this->save($request, new ThongBao, true); }
    public function update(Request $request, string $id) { return $this->save($request, ThongBao::with('hinhAnhs')->findOrFail($id), false); }

    public function destroy(string $id)
    {
        $item = ThongBao::with('hinhAnhs')->findOrFail($id);
        $paths = $item->hinhAnhs->pluck('duong_dan')->filter()->all();
        DB::transaction(fn () => $item->delete());
        foreach ($paths as $path) $this->deleteImageFile($path);
        return response()->json(['message' => 'Đã xóa thông báo và hình ảnh liên quan.']);
    }

    private function save(Request $request, ThongBao $item, bool $creating)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'content' => ['required', 'string', 'max:10000'],
            'type' => ['required', Rule::in(['update', 'promotion', 'maintenance', 'general'])],
            'status' => ['required', Rule::in(['draft', 'published', 'hidden'])],
            'published_at' => ['nullable', 'date'],
            'images' => ['nullable', 'array', 'max:10'],
            'images.*.id' => ['nullable', 'string'],
            'images.*.url' => ['required', 'string', 'max:500'],
            'images.*.path' => ['nullable', 'string', 'max:255'],
        ]);

        $removedPaths = DB::transaction(function () use ($item, $data) {
            $paths = [];
            $item->fill([
                'tieu_de' => trim($data['title']), 'noi_dung' => $data['content'], 'loai' => $data['type'],
                'trang_thai' => $data['status'],
                'ngay_xuat_ban' => $data['status'] === 'published' ? ($data['published_at'] ?? now()) : $data['published_at'],
                'ngay_tao' => $item->ngay_tao ?? now(), 'ngay_cap_nhat' => now(),
            ])->save();

            $keptIds = collect($data['images'] ?? [])->pluck('id')->filter()->all();
            foreach ($item->hinhAnhs()->whereNotIn('ma_anh_tb', $keptIds)->get() as $removed) {
                if ($removed->duong_dan) $paths[] = $removed->duong_dan;
                $removed->delete();
            }

            foreach ($data['images'] ?? [] as $order => $imageData) {
                if (!empty($imageData['id'])) {
                    $item->hinhAnhs()->where('ma_anh_tb', $imageData['id'])->firstOrFail()->update(['thu_tu' => $order]);
                } else {
                    HinhAnhThongBao::create([
                        'ma_tb' => $item->ma_tb, 'url' => $imageData['url'], 'duong_dan' => $imageData['path'] ?? null,
                        'thu_tu' => $order, 'ngay_tao' => now(),
                    ]);
                }
            }

            return $paths;
        });
        foreach ($removedPaths as $path) $this->deleteImageFile($path);

        return response()->json($this->format($item->fresh('hinhAnhs')), $creating ? 201 : 200);
    }

    private function deleteImageFile(?string $path): void
    {
        if ($path) Storage::disk('public')->delete($path);
    }

    private function format(ThongBao $item): array
    {
        return [
            'id' => $item->ma_tb, 'title' => $item->tieu_de, 'content' => $item->noi_dung,
            'type' => $item->loai, 'status' => $item->trang_thai,
            'published_at' => $item->ngay_xuat_ban?->toISOString(),
            'created_at' => $item->ngay_tao?->toISOString(),
            'images' => $item->hinhAnhs->map(fn ($image) => [
                'id' => $image->ma_anh_tb, 'url' => $image->url, 'path' => $image->duong_dan, 'order' => $image->thu_tu,
            ])->values(),
        ];
    }
}
