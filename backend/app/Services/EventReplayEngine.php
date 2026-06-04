<?php

namespace App\Services;

use App\Models\Receipt;
use App\Models\ReceiptEvent;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionEvent;
use Illuminate\Support\Facades\DB;

/**
 * EventReplayEngine - Rebuilds state from event streams and verifies integrity.
 *
 * State = function(events)
 *
 * This engine proves that the current system state is derivable from events alone.
 * Any mismatch between stored state and replayed state indicates corruption or tampering.
 */
class EventReplayEngine
{
    protected EventStore $eventStore;
    protected FeeEngine $feeEngine;
    protected CalculationContext $ctx;

    public function __construct(EventStore $eventStore, FeeEngine $feeEngine)
    {
        $this->eventStore = $eventStore;
        $this->feeEngine = $feeEngine;
        $this->ctx = CalculationContext::default();
        $this->feeEngine->setContext($this->ctx);
    }

    // ============================================================
    // WORKFLOW EXECUTION REPLAY
    // ============================================================

    /**
     * Replay a workflow execution from its event stream.
     * Returns the reconstructed state.
     *
     * @return array{
     *   status: string,
     *   current_step_index: int,
     *   values_snapshot: array,
     *   calculated_items: array,
     *   total_amount: string,
     *   events_applied: int,
     *   first_event_at: string,
     *   last_event_at: string,
     * }
     */
    public function replayExecution(string $executionId): array
    {
        $events = $this->eventStore->getExecutionEvents($executionId);

        if (empty($events)) {
            throw new \RuntimeException("No events found for execution: {$executionId}");
        }

        $state = [
            'status' => 'in_progress',
            'current_step_index' => 0,
            'values_snapshot' => [],
            'calculated_items' => [],
            'total_amount' => '0.000',
            'events_applied' => 0,
            'first_event_at' => null,
            'last_event_at' => null,
        ];

        $state['first_event_at'] = $events[0]['created_at'];

        foreach ($events as $event) {
            $state['last_event_at'] = $event['created_at'];
            $state['events_applied']++;

            match ($event['event_type']) {
                WorkflowExecutionEvent::EXECUTION_STARTED => $this->applyExecutionStarted($state, $event),
                WorkflowExecutionEvent::STEP_SUBMITTED => $this->applyStepSubmitted($state, $event),
                WorkflowExecutionEvent::STEP_FAILED => $this->applyStepFailed($state, $event),
                WorkflowExecutionEvent::EXECUTION_COMPLETED => $this->applyExecutionCompleted($state, $event),
                WorkflowExecutionEvent::EXECUTION_CANCELLED => $this->applyExecutionCancelled($state, $event),
                WorkflowExecutionEvent::EXECUTION_REPLAYED => $this->applyExecutionReplayed($state, $event),
                default => throw new \RuntimeException("Unknown event type: {$event['event_type']}"),
            };
        }

        return $state;
    }

    /**
     * Compare replayed state against stored execution state.
     * Returns discrepancies if any.
     */
    public function verifyExecution(string $executionId): array
    {
        $execution = WorkflowExecution::findOrFail($executionId);
        $replayed = $this->replayExecution($executionId);

        $discrepancies = [];

        if ($execution->status !== $replayed['status']) {
            $discrepancies['status'] = [
                'stored' => $execution->status,
                'replayed' => $replayed['status'],
            ];
        }

        if ((string) $execution->current_step_index !== (string) $replayed['current_step_index']) {
            $discrepancies['current_step_index'] = [
                'stored' => $execution->current_step_index,
                'replayed' => $replayed['current_step_index'],
            ];
        }

        $storedTotal = $this->ctx->normalize((string) $execution->total_amount);
        $replayedTotal = $this->ctx->normalize($replayed['total_amount']);
        if ($storedTotal !== $replayedTotal) {
            $discrepancies['total_amount'] = [
                'stored' => $storedTotal,
                'replayed' => $replayedTotal,
            ];
        }

        return [
            'execution_id' => $executionId,
            'integrity' => empty($discrepancies) ? 'PASS' : 'FAIL',
            'events_replayed' => $replayed['events_applied'],
            'discrepancies' => $discrepancies,
            'replayed_state' => $replayed,
        ];
    }

    // ============================================================
    // RECEIPT REPLAY
    // ============================================================

