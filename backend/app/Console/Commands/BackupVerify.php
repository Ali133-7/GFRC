<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class BackupVerify extends Command
{
    protected $signature = 'backup:verify {file : مسار ملف النسخة الاحتياطية}';

    protected $description = 'التحقق من سلامة نسخة احتياطية (HMAC + CRC-32)';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (!File::exists($file)) {
            $this->error('الملف غير موجود: ' . $file);
            return self::FAILURE;
        }

        $isEncrypted = str_ends_with($file, '.enc');

        if ($isEncrypted) {
            $this->info('🔓 فك تشفير الملف...');
            $encoded = File::get($file);
            $content = BackupService::decrypt($encoded);
            if ($content === false) {
                $this->error('❌ فشل فك التشفير — المفتاح غير صحيح أو الملف تالف');
                return self::FAILURE;
            }
            $baseFile = BackupService::basePath($file);
        } else {
            $content = File::get($file);
            $baseFile = $file;
        }

        // Verify HMAC
        $hmacFile = $baseFile . '.hmac';
        if (File::exists($hmacFile)) {
            $expectedHash = trim(File::get($hmacFile));
            if (!BackupService::verifyHmac($content, $expectedHash)) {
                $this->error('❌ فشل التحقق من HMAC — الملف قد يكون معدلاً');
                return self::FAILURE;
            }
            $this->info('✅ HMAC صحيح');
        } else {
            $this->warn('⚠️ ملف HMAC غير موجود');
        }

        // Verify CRC-32
        $crcFile = $baseFile . '.crc';
        if (File::exists($crcFile)) {
            $expectedCrc = trim(File::get($crcFile));
            $actualCrc = BackupService::crc($content);
            if ($expectedCrc !== $actualCrc) {
                $this->error('❌ فشل التحقق من CRC-32 — الملف قد يكون تالف');
                return self::FAILURE;
            }
            $this->info('✅ CRC-32 صحيح');
        } else {
            $this->warn('⚠️ ملف CRC غير موجود');
        }

        $this->info('✅ النسخة الاحتياطية سليمة ومؤكدة');
        return self::SUCCESS;
    }
}
