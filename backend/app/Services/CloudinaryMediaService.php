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
            'expires_at' => now()->addHours(2)->timestamp,
        ];

        return [
            'url' => $payload['url'],
            ...$this->urls($payload['url'], 'cloudinary'),
            'provider' => 'cloudinary',
            'public_id' => $payload['public_id'],
            'width' => $payload['width'],
            'height' => $payload['height'],
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

    public function url(?string $url, ?string $provider, string $context): ?string
    {
        if (!$url || $provider !== 'cloudinary') return $url;
        $transformation = match ($context) {
            // Thumbnails have a fixed visual slot. Uploaded product images are at least 800px.
            'thumbnail' => 'f_auto,q_auto:good,c_fill,g_auto,w_240,h_240',
            // c_limit only downscales and preserves the source aspect ratio.
            'list' => 'f_auto,q_auto:good,c_limit,w_640,h_640',
            'announcement' => 'f_auto,q_auto:good,c_limit,w_1600',
            default => 'f_auto,q_auto:good,c_limit,w_1600',
        };
        return str_replace('/image/upload/', '/image/upload/'.$transformation.'/', $url);
    }

    /**
     * Keep the persisted Cloudinary secure_url untouched and expose derivatives per UI context.
     */
    public function urls(?string $url, ?string $provider): array
    {
        return [
            'original_url' => $url,
            'thumbnail_url' => $this->url($url, $provider, 'thumbnail'),
            'list_url' => $this->url($url, $provider, 'list'),
            'detail_url' => $this->url($url, $provider, 'detail'),
            'announcement_url' => $this->url($url, $provider, 'announcement'),
        ];
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