    /**
     * Replay a receipt from its event stream.
     * Returns the reconstructed state.
     */
    public function replayReceipt(string $receiptId): array
    {
        $events = $this->eventStore->getReceiptEvents($receiptId);

        if (empty($events)) {
            throw new \RuntimeException("No events found for receipt: {$receiptId}");
        }

        $state = [
            'status' => 'draft',
            'total_amount' => '0.000',
            'version' => 1,
            'lock_version' => 0,
            'notes' => null,
            'items' => [],
            'fee_snapshot' => [],
            'events_applied' => 0,
            'first_event_at' => null,
            'last_event_at' => null,
        ];

        $state['first_event_at'] = $events[0]['created_at'];

        foreach ($events as $event) {
            $state['last_event_at'] = $event['created_at'];
            $state['events_applied']++;

            match ($event['event_type']) {
                ReceiptEvent::RECEIPT_CREATED => $this->applyReceiptCreated($state, $event),
                ReceiptEvent::RECEIPT_ISSUED => $this->applyReceiptIssued($state, $event),
                ReceiptEvent::RECEIPT_REVISED => $this->applyReceiptRevised($state, $event),
                ReceiptEvent::RECEIPT_CANCELLED => $this->applyReceiptCancelled($state, $event),
                ReceiptEvent::RECEIPT_PRINTED => $this->applyReceiptPrinted($state, $event),
                default => throw new \RuntimeException("Unknown event type: {$event['event_type']}"),
            };
        }

        return $state;
    }

    /**
     * Compare replayed receipt state against stored receipt state.
     */
    public function verifyReceipt(string $receiptId): array
    {
        $receipt = Receipt::findOrFail($receiptId);
        $replayed = $this->replayReceipt($receiptId);

        $discrepancies = [];

        if ($receipt->status !== $replayed['status']) {
            $discrepancies['status'] = [
                'stored' => $receipt->status,
                'replayed' => $replayed['status'],
            ];
        }

        $storedTotal = $this->ctx->normalize((string) $receipt->total_amount);
        $replayedTotal = $this->ctx->normalize($replayed['total_amount']);
        if ($storedTotal !== $replayedTotal) {
            $discrepancies['total_amount'] = [
                'stored' => $storedTotal,
                'replayed' => $replayedTotal,
            ];
        }

        if ($receipt->version !== $replayed['version']) {
            $discrepancies['version'] = [
                'stored' => $receipt->version,
                'replayed' => $replayed['version'],
            ];
        }

        if ($receipt->lock_version !== $replayed['lock_version']) {
            $discrepancies['lock_version'] = [
                'stored' => $receipt->lock_version,
                'replayed' => $replayed['lock_version'],
            ];
        }

        return [
            'receipt_id' => $receiptId,
            'integrity' => empty($discrepancies) ? 'PASS' : 'FAIL',
            'events_replayed' => $replayed['events_applied'],
            'discrepancies' => $discrepancies,
            'replayed_state' => $replayed,
        ];
    }

    // ============================================================
    // HASH CHAIN VERIFICATION
    // ============================================================

    /**
     * Verify the hash chain for a workflow execution.
     * Returns integrity report.
     */
    public function verifyExecutionChain(string $executionId): array
    {
        $events = $this->eventStore->getExecutionEvents($executionId);

        if (empty($events)) {
            return ['integrity' => 'EMPTY', 'message' => 'No events found'];
        }

        $report = [
            'execution_id' => $executionId,
            'total_events' => count($events),
            'chain_integrity' => 'PASS',
            'broken_links' => [],
        ];

        $previousHash = null;

        foreach ($events as $index => $event) {
            // Verify previous_event_hash matches
            if ($event['previous_event_hash'] !== $previousHash) {
                $report['chain_integrity'] = 'FAIL';
                $report['broken_links'][] = [
                    'event_index' => $index,
                    'event_id' => $event['id'],
                    'expected_previous' => $previousHash,
                    'actual_previous' => $event['previous_event_hash'],
                    'issue' => 'previous_event_hash_mismatch',
                ];
            }

            // Normalize created_at back to original format for hash verification
            $createdAt = $event['created_at'];
            if (is_object($createdAt)) {
                $createdAt = $createdAt instanceof \DateTimeInterface
                    ? $createdAt->format('Y-m-d H:i:s')
                    : (string) $createdAt;
            } elseif (is_string($createdAt) && str_contains($createdAt, 'T')) {
                $dt = new \DateTimeImmutable($createdAt);
                $createdAt = $dt->format('Y-m-d H:i:s');
            }

            // Recompute hash and verify
            $eventData = [
                'execution_id' => $event['execution_id'],
                'event_type' => $event['event_type'],
                'sequence' => $event['sequence'],
                'event_payload' => $event['event_payload'],
                'calculated_items' => $event['calculated_items'],
                'fee_snapshot' => $event['fee_snapshot'],
                'context_snapshot' => $event['context_snapshot'],
                'previous_event_hash' => $event['previous_event_hash'],
                'created_at' => $createdAt,
            ];

            $computedHash = $this->eventStore->computeHash($eventData, $event['previous_event_hash']);

            if ($computedHash !== $event['hash']) {
                $report['chain_integrity'] = 'FAIL';
                $report['broken_links'][] = [
                    'event_index' => $index,
                    'event_id' => $event['id'],
                    'expected_hash' => $computedHash,
                    'actual_hash' => $event['hash'],
                    'issue' => 'hash_mismatch',
                ];
            }

            $previousHash = $event['hash'];
        }

        return $report;
    }

