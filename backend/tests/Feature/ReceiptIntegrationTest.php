<?php

namespace Tests\Feature;

use App\Models\Receipt;
use App\Models\ReceiptEvent;
use Illuminate\Support\Str;
use Tests\TestCase;

class ReceiptIntegrationTest extends TestCase
{
    public function test_create_receipt(): void
    {
        $response = $this->actingAsAdmin()->postJson('/api/v1/receipts', [
            'register_id' => $this->register->id,
            'total_amount' => '100.500',
            'items' => [
                [
                    'field_id' => $this->financialField->id,
                    'amount' => '100.500',
                ],
            ],
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('receipts', [
            'register_id' => $this->register->id,
            'status' => 'draft',
        ]);

        $receiptId = $response->json('data.id');
        $this->assertNotNull($receiptId);
        $this->assertDatabaseHas('receipt_events', [
            'receipt_id' => $receiptId,
            'event_type' => 'receipt_created',
        ]);
    }

    public function test_create_receipt_with_idempotency_key(): void
    {
        $idempotencyKey = 'test-idem-' . Str::uuid();

        $response1 = $this->actingAsAdmin()->postJson('/api/v1/receipts', [
            'register_id' => $this->register->id,
            'total_amount' => '50.000',
            'items' => [
                ['field_id' => $this->financialField->id, 'amount' => '50.000'],
            ],
            'idempotency_key' => $idempotencyKey,
        ]);

        $response1->assertStatus(200);
        $firstId = $response1->json('data.id');

        $response2 = $this->actingAsAdmin()->postJson('/api/v1/receipts', [
            'register_id' => $this->register->id,
            'total_amount' => '50.000',
            'items' => [
                ['field_id' => $this->financialField->id, 'amount' => '50.000'],
            ],
            'idempotency_key' => $idempotencyKey,
        ]);

        $response2->assertStatus(200);
        $this->assertEquals($firstId, $response2->json('data.id'));
    }

    public function test_issue_receipt(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '100.000',
            'status' => 'draft',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        \App\Models\ReceiptItem::create([
            'receipt_id' => $receipt->id,
            'field_id' => $this->financialField->id,
            'field_name_snapshot' => $this->financialField->name,
            'label_ar_snapshot' => $this->financialField->label_ar,
            'amount' => '100.000',
        ]);

        $response = $this->actingAsAdmin()->postJson("/api/v1/receipts/{$receipt->id}/issue");

        $response->assertStatus(200);
        $this->assertDatabaseHas('receipts', [
            'id' => $receipt->id,
            'status' => 'issued',
        ]);
        $this->assertDatabaseHas('receipt_events', [
            'receipt_id' => $receipt->id,
            'event_type' => 'receipt_issued',
        ]);
    }

    public function test_issue_receipt_fails_if_not_draft(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '100.000',
            'status' => 'issued',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        $response = $this->actingAsAdmin()->postJson("/api/v1/receipts/{$receipt->id}/issue");

        $response->assertStatus(422);
    }

    public function test_cancel_receipt(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '100.000',
            'status' => 'issued',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        $response = $this->actingAsAdmin()->postJson("/api/v1/receipts/{$receipt->id}/cancel", [
            'reason' => 'Test cancellation',
        ]);

        $response->assertStatus(200);
        $this->assertDatabaseHas('receipts', [
            'id' => $receipt->id,
            'status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('receipt_events', [
            'receipt_id' => $receipt->id,
            'event_type' => 'receipt_cancelled',
        ]);
    }

    public function test_cancel_draft_receipt_fails(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '100.000',
            'status' => 'draft',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        $response = $this->actingAsAdmin()->postJson("/api/v1/receipts/{$receipt->id}/cancel", [
            'reason' => 'Test cancellation',
        ]);

        $response->assertStatus(422);
    }

    public function test_revise_receipt(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '100.000',
            'status' => 'issued',
            'receipt_number' => 'REG-001-2026-000001',
            'version' => 1,
        ]);

        $response = $this->actingAsAdmin()->postJson("/api/v1/receipts/{$receipt->id}/revise", [
            'total_amount' => '150.000',
            'reason' => 'Price correction',
            'items' => [
                ['field_id' => $this->financialField->id, 'amount' => '150.000'],
            ],
        ]);

        $response->assertStatus(200);
        $receipt->refresh();
        $this->assertEquals('150.000', $receipt->total_amount);
        $this->assertEquals(2, $receipt->version);
        $this->assertDatabaseHas('receipt_events', [
            'receipt_id' => $receipt->id,
            'event_type' => 'receipt_revised',
        ]);
    }

    public function test_update_draft_receipt(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '100.000',
            'status' => 'draft',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        $response = $this->actingAsAdmin()->putJson("/api/v1/receipts/{$receipt->id}", [
            'total_amount' => '200.000',
            'items' => [
                ['field_id' => $this->financialField->id, 'amount' => '200.000'],
            ],
        ]);

        $response->assertStatus(200);
        $receipt->refresh();
        $this->assertEquals('200.000', $receipt->total_amount);
    }

    public function test_show_receipt(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '100.000',
            'status' => 'draft',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        $response = $this->actingAsAdmin()->getJson("/api/v1/receipts/{$receipt->id}");

        $response->assertStatus(200);
        $response->assertJsonPath('data.id', $receipt->id);
    }

    public function test_list_receipts(): void
    {
        Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '100.000',
            'status' => 'draft',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        $response = $this->actingAsAdmin()->getJson('/api/v1/receipts');

        $response->assertStatus(200);
        $this->assertCount(1, $response->json('data'));
    }
}
