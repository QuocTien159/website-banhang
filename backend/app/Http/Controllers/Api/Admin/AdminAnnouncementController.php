<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\HinhAnhThongBao;
use App\Models\ThongBao;
use App\Services\CloudinaryMediaService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AdminAnnouncementController extends Controller
{
    public function index(Request $request)
    {
        $this->abortUnlessAdmin($request);
        $query = ThongBao::with('hinhAnhs');
        if ($search = $request->input('search')) $query->where('tieu_de', 'like', "%{$search}%");
        if ($status = $request->input('status')) $query->where('trang_thai', $status);
        return response()->json($query->orderByDesc('ngay_tao')->get()->map(fn (ThongBao $item) => $this->format($item)));
    }

    public function uploadImages(Request $request)
    {
        $this->abortUnlessAdmin($request);
        $data = $request->validate([
            'images' => ['required', 'array', 'min:1', 'max:10'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:20480', 'dimensions:max_width=10000,max_height=10000'],
        ]);

        foreach ($data['images'] as $image) {
            $dimensions = $image->dimensions();
            if (!$dimensions || max($dimensions[0], $dimensions[1]) < 1000) {
                return response()->json([
                    'message' => 'Ảnh thông báo cần có cạnh dài tối thiểu 1000 px để hiển thị rõ nét.',
                    'errors' => ['images' => ['Ảnh thông báo cần có cạnh dài tối thiểu 1000 px để hiển thị rõ nét.']],
                ], 422);
            }
        }

        $media = app(CloudinaryMediaService::class);
        return response()->json([
            'images' => collect($data['images'])->map(fn ($image) => $media->upload($image, 'announcements', $request->user()->ma_kh)),
        ], 201);
    }

    public function deleteUploadedImage(Request $request)
    {
        $this->abortUnlessAdmin($request);
        $data = $request->validate([
            'path' => ['nullable', 'string', 'starts_with:announcements/'],
            'upload_token' => ['nullable', 'string'],
        ]);

        if (!empty($data['upload_token'])) {
            $asset = app(CloudinaryMediaService::class)->verifiedUpload($data['upload_token'], 'announcements', $request->user()->ma_kh);
            app(CloudinaryMediaService::class)->delete($asset['provider'], $asset['public_id'] ?? null, $asset['path'] ?? null);
        } elseif (!empty($data['path'])) {
            app(CloudinaryMediaService::class)->delete('local', null, $data['path']);
        } else {
            return response()->json(['message' => 'Thiếu thông tin ảnh cần xóa.'], 422);
        }

        return response()->json(['message' => 'Đã xóa ảnh.']);
    }

    public function store(Request $request) { return $this->save($request, new ThongBao, true); }
    public function update(Request $request, string $id) { return $this->save($request, ThongBao::with('hinhAnhs')->findOrFail($id), false); }

    public function destroy(Request $request, string $id)
    {
        $this->abortUnlessAdmin($request);
        $item = ThongBao::with('hinhAnhs')->findOrFail($id);
        $assets = $item->hinhAnhs->map(fn ($image) => [$image->provider, $image->cloudinary_public_id, $image->duong_dan])->all();
        DB::transaction(fn () => $item->delete());
        foreach ($assets as [$provider, $publicId, $path]) app(CloudinaryMediaService::class)->delete($provider, $publicId, $path);
        return response()->json(['message' => 'Đã xóa thông báo và ảnh liên quan.']);
    }

    private function save(Request $request, ThongBao $item, bool $creating)
    {
        $this->abortUnlessAdmin($request);
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
            'images.*.upload_token' => ['nullable', 'string'],
            'cover_image' => ['nullable', 'array'],
            'cover_image.id' => ['nullable', 'string'],
            'cover_image.url' => ['nullable', 'string', 'max:500'],
            'cover_image.path' => ['nullable', 'string', 'max:255'],
            'cover_image.upload_token' => ['nullable', 'string'],
            'cover_image.crop' => ['nullable', 'array'],
            'cover_image.crop.x' => ['nullable', 'integer', 'min:0'],
            'cover_image.crop.y' => ['nullable', 'integer', 'min:0'],
            'cover_image.crop.width' => ['nullable', 'integer', 'min:1'],
            'cover_image.crop.height' => ['nullable', 'integer', 'min:1'],
            'cover_image.crop.rotation' => ['nullable', 'numeric', 'between:-360,360'],
        ]);

        $removedAssets = DB::transaction(function () use ($item, $data, $request) {
            $item->fill([
                'tieu_de' => trim($data['title']), 'noi_dung' => $data['content'], 'loai' => $data['type'],
                'trang_thai' => $data['status'],
                'ngay_xuat_ban' => $data['status'] === 'published' ? ($data['published_at'] ?? now()) : $data['published_at'],
                'ngay_tao' => $item->ngay_tao ?? now(), 'ngay_cap_nhat' => now(),
            ])->save();

            $assets = [];
            $keptIds = collect($data['images'] ?? [])->pluck('id')->filter()->all();
            foreach ($item->hinhAnhs()->whereNotIn('ma_anh_tb', $keptIds)->get() as $removed) {
                if ($removed->vai_tro_anh === 'announcement_cover') continue;
                $assets[] = [$removed->provider, $removed->cloudinary_public_id, $removed->duong_dan];
                $removed->delete();
            }

            $existingCover = $item->hinhAnhs()->where('vai_tro_anh', 'announcement_cover')->first();
            $coverData = $data['cover_image'] ?? null;
            if (!$coverData && $existingCover) {
                $assets[] = [$existingCover->provider, $existingCover->cloudinary_public_id, $existingCover->duong_dan];
                $existingCover->delete();
            } elseif ($coverData && empty($coverData['id'])) {
                if ($existingCover) {
                    $assets[] = [$existingCover->provider, $existingCover->cloudinary_public_id, $existingCover->duong_dan];
                    $existingCover->delete();
                }
                $asset = $this->verifiedImageAsset($coverData, $request->user()->ma_kh);
                HinhAnhThongBao::create($this->imagePayload($item, $asset, $coverData['crop'] ?? [], 'announcement_cover', 0));
            }

            foreach ($data['images'] ?? [] as $order => $imageData) {
                if (!empty($imageData['id'])) {
                    $item->hinhAnhs()->where('ma_anh_tb', $imageData['id'])->firstOrFail()->update(['thu_tu' => $order]);
                    continue;
                }

                $asset = $this->verifiedImageAsset($imageData, $request->user()->ma_kh);
                HinhAnhThongBao::create($this->imagePayload($item, $asset, [], 'announcement_content', $order));
            }
            return $assets;
        });

        foreach ($removedAssets as [$provider, $publicId, $path]) app(CloudinaryMediaService::class)->delete($provider, $publicId, $path);
        return response()->json($this->format($item->fresh('hinhAnhs')), $creating ? 201 : 200);
    }

    private function verifiedImageAsset(array $image, string $actorId): array
    {
        if (!empty($image['upload_token'])) {
            return app(CloudinaryMediaService::class)->verifiedUpload($image['upload_token'], 'announcements', $actorId);
        }
        if (!empty($image['path']) && str_starts_with($image['path'], 'announcements/')) {
            return ['url' => $image['url'], 'path' => $image['path'], 'provider' => 'local'];
        }
        throw new \RuntimeException('Ảnh mới phải được tải lên qua API quản lý ảnh.');
    }

    private function abortUnlessAdmin(Request $request): void
    {
        abort_unless($request->user()?->isAdmin(), 403, 'Bạn không có quyền quản lý hình ảnh.');
    }

    private function format(ThongBao $item): array
    {
        $media = app(CloudinaryMediaService::class);
        return [
            'id' => $item->ma_tb, 'title' => $item->tieu_de, 'content' => $item->noi_dung,
            'type' => $item->loai, 'status' => $item->trang_thai,
            'published_at' => $item->ngay_xuat_ban?->toISOString(),
            'created_at' => $item->ngay_tao?->toISOString(),
            'cover_image' => $this->formatImage($item->hinhAnhs->firstWhere('vai_tro_anh', 'announcement_cover'), $media),
            'images' => $item->hinhAnhs->where('vai_tro_anh', '!=', 'announcement_cover')->map(function ($image) use ($media) {
                $urls = $media->urls($image->original_url ?? $image->url, $image->provider, $this->crop($image));
                return [
                    'id' => $image->ma_anh_tb,
                    'url' => $urls['original_url'],
                    ...$urls,
                    'path' => $image->duong_dan,
                    'provider' => $image->provider,
                    'public_id' => $image->cloudinary_public_id,
                    'width' => $image->chieu_rong,
                    'height' => $image->chieu_cao,
                    'order' => $image->thu_tu,
                ];
            })->values(),
        ];
    }

    private function imagePayload(ThongBao $item, array $asset, array $crop, string $role, int $order): array
    {
        return [
            'ma_tb' => $item->ma_tb, 'url' => $asset['url'], 'original_url' => $asset['url'], 'duong_dan' => $asset['path'] ?? null,
            'provider' => $asset['provider'], 'cloudinary_public_id' => $asset['public_id'] ?? null,
            'chieu_rong' => $asset['width'] ?? null, 'chieu_cao' => $asset['height'] ?? null,
            'kich_thuoc_byte' => $asset['bytes'] ?? null, 'dinh_dang' => $asset['format'] ?? null,
            'crop_x' => $crop['x'] ?? null, 'crop_y' => $crop['y'] ?? null, 'crop_width' => $crop['width'] ?? null, 'crop_height' => $crop['height'] ?? null,
            'goc_xoay' => $crop['rotation'] ?? 0, 'ty_le_khung_hinh' => $role === 'announcement_cover' ? '16:9' : null,
            'vai_tro_anh' => $role, 'thu_tu' => $order, 'ngay_tao' => now(),
        ];
    }

    private function formatImage(?HinhAnhThongBao $image, CloudinaryMediaService $media): ?array
    {
        if (!$image) return null;
        $urls = $media->urls($image->original_url ?? $image->url, $image->provider, $this->crop($image));
        return ['id' => $image->ma_anh_tb, 'url' => $urls['original_url'], ...$urls, 'path' => $image->duong_dan, 'order' => $image->thu_tu];
    }

    private function crop(HinhAnhThongBao $image): array
    {
        return ['x' => $image->crop_x, 'y' => $image->crop_y, 'width' => $image->crop_width, 'height' => $image->crop_height, 'rotation' => $image->goc_xoay];
    }
}
