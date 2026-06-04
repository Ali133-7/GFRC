<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\LogoService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_endpoint_requires_confirmation(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/v1/system/reset', [])
            ->assertStatus(422);
    }

    public function test_reset_endpoint_requires_delete_confirmation(): void
    {
        $this->actingAsAdmin()
            ->postJson('/api/v1/system/reset', ['confirmation' => 'WRONG'])
            ->assertStatus(422);
    }

    public function test_reset_endpoint_rejects_non_admin(): void
    {
        $this->actingAsCashier()
            ->postJson('/api/v1/system/reset', ['confirmation' => 'DELETE'])
            ->assertForbidden();
    }

    public function test_admin_cannot_be_deleted_via_model(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('حذف حساب الأدمن غير مسموح به');

        $this->admin->delete();
    }

    public function test_admin_cannot_be_force_deleted(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->admin->forceDelete();
    }

    public function test_non_admin_users_can_be_deleted(): void
    {
        $cashier = $this->cashier;
        $cashier->delete();

        $this->assertSoftDeleted('users', ['id' => $cashier->id]);
    }

    public function test_admin_protection_triggers_on_delete_attempt(): void
    {
        $admin = $this->admin;
        
        $caught = false;
        try {
            $admin->delete();
        } catch (\RuntimeException $e) {
            $caught = true;
            $this->assertStringContainsString('حذف حساب الأدمن غير مسموح به', $e->getMessage());
        }
        
        $this->assertTrue($caught, 'Expected RuntimeException was not thrown');
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_logo_upload_requires_authentication(): void
    {
        $file = UploadedFile::fake()->create('logo.png', 100, 'image/png');

        $this->postJson('/api/v1/system/logo', ['logo' => $file])
            ->assertUnauthorized();
    }

    public function test_logo_upload_requires_admin_role(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('logo.png', 100, 'image/png');

        $this->actingAsCashier()
            ->post('/api/v1/system/logo', ['logo' => $file])
            ->assertForbidden();
    }

    public function test_logo_upload_accepts_png(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('logo.png', 100, 'image/png');

        $response = $this->actingAsAdmin()
            ->post('/api/v1/system/logo', ['logo' => $file], ['Accept' => 'application/json']);

        $response->assertOk();

        $this->assertDatabaseHas('settings', [
            'key' => 'system_logo',
        ]);
    }

    public function test_logo_upload_accepts_jpeg(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('logo.jpg', 100, 'image/jpeg');

        $this->actingAsAdmin()
            ->post('/api/v1/system/logo', ['logo' => $file], ['Accept' => 'application/json'])
            ->assertOk();
    }

    public function test_logo_upload_accepts_webp(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('logo.webp', 100, 'image/webp');

        $this->actingAsAdmin()
            ->post('/api/v1/system/logo', ['logo' => $file], ['Accept' => 'application/json'])
            ->assertOk();
    }

    public function test_logo_upload_rejects_oversized_file(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('logo.png', 100, 'image/png')->size(3000);

        $this->actingAsAdmin()
            ->post('/api/v1/system/logo', ['logo' => $file], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_logo_upload_rejects_invalid_mime(): void
    {
        Storage::fake('public');

        $file = UploadedFile::fake()->create('malicious.exe', 100, 'application/x-executable');

        $this->actingAsAdmin()
            ->post('/api/v1/system/logo', ['logo' => $file], ['Accept' => 'application/json'])
            ->assertStatus(422);
    }

    public function test_logo_upload_replaces_old_logo(): void
    {
        Storage::fake('public');

        $file1 = UploadedFile::fake()->create('logo1.png', 100, 'image/png');
        $this->actingAsAdmin()
            ->post('/api/v1/system/logo', ['logo' => $file1], ['Accept' => 'application/json'])
            ->assertOk();

        $setting = Setting::where('key', 'system_logo')->first();
        $this->assertNotNull($setting);
        $oldUrl = $setting->value;

        $file2 = UploadedFile::fake()->create('logo2.png', 100, 'image/png');
        $this->actingAsAdmin()
            ->post('/api/v1/system/logo', ['logo' => $file2], ['Accept' => 'application/json'])
            ->assertOk();

        $newUrl = Setting::get('system_logo');
        $this->assertNotEquals($oldUrl, $newUrl);
    }

    public function test_logo_service_validates_file_size(): void
    {
        $service = new LogoService();

        $file = UploadedFile::fake()->create('logo.png', 3000, 'image/png');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('حجم الملف يتجاوز الحد الأقصى (2MB)');

        $service->uploadLogo($file);
    }

    public function test_logo_service_validates_mime_type(): void
    {
        $service = new LogoService();

        $file = UploadedFile::fake()->create('script.php', 100, 'application/x-php');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('نوع الملف غير مدعوم');

        $service->uploadLogo($file);
    }

    public function test_logo_service_prevents_path_traversal(): void
    {
        $service = new LogoService();

        Storage::fake('public');
        $file = UploadedFile::fake()->create('logo.png', 100, 'image/png');
        $url = $service->uploadLogo($file);

        $result = $service->deleteLogo($url . '/../../secret');
        $this->assertFalse($result);
    }

    public function test_logo_service_deletes_old_file(): void
    {
        Storage::fake('public');
        $service = new LogoService();

        $file = UploadedFile::fake()->create('logo.png', 100, 'image/png');
        $url = $service->uploadLogo($file);

        $this->assertTrue($service->deleteLogo($url));

        $relativePath = $this->urlToRelativePath($url);
        $this->assertFalse(Storage::disk('public')->exists($relativePath));
    }

    protected function urlToRelativePath(string $url): string
    {
        $baseUrl = Storage::disk('public')->url('');
        return ltrim(substr($url, strlen($baseUrl)), '/');
    }
}
