<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class BackupCreate extends Command
{
    protected $signature = 'backup:create
                            {--encrypt : تشفير النسخة الاحتياطية باستخدام AES-256-CBC}
                            {--path= : مسار حفظ النسخة الاحتياطية}
                            {--keep=30 : عدد النسخ المحتفظ بها}';

    protected $description = 'إنشاء نسخة احتياطية مشفرة من قاعدة البيانات مع التحقق من السلامة';

    public function handle(): int
    {
        $backupPath = $this->option('path') ?? storage_path('app/backups');
        $keep = (int) ($this->option('keep') ?? 30);

        if (!File::exists($backupPath)) {
            File::makeDirectory($backupPath, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $driver = config('database.default');
        $baseFile = "$backupPath/gfrc_backup_$timestamp";

        try {
            if ($driver === 'sqlite') {
                $source = database_path('database.sqlite');
                $plainFile = "$baseFile.sqlite";
                DB::statement('PRAGMA wal_checkpoint(TRUNCATE)');
                File::copy($source, $plainFile);
            } else {
                $plainFile = "$baseFile.sql";
                $host = config('database.connections.pgsql.host');
                $port = config('database.connections.pgsql.port');
                $database = config('database.connections.pgsql.database');
                $user = config('database.connections.pgsql.username');
                $password = config('database.connections.pgsql.password');

                $result = Process::run("PGPASSWORD=$password pg_dump -h $host -p $port -U $user -d $database -F p -f $plainFile");
                if (!$result->successful()) {
                    $this->error('فشل pg_dump: ' . $result->errorOutput());
                    return self::FAILURE;
                }
            }

            if (!File::exists($plainFile)) {
                $this->error('فشل إنشاء النسخة الاحتياطية');
                return self::FAILURE;
            }

            $content = File::get($plainFile);
            BackupService::writeIntegrity($plainFile, $content);

            if ($this->option('encrypt')) {
                $encFile = "$plainFile.enc";
                File::put($encFile, BackupService::encrypt($content));
                File::delete($plainFile);
                $plainFile = $encFile;
            }

            // Rotate
            $this->rotateBackups($backupPath, $keep);

            $size = $this->formatBytes(File::size($plainFile));
            $this->info("✅ تم إنشاء النسخة: " . basename($plainFile) . " ($size)");

            return self::SUCCESS;

        } catch (\Exception $e) {
            if (isset($plainFile) && File::exists($plainFile)) {
                File::delete($plainFile);
            }
            BackupService::deleteIntegrity($plainFile ?? '');
            $this->error('فشل: ' . $e->getMessage());
            return self::FAILURE;
        }
    }

    protected function rotateBackups(string $path, int $keep): void
    {
        $files = collect(File::files($path))
            ->filter(fn($f) => !str_ends_with($f->getFilename(), '.hmac') && !str_ends_with($f->getFilename(), '.crc'))
            ->sortByDesc(fn($f) => $f->getMTime())
            ->skip($keep);

        foreach ($files as $file) {
            File::delete($file->getPathname());
            BackupService::deleteIntegrity($file->getPathname());
        }
    }

    protected function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $unitIndex = 0;
        while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
            $bytes /= 1024;
            $unitIndex++;
        }
        return round($bytes, 2) . ' ' . $units[$unitIndex];
    }
}
