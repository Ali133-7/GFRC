<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class BackupController extends ApiController
{
    protected string $backupPath;

    public function __construct()
    {
        $this->backupPath = storage_path('app/backups');
        if (!File::exists($this->backupPath)) {
            File::makeDirectory($this->backupPath, 0755, true);
        }
    }

    public function index(): JsonResponse
    {
        $this->authorize('manage-settings', \App\Models\Setting::class);
        $files = File::files($this->backupPath);
        $backups = collect($files)
            ->filter(fn($f) => !str_ends_with($f->getFilename(), '.hmac') && !str_ends_with($f->getFilename(), '.crc'))
            ->sortByDesc(fn($f) => $f->getMTime())
            ->map(fn($f) => [
                'filename' => $f->getFilename(),
                'size' => $this->formatBytes($f->getSize()),
                'size_bytes' => $f->getSize(),
                'date' => date('Y-m-d H:i:s', $f->getMTime()),
                'encrypted' => str_ends_with($f->getFilename(), '.enc'),
                'verified' => BackupService::hasIntegrity($f->getPathname()),
            ])
            ->values();

        return $this->success($backups);
    }

    public function create(): JsonResponse
    {
        $this->authorize('manage-settings', \App\Models\Setting::class);
        $timestamp = now()->format('Y-m-d_His');
        $driver = config('database.default');
        $baseFile = $this->backupPath . "/gfrc_backup_{$timestamp}";

        try {
            if ($driver === 'sqlite') {
                $source = database_path('database.sqlite');
                $plainFile = "$baseFile.sqlite";

                // Lock and copy SQLite database atomically
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
                    return $this->error('فشل pg_dump: ' . $result->errorOutput(), [], 'BACKUP_FAILED');
                }
            }

            if (!File::exists($plainFile)) {
                return $this->error('فشل إنشاء النسخة الاحتياطية', [], 'BACKUP_FAILED');
            }

            $content = File::get($plainFile);

            // Write integrity files against the PLAIN file
            BackupService::writeIntegrity($plainFile, $content);

            // Encrypt and replace
            $encFile = "$plainFile.enc";
            File::put($encFile, BackupService::encrypt($content));
            File::delete($plainFile);

            // Rotate old backups (keep last 30)
            $this->rotateBackups(30);

            activity()
                ->causedBy(auth()->user())
                ->withProperties(['filename' => basename($encFile), 'size' => File::size($encFile)])
                ->event('backup_created')
                ->tap(function ($activity) {
                    $activity->ip_address = request()->ip();
                    $activity->user_agent = request()->userAgent();
                })
                ->log('backup_created');

            return $this->success([
                'filename' => basename($encFile),
                'size' => $this->formatBytes(File::size($encFile)),
                'hash' => BackupService::hmac($content),
                'crc' => BackupService::crc($content),
            ], 'تم إنشاء النسخة الاحتياطية المشفرة بنجاح');

        } catch (\Exception $e) {
            // Cleanup on failure
            if (isset($plainFile) && File::exists($plainFile)) {
                File::delete($plainFile);
            }
            if (isset($encFile) && File::exists($encFile)) {
                File::delete($encFile);
            }
            BackupService::deleteIntegrity($plainFile ?? '');

            return $this->error('فشل إنشاء النسخة: ' . $e->getMessage(), [], 'BACKUP_FAILED');
        }
    }

    public function download(string $filename): Response
    {
        $this->authorize('manage-settings', \App\Models\Setting::class);
        $filepath = $this->backupPath . '/' . $filename;
        if (!File::exists($filepath)) {
            abort(404, 'الملف غير موجود');
        }
        return response()->download($filepath);
    }

    public function restore(string $filename): JsonResponse
    {
        $this->authorize('manage-settings', \App\Models\Setting::class);
        $filepath = $this->backupPath . '/' . $filename;

        if (!File::exists($filepath)) {
            return $this->error('الملف غير موجود', [], 'NOT_FOUND');
        }

        $isEncrypted = str_ends_with($filepath, '.enc');
        if ($isEncrypted) {
            $encoded = File::get($filepath);
            $content = BackupService::decrypt($encoded);
            if ($content === false) {
                return $this->error('فشل فك التشفير', [], 'DECRYPT_FAILED');
            }
        } else {
            $content = File::get($filepath);
        }

        // Verify integrity
        $baseFile = BackupService::basePath($filepath);
        $hmacFile = $baseFile . '.hmac';
        if (File::exists($hmacFile)) {
            $expectedHash = trim(File::get($hmacFile));
            if (!BackupService::verifyHmac($content, $expectedHash)) {
                return $this->error('فشل التحقق من سلامة الملف — لا يمكن الاستعادة', [], 'INTEGRITY_FAILED');
            }
        }

        $driver = config('database.default');

        try {
            if ($driver === 'sqlite') {
                $target = database_path('database.sqlite');
                $tempFile = $target . '.restore_' . time();
                File::put($tempFile, $content);

                // Atomic swap with safety copy
                $safetyCopy = $target . '.before_restore_' . now()->format('Ymd_His');
                File::copy($target, $safetyCopy);
                File::move($tempFile, $target);
            } else {
                $host = config('database.connections.pgsql.host');
                $port = config('database.connections.pgsql.port');
                $database = config('database.connections.pgsql.database');
                $user = config('database.connections.pgsql.username');
                $password = config('database.connections.pgsql.password');

                $tempFile = storage_path('app/restore_' . time() . '.sql');
                File::put($tempFile, $content);

                $result = Process::run("PGPASSWORD=$password psql -h $host -p $port -U $user -d $database -f $tempFile");
                File::delete($tempFile);

                if (!$result->successful()) {
                    return $this->error('فشل psql: ' . $result->errorOutput(), [], 'RESTORE_FAILED');
                }
            }

            // Ensure admin has super_admin after restore
            $adminUser = \App\Models\User::where('username', 'admin')->first();
            if ($adminUser && !$adminUser->hasRole('super_admin')) {
                $adminUser->assignRole('super_admin');
            }
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();

            return $this->success([], 'تم استعادة قاعدة البيانات بنجاح');
        } catch (\Exception $e) {
            return $this->error('فشل الاستعادة: ' . $e->getMessage(), [], 'RESTORE_FAILED');
        }
    }

    public function destroy(string $filename): JsonResponse
    {
        $this->authorize('manage-settings', \App\Models\Setting::class);
        $filepath = $this->backupPath . '/' . $filename;
        if (!File::exists($filepath)) {
            return $this->error('الملف غير موجود', [], 'NOT_FOUND');
        }

        File::delete($filepath);
        BackupService::deleteIntegrity($filepath);

        return $this->success([], 'تم حذف النسخة الاحتياطية بنجاح');
    }

    protected function rotateBackups(int $keep): void
    {
        $files = collect(File::files($this->backupPath))
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