    /**
     * Verify the hash chain for a receipt.
     * Returns integrity report.
     */
    public function verifyReceiptChain(string $receiptId): array
    {
        $events = $this->eventStore->getReceiptEvents($receiptId);

        if (empty($events)) {
            return ['integrity' => 'EMPTY', 'message' => 'No events found'];
        }

        $report = [
            'receipt_id' => $receiptId,
            'total_events' => count($events),
            'chain_integrity' => 'PASS',
            'broken_links' => [],
        ];

        $previousHash = null;

        foreach ($events as $index => $event) {
            // Verify previous_event_hash matches
            if ($event['previous_event_hash'] !== $previousHash) {
                $report['chain_integrity'] = 'FAIL';
                $report['broken_links'][] = [
                    'event_index' => $index,
                    'event_id' => $event['id'],
                    'expected_previous' => $previousHash,
                    'actual_previous' => $event['previous_event_hash'],
                    'issue' => 'previous_event_hash_mismatch',
                ];
            }

            // Normalize created_at back to original format for hash verification
            $createdAt = $event['created_at'];
            if (is_object($createdAt)) {
                $createdAt = $createdAt instanceof \DateTimeInterface
                    ? $createdAt->format('Y-m-d H:i:s')
                    : (string) $createdAt;
            } elseif (is_string($createdAt) && str_contains($createdAt, 'T')) {
                $dt = new \DateTimeImmutable($createdAt);
                $createdAt = $dt->format('Y-m-d H:i:s');
            }

            // Recompute hash and verify
            $eventData = [
                'receipt_id' => $event['receipt_id'],
                'event_type' => $event['event_type'],
                'sequence' => $event['sequence'],
                'before_state' => $event['before_state'],
                'after_state' => $event['after_state'],
                'fee_snapshot' => $event['fee_snapshot'],
                'context_snapshot' => $event['context_snapshot'],
                'lock_version' => $event['lock_version'],
                'previous_event_hash' => $event['previous_event_hash'],
                'created_at' => $createdAt,
            ];

            $computedHash = $this->eventStore->computeHash($eventData, $event['previous_event_hash']);

            if ($computedHash !== $event['hash']) {
                $report['chain_integrity'] = 'FAIL';
                $report['broken_links'][] = [
                    'event_index' => $index,
                    'event_id' => $event['id'],
                    'expected_hash' => $computedHash,
                    'actual_hash' => $event['hash'],
                    'issue' => 'hash_mismatch',
                ];
            }

            $previousHash = $event['hash'];
        }

        return $report;
    }

    /**
     * Full forensic report for an execution.
     */
    public function forensicReportExecution(string $executionId): array
    {
        $chainReport = $this->verifyExecutionChain($executionId);
        $stateReport = $this->verifyExecution($executionId);
        $events = $this->eventStore->getExecutionEvents($executionId);

        $eventTimeline = [];
        foreach ($events as $event) {
            $eventTimeline[] = [
                'sequence' => $event['sequence'],
                'event_type' => $event['event_type'],
                'created_at' => $event['created_at'],
                'caused_by' => $event['caused_by'],
                'ip_address' => $event['ip_address'],
                'hash' => substr($event['hash'], 0, 16) . '...',
            ];
        }

        return [
            'execution_id' => $executionId,
            'generated_at' => now()->toDateTimeString(),
            'chain_integrity' => $chainReport['chain_integrity'],
            'state_integrity' => $stateReport['integrity'],
            'overall_integrity' => (
                $chainReport['chain_integrity'] === 'PASS' &&
                $stateReport['integrity'] === 'PASS'
            ) ? 'PASS' : 'FAIL',
            'event_count' => count($events),
            'event_timeline' => $eventTimeline,
            'chain_broken_links' => $chainReport['broken_links'] ?? [],
            'state_discrepancies' => $stateReport['discrepancies'] ?? [],
        ];
    }

