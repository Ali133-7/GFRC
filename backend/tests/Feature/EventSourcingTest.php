<?php

namespace Tests\Feature;

use App\Models\Receipt;
use App\Models\ReceiptEvent;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionEvent;
use App\Services\EventReplayEngine;
use App\Services\EventStore;
use Illuminate\Support\Str;
use Tests\TestCase;

class EventSourcingTest extends TestCase
{
    protected EventStore $eventStore;
    protected EventReplayEngine $replayEngine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->eventStore = app(EventStore::class);
        $this->replayEngine = app(EventReplayEngine::class);
    }

    public function test_append_execution_event_creates_hash_chain(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $execution = WorkflowExecution::create([
            'workflow_version_id' => $version->id,
            'register_id' => $this->register->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $event1 = $this->eventStore->appendExecutionEvent(
            executionId: $execution->id,
            eventType: 'execution_started',
            payload: ['step_index' => 0],
        );

        $event2 = $this->eventStore->appendExecutionEvent(
            executionId: $execution->id,
            eventType: 'step_submitted',
            payload: ['step_index' => 0, 'next_step_index' => 1, 'values' => [], 'step_total' => '100'],
            calculatedItems: [['field_id' => '1', 'amount' => '100']],
        );

        $this->assertNotNull($event1->hash);
        $this->assertEquals($event1->hash, $event2->previous_event_hash);
        $this->assertNotEquals($event1->hash, $event2->hash);
    }

    public function test_append_receipt_event_creates_hash_chain(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '100.000',
            'status' => 'draft',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        $event1 = $this->eventStore->appendReceiptEvent(
            receiptId: $receipt->id,
            eventType: 'receipt_created',
            afterState: ['total_amount' => '100.000', 'status' => 'draft'],
        );

        $event2 = $this->eventStore->appendReceiptEvent(
            receiptId: $receipt->id,
            eventType: 'receipt_issued',
            afterState: ['total_amount' => '100.000', 'status' => 'issued'],
            beforeState: ['status' => 'draft'],
            lockVersion: 1,
        );

        $this->assertNotNull($event1->hash);
        $this->assertEquals($event1->hash, $event2->previous_event_hash);
    }

    public function test_replay_execution_rebuilds_state(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $execution = WorkflowExecution::create([
            'workflow_version_id' => $version->id,
            'register_id' => $this->register->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $this->eventStore->appendExecutionEvent(
            executionId: $execution->id,
            eventType: 'execution_started',
            payload: ['step_index' => 0],
        );

        $this->eventStore->appendExecutionEvent(
            executionId: $execution->id,
            eventType: 'step_submitted',
            payload: ['step_index' => 0, 'next_step_index' => 1, 'values' => ['key' => 'value'], 'step_total' => '150.500'],
            calculatedItems: [['field_id' => '1', 'amount' => '150.500']],
        );

        $this->eventStore->appendExecutionEvent(
            executionId: $execution->id,
            eventType: 'execution_completed',
            payload: ['total_amount' => '150.500'],
        );

        $state = $this->replayEngine->replayExecution($execution->id);

        $this->assertEquals('completed', $state['status']);
        $this->assertEquals('150.500', $state['total_amount']);
        $this->assertEquals(3, $state['events_applied']);
    }

    public function test_replay_receipt_rebuilds_state(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '100.000',
            'status' => 'draft',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        $this->eventStore->appendReceiptEvent(
            receiptId: $receipt->id,
            eventType: 'receipt_created',
            afterState: ['total_amount' => '100.000', 'status' => 'draft'],
            lockVersion: 0,
        );

        $this->eventStore->appendReceiptEvent(
            receiptId: $receipt->id,
            eventType: 'receipt_issued',
            afterState: ['total_amount' => '100.000', 'status' => 'issued'],
            beforeState: ['status' => 'draft'],
            lockVersion: 1,
        );

        $state = $this->replayEngine->replayReceipt($receipt->id);

        $this->assertEquals('issued', $state['status']);
        $this->assertEquals('100.000', $state['total_amount']);
        $this->assertEquals(2, $state['events_applied']);
    }

    public function test_verify_execution_detects_discrepancies(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $execution = WorkflowExecution::create([
            'workflow_version_id' => $version->id,
            'register_id' => $this->register->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '999.999', // Intentionally wrong
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $this->eventStore->appendExecutionEvent(
            executionId: $execution->id,
            eventType: 'execution_started',
            payload: ['step_index' => 0],
        );

        $this->eventStore->appendExecutionEvent(
            executionId: $execution->id,
            eventType: 'step_submitted',
            payload: ['step_index' => 0, 'next_step_index' => 1, 'values' => [], 'step_total' => '100.000'],
            calculatedItems: [['field_id' => '1', 'amount' => '100.000']],
        );

        $report = $this->replayEngine->verifyExecution($execution->id);

        $this->assertEquals('FAIL', $report['integrity']);
        $this->assertArrayHasKey('total_amount', $report['discrepancies']);
    }

    public function test_verify_receipt_detects_discrepancies(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '999.999', // Intentionally wrong
            'status' => 'issued',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        $this->eventStore->appendReceiptEvent(
            receiptId: $receipt->id,
            eventType: 'receipt_created',
            afterState: ['total_amount' => '100.000', 'status' => 'draft'],
            lockVersion: 0,
        );

        $this->eventStore->appendReceiptEvent(
            receiptId: $receipt->id,
            eventType: 'receipt_issued',
            afterState: ['total_amount' => '100.000', 'status' => 'issued'],
            beforeState: ['status' => 'draft'],
            lockVersion: 1,
        );

        $report = $this->replayEngine->verifyReceipt($receipt->id);

        $this->assertEquals('FAIL', $report['integrity']);
        $this->assertArrayHasKey('total_amount', $report['discrepancies']);
    }

    public function test_hash_chain_verification_passes(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '100.000',
            'status' => 'draft',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        $this->eventStore->appendReceiptEvent(
            receiptId: $receipt->id,
            eventType: 'receipt_created',
            afterState: ['total_amount' => '100.000', 'status' => 'draft'],
            lockVersion: 0,
        );

        $this->eventStore->appendReceiptEvent(
            receiptId: $receipt->id,
            eventType: 'receipt_issued',
            afterState: ['total_amount' => '100.000', 'status' => 'issued'],
            beforeState: ['status' => 'draft'],
            lockVersion: 1,
        );

        $report = $this->replayEngine->verifyReceiptChain($receipt->id);

        $this->assertEquals('PASS', $report['chain_integrity']);
        $this->assertEmpty($report['broken_links']);
    }

    public function test_tampered_hash_is_detected(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '100.000',
            'status' => 'draft',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        $this->eventStore->appendReceiptEvent(
            receiptId: $receipt->id,
            eventType: 'receipt_created',
            afterState: ['total_amount' => '100.000', 'status' => 'draft'],
            lockVersion: 0,
        );

        $event2 = $this->eventStore->appendReceiptEvent(
            receiptId: $receipt->id,
            eventType: 'receipt_issued',
            afterState: ['total_amount' => '100.000', 'status' => 'issued'],
            beforeState: ['status' => 'draft'],
            lockVersion: 1,
        );

        // Tamper with the hash
        $event2->hash = 'tampered_hash_value_' . Str::random(32);
        // Bypass model-level protection for testing
        \DB::table('receipt_events')
            ->where('id', $event2->id)
            ->update(['hash' => $event2->hash]);

        $report = $this->replayEngine->verifyReceiptChain($receipt->id);

        $this->assertEquals('FAIL', $report['chain_integrity']);
        $this->assertNotEmpty($report['broken_links']);
        $this->assertEquals('hash_mismatch', $report['broken_links'][0]['issue']);
    }

    public function test_tampered_previous_event_hash_is_detected(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '100.000',
            'status' => 'draft',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        $event1 = $this->eventStore->appendReceiptEvent(
            receiptId: $receipt->id,
            eventType: 'receipt_created',
            afterState: ['total_amount' => '100.000', 'status' => 'draft'],
            lockVersion: 0,
        );

        $this->eventStore->appendReceiptEvent(
            receiptId: $receipt->id,
            eventType: 'receipt_issued',
            afterState: ['total_amount' => '100.000', 'status' => 'issued'],
            beforeState: ['status' => 'draft'],
            lockVersion: 1,
        );

        // Tamper with the first event's hash (which breaks the chain for the second event)
        \DB::table('receipt_events')
            ->where('id', $event1->id)
            ->update(['hash' => 'tampered_' . Str::random(32)]);

        $report = $this->replayEngine->verifyReceiptChain($receipt->id);

        $this->assertEquals('FAIL', $report['chain_integrity']);
        $this->assertNotEmpty($report['broken_links']);
    }

    public function test_tampered_payload_is_detected(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '100.000',
            'status' => 'draft',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        $event = $this->eventStore->appendReceiptEvent(
            receiptId: $receipt->id,
            eventType: 'receipt_created',
            afterState: ['total_amount' => '100.000', 'status' => 'draft'],
            lockVersion: 0,
        );

        // Tamper with the payload
        $tamperedPayload = json_encode(['total_amount' => '999999.999', 'status' => 'draft']);
        \DB::table('receipt_events')
            ->where('id', $event->id)
            ->update(['after_state' => $tamperedPayload]);

        $report = $this->replayEngine->verifyReceiptChain($receipt->id);

        $this->assertEquals('FAIL', $report['chain_integrity']);
    }

    public function test_forensic_report_execution(): void
    {
        $workflow = $this->createWorkflow();
        $version = $this->createWorkflowVersion($workflow);

        $execution = WorkflowExecution::create([
            'workflow_version_id' => $version->id,
            'register_id' => $this->register->id,
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'started_by' => $this->admin->id,
            'started_at' => now(),
        ]);

        $this->eventStore->appendExecutionEvent(
            executionId: $execution->id,
            eventType: 'execution_started',
            payload: ['step_index' => 0],
        );

        $report = $this->replayEngine->forensicReportExecution($execution->id);

        $this->assertArrayHasKey('execution_id', $report);
        $this->assertArrayHasKey('chain_integrity', $report);
        $this->assertArrayHasKey('state_integrity', $report);
        $this->assertArrayHasKey('overall_integrity', $report);
        $this->assertArrayHasKey('event_timeline', $report);
    }

    public function test_forensic_report_receipt(): void
    {
        $receipt = Receipt::create([
            'register_id' => $this->register->id,
            'created_by' => $this->admin->id,
            'total_amount' => '100.000',
            'status' => 'draft',
            'receipt_number' => 'REG-001-2026-000001',
        ]);

        $this->eventStore->appendReceiptEvent(
            receiptId: $receipt->id,
            eventType: 'receipt_created',
            afterState: ['total_amount' => '100.000', 'status' => 'draft'],
            lockVersion: 0,
        );

        $report = $this->replayEngine->forensicReportReceipt($receipt->id);

        $this->assertArrayHasKey('receipt_id', $report);
        $this->assertArrayHasKey('chain_integrity', $report);
        $this->assertArrayHasKey('state_integrity', $report);
        $this->assertArrayHasKey('overall_integrity', $report);
        $this->assertArrayHasKey('event_timeline', $report);
    }
}
