<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class BackupService
{
    /**
     * Get the raw encryption key from APP_KEY (strips base64: prefix).
     */
    public static function encryptionKey(): string
    {
        $key = config('app.key');
        if (str_starts_with($key, 'base64:')) {
            $key = base64_decode(Str::after($key, 'base64:'));
        }
        return hash('sha256', $key, true);
    }

    /**
     * Encrypt file content with AES-256-CBC.
     */
    public static function encrypt(string $content): string
    {
        $cipher = 'AES-256-CBC';
        $iv = random_bytes(openssl_cipher_iv_length($cipher));
        $encrypted = openssl_encrypt($content, $cipher, self::encryptionKey(), OPENSSL_RAW_DATA, $iv);
        return base64_encode($iv . $encrypted);
    }

    /**
     * Decrypt file content with AES-256-CBC.
     */
    public static function decrypt(string $encoded): string|false
    {
        $cipher = 'AES-256-CBC';
        $raw = base64_decode($encoded);
        $ivLength = openssl_cipher_iv_length($cipher);
        if (strlen($raw) < $ivLength) {
            return false;
        }
        $iv = substr($raw, 0, $ivLength);
        $encrypted = substr($raw, $ivLength);
        return openssl_decrypt($encrypted, $cipher, self::encryptionKey(), OPENSSL_RAW_DATA, $iv);
    }

    /**
     * Generate HMAC-SHA256 for content.
     */
    public static function hmac(string $content): string
    {
        return hash_hmac('sha256', $content, config('app.key'));
    }

    /**
     * Verify HMAC-SHA256.
     */
    public static function verifyHmac(string $content, string $expected): bool
    {
        return hash_equals($expected, self::hmac($content));
    }

    /**
     * Generate CRC-32 for content.
     */
    public static function crc(string $content): string
    {
        return hash('crc32b', $content);
    }

    /**
     * Get base path without .enc extension.
     */
    public static function basePath(string $filepath): string
    {
        return str_ends_with($filepath, '.enc') ? substr($filepath, 0, -4) : $filepath;
    }

    /**
     * Write integrity files (HMAC + CRC) for a given base path.
     */
    public static function writeIntegrity(string $basePath, string $content): void
    {
        File::put("$basePath.hmac", self::hmac($content));
        File::put("$basePath.crc", self::crc($content));
    }

    /**
     * Delete integrity files for a given filepath.
     */
    public static function deleteIntegrity(string $filepath): void
    {
        $base = self::basePath($filepath);
        File::delete("$base.hmac");
        File::delete("$base.crc");
    }

    /**
     * Check if integrity files exist for a given filepath.
     */
    public static function hasIntegrity(string $filepath): bool
    {
        $base = self::basePath($filepath);
        return File::exists("$base.hmac");
    }
}
