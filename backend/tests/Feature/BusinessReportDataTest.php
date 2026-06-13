<?php

namespace Tests\Feature;

use App\Models\Register;
use App\Models\RegisterField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BusinessReportDataTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function makeRegister(array $overrides = []): Register
    {
        return Register::create(array_merge([
            'id' => (string) Str::uuid(),
            'code' => 'REG_'.Str::random(4),
            'name_ar' => 'سجل تجريبي',
            'name_en' => 'Test Register',
            'fiscal_year' => now()->year,
        ], $overrides));
    }

    private function makeField(Register $register, array $overrides = []): RegisterField
    {
        return RegisterField::create(array_merge([
            'id' => (string) Str::uuid(),
            'register_id' => $register->id,
            'name' => 'field_'.Str::random(4),
            'label_ar' => 'حقل تجريبي',
            'label_en' => 'Test Field',
            'field_type' => 'text',
        ], $overrides));
    }

    private function makeUser(): User
    {
        return User::create([
            'id' => (string) Str::uuid(),
            'name' => 'Test User',
            'username' => 'test_'.Str::random(4),
            'email' => 'test_'.Str::random(4).'@example.com',
            'password' => bcrypt('password'),
        ]);
    }

    public function test_business_registers_endpoint_returns_registers(): void
    {
        $user = $this->makeUser();
        $this->makeRegister();
        $this->makeRegister();

        $response = $this->actingAs($user)->getJson('/api/v1/reports/business-registers');

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonStructure([
                'data' => [
                    'registers' => [
                        '*' => ['id', 'type', 'code', 'name', 'record_count', 'table_alias'],
                    ],
                ],
            ]);
    }

    public function test_business_fields_endpoint_returns_fields_for_register(): void
    {
        $user = $this->makeUser();
        $register = $this->makeRegister();
        $this->makeField($register);
        $this->makeField($register);
        $this->makeField($register);

        $response = $this->actingAs($user)->postJson('/api/v1/reports/business-fields', [
            'register_ids' => [$register->id],
        ]);

        $response->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(3, 'data.fields');
    }

    public function test_business_relationships_endpoint_requires_two_registers(): void
    {
        $user = $this->makeUser();
        $register = $this->makeRegister();

        $response = $this->actingAs($user)->postJson('/api/v1/reports/business-relationships', [
            'register_ids' => [$register->id],
        ]);

        $response->assertStatus(422);
    }
}