    /**
     * Full forensic report for a receipt.
     */
    public function forensicReportReceipt(string $receiptId): array
    {
        $chainReport = $this->verifyReceiptChain($receiptId);
        $stateReport = $this->verifyReceipt($receiptId);
        $events = $this->eventStore->getReceiptEvents($receiptId);

        $eventTimeline = [];
        foreach ($events as $event) {
            $eventTimeline[] = [
                'sequence' => $event['sequence'],
                'event_type' => $event['event_type'],
                'created_at' => $event['created_at'],
                'caused_by' => $event['caused_by'],
                'ip_address' => $event['ip_address'],
                'reason' => $event['reason'],
                'hash' => substr($event['hash'], 0, 16) . '...',
            ];
        }

        return [
            'receipt_id' => $receiptId,
            'generated_at' => now()->toDateTimeString(),
            'chain_integrity' => $chainReport['chain_integrity'],
            'state_integrity' => $stateReport['integrity'],
            'overall_integrity' => (
                $chainReport['chain_integrity'] === 'PASS' &&
                $stateReport['integrity'] === 'PASS'
            ) ? 'PASS' : 'FAIL',
            'event_count' => count($events),
            'event_timeline' => $eventTimeline,
            'chain_broken_links' => $chainReport['broken_links'] ?? [],
            'state_discrepancies' => $stateReport['discrepancies'] ?? [],
        ];
    }

    // ============================================================
    // EVENT APPLICATORS (State Transitions)
    // ============================================================

    protected function applyExecutionStarted(array &$state, array $event): void
    {
        $state['status'] = 'in_progress';
        $state['current_step_index'] = $event['event_payload']['step_index'] ?? 0;
        if (!empty($event['event_payload']['values'])) {
            $state['values_snapshot'] = $event['event_payload']['values'];
        }
    }

    protected function applyStepSubmitted(array &$state, array $event): void
    {
        $payload = $event['event_payload'];
        $state['current_step_index'] = $payload['next_step_index'] ?? $state['current_step_index'];
        $state['values_snapshot'] = $payload['values'] ?? $state['values_snapshot'];

        // Merge calculated items
        $newItems = $event['calculated_items'] ?? [];
        if (!empty($newItems)) {
            $state['calculated_items'] = array_merge($state['calculated_items'], $newItems);
        }

        // Accumulate total using BC math
        $stepTotal = $payload['step_total'] ?? '0';
        $state['total_amount'] = bcadd($state['total_amount'], $stepTotal, $this->ctx->scale());
    }

    protected function applyStepFailed(array &$state, array $event): void
    {
        // Step failure does not change financial state
        $state['status'] = 'in_progress';
    }

    protected function applyExecutionCompleted(array &$state, array $event): void
    {
        $state['status'] = 'completed';
    }

    protected function applyExecutionCancelled(array &$state, array $event): void
    {
        $state['status'] = 'cancelled';
    }

    protected function applyExecutionReplayed(array &$state, array $event): void
    {
        // Replay event is metadata only, no state change
    }

    protected function applyReceiptCreated(array &$state, array $event): void
    {
        $after = $event['after_state'];
        $state['status'] = 'draft';
        $state['total_amount'] = $after['total_amount'] ?? '0.000';
        $state['version'] = 1;
        $state['lock_version'] = $event['lock_version'] ?? 0;
        $state['notes'] = $after['notes'] ?? null;
        $state['items'] = $after['items'] ?? [];
        $state['fee_snapshot'] = $event['fee_snapshot'] ?? [];
    }

    protected function applyReceiptIssued(array &$state, array $event): void
    {
        $state['status'] = 'issued';
        $state['lock_version'] = $event['lock_version'] ?? $state['lock_version'] + 1;
        if (!empty($event['fee_snapshot'])) {
            $state['fee_snapshot'] = array_merge($state['fee_snapshot'], $event['fee_snapshot']);
        }
    }

    protected function applyReceiptRevised(array &$state, array $event): void
    {
        $after = $event['after_state'];
        $state['status'] = 'issued';
        $state['total_amount'] = $after['total_amount'] ?? $state['total_amount'];
        $state['version'] = ($state['version'] ?? 1) + 1;
        $state['lock_version'] = $event['lock_version'] ?? $state['lock_version'] + 1;
        $state['notes'] = $after['notes'] ?? $state['notes'];
        $state['items'] = $after['items'] ?? $state['items'];
    }

    protected function applyReceiptCancelled(array &$state, array $event): void
    {
        $state['status'] = 'cancelled';
        $state['lock_version'] = $event['lock_version'] ?? $state['lock_version'] + 1;
    }

    protected function applyReceiptPrinted(array &$state, array $event): void
    {
        $state['status'] = 'printed';
    }
}
