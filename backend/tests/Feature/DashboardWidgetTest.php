<?php

namespace Tests\Feature;

use App\Models\DashboardLayout;
use App\Models\DashboardLayoutWidget;
use App\Models\Register;
use App\Models\RegisterField;
use App\Models\Permission;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardWidgetTest extends TestCase
{
    protected Register $receiptsRegister;
    protected RegisterField $amountField;
    protected RegisterField $customerField;

    protected function setUp(): void
    {
        parent::setUp();

        $this->receiptsRegister = Register::create([
            'code' => 'receipts',
            'name_ar' => 'سجل الإيصالات',
            'name_en' => 'Receipts Register',
            'fiscal_year' => 2026,
            'current_sequence' => 0,
            'created_by' => $this->admin->id,
            'is_active' => true,
        ]);

        $this->amountField = RegisterField::create([
            'register_id' => $this->receiptsRegister->id,
            'name' => 'amount',
            'label_ar' => 'المبلغ',
            'field_type' => 'number',
            'is_required' => true,
            'is_financial' => true,
            'sort_order' => 1,
        ]);

        $this->customerField = RegisterField::create([
            'register_id' => $this->receiptsRegister->id,
            'name' => 'customer_name',
            'label_ar' => 'اسم العميل',
            'field_type' => 'text',
            'is_required' => false,
            'is_financial' => false,
            'sort_order' => 2,
        ]);

        $this->grantReadPermission($this->admin, $this->receiptsRegister);
        $this->grantReadPermission($this->manager, $this->receiptsRegister);
    }

    protected function grantReadPermission($user, Register $register): void
    {
        $permissionName = "read-register-{$register->code}";
        $permission = Permission::firstOrCreate(['name' => $permissionName, 'guard_name' => 'api']);
        $user->givePermissionTo($permission);
    }

    protected function createRecord(array $data, ?string $createdAt = null): string
    {
        $id = (string) Str::uuid();
        DB::table('records')->insert([
            'id' => $id,
            'register_id' => $this->receiptsRegister->id,
            'record_number' => 'RCPT-' . rand(1000, 9999),
            'data' => json_encode($data),
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
            'created_at' => $createdAt ?? now(),
            'updated_at' => now(),
        ]);
        return $id;
    }

    public function test_default_layout_created_for_authenticated_user(): void
    {
        $response = $this->actingAsAdmin()->getJson('/api/v1/dashboard/layout');

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.user_id', $this->admin->id)
            ->assertJsonPath('data.is_default', true);

        $widgets = $response->json('data.widgets');
        $this->assertCount(5, $widgets);
        $this->assertEquals('stat_card', $widgets[0]['widget_type']);
        $this->assertEquals('table', $widgets[4]['widget_type']);
    }

    public function test_save_layout_replaces_widgets(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/dashboard/layout');

        $response = $this->actingAsAdmin()->postJson('/api/v1/dashboard/layout', [
            'name' => 'Custom Layout',
            'widgets' => [
                [
                    'widget_type' => 'stat_card',
                    'title' => ['ar' => 'اختبار', 'en' => 'Test'],
                    'data_source' => [
                        'register_id' => $this->receiptsRegister->id,
                        'aggregation' => 'sum',
                        'field' => 'amount',
                    ],
                    'display_config' => ['format' => 'currency'],
                    'position_x' => 0,
                    'position_y' => 0,
                    'width' => 3,
                    'height' => 1,
                    'sort_order' => 0,
                    'register_id' => $this->receiptsRegister->id,
                ],
            ],
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.name', 'Custom Layout')
            ->assertJsonCount(1, 'data.widgets');

        $layout = DashboardLayout::where('user_id', $this->admin->id)->where('is_default', true)->first();
        $this->assertEquals('Custom Layout', $layout->name);
        $this->assertEquals(1, $layout->widgets()->count());
    }

    public function test_widget_data_resolver_stat_card_sum_uses_bc_math(): void
    {
        $this->createRecord(['amount' => '10.1111', 'customer_name' => 'A']);
        $this->createRecord(['amount' => '20.2222', 'customer_name' => 'B']);
        $this->createRecord(['amount' => '30.3333', 'customer_name' => 'C']);

        $widget = DashboardLayoutWidget::create([
            'layout_id' => DashboardLayout::create([
                'name' => 'Test',
                'user_id' => $this->admin->id,
                'is_default' => false,
                'created_by' => $this->admin->id,
                'updated_by' => $this->admin->id,
            ])->id,
            'widget_type' => 'stat_card',
            'title' => ['ar' => 'المجموع', 'en' => 'Sum'],
            'data_source' => [
                'register_id' => $this->receiptsRegister->id,
                'aggregation' => 'sum',
                'field' => 'amount',
            ],
            'register_id' => $this->receiptsRegister->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $response = $this->actingAsAdmin()->getJson("/api/v1/dashboard/widgets/{$widget->id}/data");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.value', '60.6666');
    }

    public function test_widget_data_resolver_count(): void
    {
        $this->createRecord(['amount' => '100', 'customer_name' => 'A']);
        $this->createRecord(['amount' => '200', 'customer_name' => 'B']);

        $widget = DashboardLayoutWidget::create([
            'layout_id' => DashboardLayout::create([
                'name' => 'Test',
                'user_id' => $this->admin->id,
                'is_default' => false,
                'created_by' => $this->admin->id,
                'updated_by' => $this->admin->id,
            ])->id,
            'widget_type' => 'stat_card',
            'title' => ['ar' => 'العدد', 'en' => 'Count'],
            'data_source' => [
                'register_id' => $this->receiptsRegister->id,
                'aggregation' => 'count',
            ],
            'register_id' => $this->receiptsRegister->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $response = $this->actingAsAdmin()->getJson("/api/v1/dashboard/widgets/{$widget->id}/data");

        $response->assertStatus(200)
            ->assertJsonPath('data.data.value', '2');
    }

    public function test_grouped_aggregation_by_period(): void
    {
        $this->createRecord(['amount' => '100', 'customer_name' => 'A'], '2026-01-15 10:00:00');
        $this->createRecord(['amount' => '200', 'customer_name' => 'B'], '2026-01-20 10:00:00');
        $this->createRecord(['amount' => '300', 'customer_name' => 'C'], '2026-02-10 10:00:00');

        $widget = DashboardLayoutWidget::create([
            'layout_id' => DashboardLayout::create([
                'name' => 'Test',
                'user_id' => $this->admin->id,
                'is_default' => false,
                'created_by' => $this->admin->id,
                'updated_by' => $this->admin->id,
            ])->id,
            'widget_type' => 'chart_bar',
            'title' => ['ar' => 'الرسم', 'en' => 'Chart'],
            'data_source' => [
                'register_id' => $this->receiptsRegister->id,
                'aggregation' => 'sum',
                'field' => 'amount',
                'group_by' => 'period',
                'period' => 'month',
            ],
            'register_id' => $this->receiptsRegister->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $response = $this->actingAsAdmin()->getJson("/api/v1/dashboard/widgets/{$widget->id}/data");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonCount(2, 'data.data.labels')
            ->assertJsonPath('data.data.values.0', '300.0000')
            ->assertJsonPath('data.data.values.1', '300.0000');
    }

    public function test_record_list_with_filters(): void
    {
        $this->createRecord(['amount' => '100', 'customer_name' => 'Alice']);
        $this->createRecord(['amount' => '200', 'customer_name' => 'Bob']);
        $this->createRecord(['amount' => '300', 'customer_name' => 'Alice']);

        $widget = DashboardLayoutWidget::create([
            'layout_id' => DashboardLayout::create([
                'name' => 'Test',
                'user_id' => $this->admin->id,
                'is_default' => false,
                'created_by' => $this->admin->id,
                'updated_by' => $this->admin->id,
            ])->id,
            'widget_type' => 'table',
            'title' => ['ar' => 'السجلات', 'en' => 'Records'],
            'data_source' => [
                'register_id' => $this->receiptsRegister->id,
                'fields' => ['amount', 'customer_name'],
                'filters' => ['customer_name' => 'Alice'],
                'per_page' => 10,
            ],
            'register_id' => $this->receiptsRegister->id,
            'created_by' => $this->admin->id,
            'updated_by' => $this->admin->id,
        ]);

        $response = $this->actingAsAdmin()->getJson("/api/v1/dashboard/widgets/{$widget->id}/data");

        $response->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.data.rows.0.customer_name', 'Alice')
            ->assertJsonPath('data.meta.pagination.total', 2);
    }

    public function test_get_available_registers_filters_by_permission(): void
    {
        $this->grantReadPermission($this->cashier, $this->receiptsRegister);

        $response = $this->actingAsCashier()->getJson('/api/v1/dashboard/registers');

        $response->assertStatus(200)
            ->assertJsonPath('success', true);

        $codes = collect($response->json('data'))->pluck('code')->all();
        $this->assertContains('receipts', $codes);
        $this->assertNotContains('REG-001', $codes);
    }

    public function test_get_widget_data_enforces_register_permission(): void
    {
        $this->createRecord(['amount' => '100', 'customer_name' => 'A']);

        $widget = DashboardLayoutWidget::create([
            'layout_id' => DashboardLayout::create([
                'name' => 'Test',
                'user_id' => $this->cashier->id,
                'is_default' => false,
                'created_by' => $this->cashier->id,
                'updated_by' => $this->cashier->id,
            ])->id,
            'widget_type' => 'stat_card',
            'title' => ['ar' => 'المجموع', 'en' => 'Sum'],
            'data_source' => [
                'register_id' => $this->receiptsRegister->id,
                'aggregation' => 'sum',
                'field' => 'amount',
            ],
            'register_id' => $this->receiptsRegister->id,
            'created_by' => $this->cashier->id,
            'updated_by' => $this->cashier->id,
        ]);

        $response = $this->actingAsCashier()->getJson("/api/v1/dashboard/widgets/{$widget->id}/data");

        $response->assertStatus(403);
    }
}
