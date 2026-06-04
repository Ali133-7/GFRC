<?php

namespace Tests\Feature;

use App\Models\Receipt;
use App\Models\ReceiptItem;
use Tests\TestCase;

class RBACPermissionTest extends TestCase
{
    public function test_admin_can_access_all_endpoints(): void
    {
        $this->actingAsAdmin()->getJson('/api/v1/receipts')->assertStatus(200);
        $this->actingAsAdmin()->getJson('/api/v1/registers')->assertStatus(200);
        $this->actingAsAdmin()->getJson('/api/v1/workflows')->assertStatus(200);
    }

    public function test_cashier_can_create_receipts(): void
    {
        $response = $this->actingAsCashier()->postJson('/api/v1/receipts', [
            'register_id' => $this->register->id,
            'total_amount' => '100.000',
            'items' => [
                ['field_id' => $this->financialField->id, 'amount' => '100.000'],
            ],
        ]);

        $response->assertStatus(200);
    }

    public function test_cashier_can_view_receipts(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->cashier->id,
            'total_amount' => '100.000',
            'status' => 'draft',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        $response = $this->actingAsCashier()->getJson("/api/v1/receipts/{$receipt->id}");
        $response->assertStatus(200);
    }

    public function test_cashier_can_issue_receipts(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->cashier->id,
            'total_amount' => '100.000',
            'status' => 'draft',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        ReceiptItem::create([
            'receipt_id' => $receipt->id,
            'field_id' => $this->financialField->id,
            'field_name_snapshot' => $this->financialField->name,
            'label_ar_snapshot' => $this->financialField->label_ar,
            'amount' => '100.000',
        ]);

        $response = $this->actingAsCashier()->postJson("/api/v1/receipts/{$receipt->id}/issue");
        $response->assertStatus(200);
    }

    public function test_manager_can_access_receipts(): void
    {
        $response = $this->actingAsManager()->getJson('/api/v1/receipts');
        $response->assertStatus(200);
    }

    public function test_manager_can_access_registers(): void
    {
        $response = $this->actingAsManager()->getJson('/api/v1/registers');
        $response->assertStatus(200);
    }

    public function test_manager_can_access_workflows(): void
    {
        $response = $this->actingAsManager()->getJson('/api/v1/workflows');
        $response->assertStatus(200);
    }

    public function test_auditor_can_view_receipts(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '100.000',
            'status' => 'draft',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        $response = $this->actingAsAuditor()->getJson("/api/v1/receipts/{$receipt->id}");
        $response->assertStatus(200);
    }

    public function test_auditor_can_view_audit_logs(): void
    {
        $response = $this->actingAsAuditor()->getJson('/api/v1/audit-logs');
        $response->assertStatus(200);
    }

    public function test_auditor_can_view_reports(): void
    {
        $response = $this->actingAsAuditor()->getJson('/api/v1/reports/daily?date=' . now()->format('Y-m-d'));
        $response->assertStatus(200);
    }

    public function test_unauthenticated_user_cannot_access_protected_endpoints(): void
    {
        $this->getJson('/api/v1/receipts')->assertStatus(401);
        $this->getJson('/api/v1/registers')->assertStatus(401);
        $this->getJson('/api/v1/users')->assertStatus(401);
        $this->getJson('/api/v1/workflows')->assertStatus(401);
    }

    public function test_health_endpoint_is_public(): void
    {
        $response = $this->getJson('/api/v1/health');
        $response->assertStatus(200);
    }

    public function test_login_endpoint_is_public(): void
    {
        $response = $this->postJson('/api/v1/auth/login', [
            'username' => 'admin',
            'password' => 'password',
        ]);
        $response->assertStatus(200);
    }
}
