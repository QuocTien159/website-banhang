<?php

namespace App\Services;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class CloudinaryMediaService
{
    public function upload(UploadedFile $file, string $purpose, string $userId): array
    {
        if (!$this->enabled()) {
            $path = $file->store($purpose, 'public');
            $url = Storage::disk('public')->url($path);
            return [
                'url' => $url,
                ...$this->urls($url, 'local'),
                'path' => $path,
                'provider' => 'local',
                'public_id' => null,
                'width' => $file->dimensions()[0] ?? null,
                'height' => $file->dimensions()[1] ?? null,
                'bytes' => $file->getSize(),
                'format' => $file->extension(),
                'upload_token' => null,
            ];
        }

        $timestamp = now()->timestamp;
        $folder = 'tienprosport/'.$purpose;
        $params = array_filter([
            'folder' => $folder,
            'timestamp' => $timestamp,
            'upload_preset' => config('services.cloudinary.upload_preset'),
        ], fn ($value) => filled($value));
        ksort($params);
        $signature = sha1(urldecode(http_build_query($params)).config('services.cloudinary.api_secret'));

        $response = Http::withOptions($this->httpOptions())
            ->attach('file', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
            ->post($this->uploadUrl(), $params + [
                'api_key' => config('services.cloudinary.api_key'),
                'signature' => $signature,
            ]);
        $response->throw();
        $asset = $response->json();

        if (empty($asset['secure_url']) || empty($asset['public_id'])) {
            throw new RuntimeException('Cloudinary không trả về thông tin ảnh hợp lệ.');
        }

        $payload = [
            'user_id' => $userId,
            'purpose' => $purpose,
            'provider' => 'cloudinary',
            'url' => $asset['secure_url'],
            'public_id' => $asset['public_id'],
            'width' => $asset['width'] ?? null,
            'height' => $asset['height'] ?? null,
            'bytes' => $asset['bytes'] ?? null,
            'format' => $asset['format'] ?? null,
            'expires_at' => now()->addHours(2)->timestamp,
        ];

        return [
            'url' => $payload['url'],
            ...$this->urls($payload['url'], 'cloudinary'),
            'provider' => 'cloudinary',
            'public_id' => $payload['public_id'],
            'width' => $payload['width'],
            'height' => $payload['height'],
            'bytes' => $payload['bytes'],
            'format' => $payload['format'],
            'upload_token' => Crypt::encryptString(json_encode($payload)),
        ];
    }

    public function verifiedUpload(?string $token, string $purpose, string $userId): array
    {
        if (!$token) throw new RuntimeException('Ảnh mới phải được tải lên qua hệ thống.');
        try {
            $asset = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            throw new RuntimeException('Thông tin ảnh tải lên không hợp lệ.');
        }

        if (($asset['user_id'] ?? null) !== $userId || ($asset['purpose'] ?? null) !== $purpose || ($asset['expires_at'] ?? 0) < now()->timestamp) {
            throw new RuntimeException('Phiên tải ảnh đã hết hạn hoặc không có quyền sử dụng.');
        }

        return $asset;
    }

    public function delete(?string $provider, ?string $publicId, ?string $path = null): void
    {
        if ($provider === 'cloudinary' && $publicId && $this->enabled()) {
            $timestamp = now()->timestamp;
            $signature = sha1("public_id={$publicId}&timestamp={$timestamp}".config('services.cloudinary.api_secret'));
            Http::withOptions($this->httpOptions())->asForm()->post($this->destroyUrl(), [
                'public_id' => $publicId,
                'timestamp' => $timestamp,
                'api_key' => config('services.cloudinary.api_key'),
                'signature' => $signature,
            ]);
            return;
        }

        if ($path) Storage::disk('public')->delete($path);
    }

    public function url(?string $url, ?string $provider, string $context, array $crop = []): ?string
    {
        if (!$url || $provider !== 'cloudinary') return $url;
        $cropTransformation = $this->cropTransformation($crop);
        $hasCrop = $cropTransformation !== null;
        $transformation = match ($context) {
            // Thumbnails have a fixed visual slot. Uploaded product images are at least 800px.
            'thumbnail' => 'f_auto,q_auto:good,c_fill,g_auto,w_240,h_240',
            // c_limit only downscales and preserves the source aspect ratio.
            'list' => $hasCrop ? 'f_auto,q_auto:good,c_fill,w_640,h_640' : 'f_auto,q_auto:good,c_limit,w_640,h_640',
            'banner' => $hasCrop ? 'f_auto,q_auto:good,c_fill,w_1600,h_900' : 'f_auto,q_auto:good,c_limit,w_1600',
            'announcement' => 'f_auto,q_auto:good,c_limit,w_1600',
            default => 'f_auto,q_auto:good,c_limit,w_1600',
        };
        $segments = array_filter([$cropTransformation, $transformation]);
        return str_replace('/image/upload/', '/image/upload/'.implode('/', $segments).'/', $url);
    }

    /**
     * Keep the persisted Cloudinary secure_url untouched and expose derivatives per UI context.
     */
    public function urls(?string $url, ?string $provider, array $crop = []): array
    {
        return [
            'original_url' => $url,
            'thumbnail_url' => $this->url($url, $provider, 'thumbnail', $crop),
            'list_url' => $this->url($url, $provider, 'list', $crop),
            'detail_url' => $this->url($url, $provider, 'detail', $crop),
            'announcement_url' => $this->url($url, $provider, 'announcement', $crop),
            'banner_url' => $this->url($url, $provider, 'banner', $crop),
        ];
    }

    private function cropTransformation(array $crop): ?string
    {
        $width = (int) ($crop['width'] ?? $crop['crop_width'] ?? 0);
        $height = (int) ($crop['height'] ?? $crop['crop_height'] ?? 0);
        if ($width < 1 || $height < 1) return null;

        $x = max(0, (int) ($crop['x'] ?? $crop['crop_x'] ?? 0));
        $y = max(0, (int) ($crop['y'] ?? $crop['crop_y'] ?? 0));
        $rotation = (float) ($crop['rotation'] ?? $crop['goc_xoay'] ?? 0);
        $parts = ["c_crop,x_{$x},y_{$y},w_{$width},h_{$height}"];
        if ($rotation != 0.0) $parts[] = 'a_'.rtrim(rtrim(number_format($rotation, 2, '.', ''), '0'), '.');
        return implode('/', $parts);
    }

    private function enabled(): bool
    {
        return filled(config('services.cloudinary.cloud_name'))
            && filled(config('services.cloudinary.api_key'))
            && filled(config('services.cloudinary.api_secret'));
    }

    private function uploadUrl(): string
    {
        return 'https://api.cloudinary.com/v1_1/'.config('services.cloudinary.cloud_name').'/image/upload';
    }

    private function httpOptions(): array
    {
        $caBundle = config('services.cloudinary.ca_bundle');
        return $caBundle && is_file($caBundle) ? ['verify' => $caBundle] : [];
    }

    private function destroyUrl(): string
    {
        return 'https://api.cloudinary.com/v1_1/'.config('services.cloudinary.cloud_name').'/image/destroy';
    }
}
