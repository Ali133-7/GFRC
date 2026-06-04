<?php

namespace App\Services;

use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class LogoService
{
    protected const MAX_SIZE = 2 * 1024 * 1024; // 2MB

    protected const ALLOWED_MIME_TYPES = [
        'image/png' => 'png',
        'image/jpeg' => 'jpg',
        'image/webp' => 'webp',
    ];

    public function uploadLogo($file): string
    {
        $this->validateFile($file);

        $extension = $this->getSafeExtension($file);
        $filename = 'logo_' . Str::uuid()->toString() . '.' . $extension;
        $path = 'logos/' . $filename;

        Storage::disk('public')->put($path, $file->getContent(), 'public');

        return Storage::disk('public')->url($path);
    }

    public function deleteLogo(string $url): bool
    {
        $relativePath = $this->urlToRelativePath($url);
        if ($relativePath && Storage::disk('public')->exists($relativePath)) {
            return Storage::disk('public')->delete($relativePath);
        }
        return false;
    }

    protected function validateFile($file): void
    {
        if ($file->getSize() > self::MAX_SIZE) {
            throw new \InvalidArgumentException('حجم الملف يتجاوز الحد الأقصى (2MB)');
        }

        $mimeType = $file->getMimeType();
        if (!isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new \InvalidArgumentException('نوع الملف غير مدعوم. الأنواع المسموحة: PNG, JPG, WEBP');
        }

        $filename = $file->getClientOriginalName();
        if (preg_match('/[\/\\\\]/', $filename)) {
            throw new \InvalidArgumentException('اسم الملف غير صالح');
        }
    }

    protected function getSafeExtension($file): string
    {
        $mimeType = $file->getMimeType();
        return self::ALLOWED_MIME_TYPES[$mimeType] ?? 'png';
    }

    protected function urlToRelativePath(string $url): ?string
    {
        $baseUrl = Storage::disk('public')->url('');
        if (str_starts_with($url, $baseUrl)) {
            $relativePath = substr($url, strlen($baseUrl));
            $relativePath = ltrim($relativePath, '/');

            if (str_contains($relativePath, '..') || str_starts_with($relativePath, '/')) {
                return null;
            }

            return $relativePath;
        }
        return null;
    }
}
