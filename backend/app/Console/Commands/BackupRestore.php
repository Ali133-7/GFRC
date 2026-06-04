<?php

namespace App\Console\Commands;

use App\Services\BackupService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;

class BackupRestore extends Command
{
    protected $signature = 'backup:restore
                            {file : مسار ملف النسخة الاحتياطية المشفرة}
                            {--force : تجاوز التأكيد}';

    protected $description = 'استعادة قاعدة البيانات من نسخة احتياطية مشفرة';

    public function handle(): int
    {
        $file = $this->argument('file');

        if (!File::exists($file)) {
            $this->error('الملف غير موجود: ' . $file);
            return self::FAILURE;
        }

        if (!$this->option('force')) {
            $this->warn('⚠️ تحذير: سيتم استبدال قاعدة البيانات الحالية بالكامل!');
            if (!$this->confirm('هل أنت متأكد من الاستمرار؟', false)) {
                $this->info('تم إلغاء الاستعادة.');
                return self::SUCCESS;
            }
        }

        // Verify integrity first
        $isEncrypted = str_ends_with($file, '.enc');
        if ($isEncrypted) {
            $encoded = File::get($file);
            $content = BackupService::decrypt($encoded);
            if ($content === false) {
                $this->error('❌ فشل فك التشفير — المفتاح غير صحيح أو الملف تالف');
                return self::FAILURE;
            }
        } else {
            $content = File::get($file);
        }

        $baseFile = BackupService::basePath($file);
        $hmacFile = $baseFile . '.hmac';
        if (File::exists($hmacFile)) {
            $expectedHash = trim(File::get($hmacFile));
            if (!BackupService::verifyHmac($content, $expectedHash)) {
                $this->error('❌ فشل التحقق من HMAC — الملف قد يكون معدلاً');
                return self::FAILURE;
            }
            $this->info('✅ التحقق من HMAC ناجح');
        }

        $driver = config('database.default');

        try {
            if ($driver === 'sqlite') {
                $target = database_path('database.sqlite');
                // Create temporary restore file
                $tempFile = $target . '.restore_' . time();
                File::put($tempFile, $content);

                // Swap atomically
                $backupOriginal = $target . '.before_restore_' . time();
                File::copy($target, $backupOriginal);
                File::move($tempFile, $target);

                $this->info("✅ تم استعادة قاعدة البيانات SQLite");
                $this->info("📦 النسخة الأصلية محفوظة في: $backupOriginal");
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
                    $this->error('❌ فشل psql: ' . $result->errorOutput());
                    return self::FAILURE;
                }

                $this->info("✅ تم استعادة قاعدة البيانات PostgreSQL");
            }

            // Ensure permissions and roles are properly initialized after restore
            $this->info('🔄 جاري إعادة تهيئة الأدوار والأذونات...');
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\RolesSeeder']);
            $this->call('db:seed', ['--class' => 'Database\\Seeders\\AdminUserSeeder']);
            
            // Clear permission cache
            $this->info('🧹 جاري مسح cache الأذونات...');
            app()->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
            
            $this->info('✅ تم إعادة تهيئة جميع الأدوار والأذونات بنجاح!');

            return self::SUCCESS;
        } catch (\Exception $e) {
            $this->error('❌ فشل الاستعادة: ' . $e->getMessage());
            return self::FAILURE;
        }
    }
}
