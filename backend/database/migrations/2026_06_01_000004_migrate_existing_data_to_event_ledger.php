<?php

use App\Models\Receipt;
use App\Models\ReceiptEvent;
use App\Models\WorkflowExecution;
use App\Models\WorkflowExecutionEvent;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Migrate existing data to event-sourced ledger.
     *
     * For each existing receipt:
     *   1. Create receipt_created event from current state
     *   2. If status != 'draft', create receipt_issued event
     *   3. If status == 'cancelled', create receipt_cancelled event
     *
     * For each existing execution:
     *   1. Create execution_started event
     *   2. If status == 'completed', create execution_completed event
     *   3. If status == 'cancelled', create execution_cancelled event
     */
    public function up(): void
    {
        DB::transaction(function () {
            $this->migrateReceipts();
            $this->migrateExecutions();
        });
    }

    protected function migrateReceipts(): void
    {
        $receipts = Receipt::with('items')->orderBy('created_at')->get();
        $migrated = 0;

        foreach ($receipts as $receipt) {
            // Check if already migrated
            $existingEvent = ReceiptEvent::where('receipt_id', $receipt->id)->first();
            if ($existingEvent) {
                continue;
            }

            $genesisHash = null;

            // Event 1: receipt_created
            $createdPayload = [
                'receipt_number' => $receipt->receipt_number,
                'total_amount' => (string) $receipt->total_amount,
                'status' => 'draft',
                'notes' => $receipt->notes,
                'items' => $receipt->items->map(fn($item) => [
                    'field_id' => $item->field_id,
                    'amount' => $item->amount,
                    'text_value' => $item->text_value,
                    'field_name_snapshot' => $item->field_name_snapshot,
                    'label_ar_snapshot' => $item->label_ar_snapshot,
                ])->toArray(),
            ];

            $eventData = [
                'receipt_id' => $receipt->id,
                'event_type' => 'receipt_created',
                'sequence' => 0,
                'before_state' => null,
                'after_state' => $createdPayload,
                'fee_snapshot' => [],
                'context_snapshot' => [],
                'lock_version' => 0,
                'previous_event_hash' => null,
                'created_at' => $receipt->created_at->toDateTimeString(),
            ];

            $hash = hash('sha256', json_encode($eventData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . 'genesis');

            ReceiptEvent::create([
                'id' => (string) Str::uuid(),
                'receipt_id' => $receipt->id,
                'event_type' => 'receipt_created',
                'sequence' => 0,
                'before_state' => null,
                'after_state' => $createdPayload,
                'fee_snapshot' => [],
                'context_snapshot' => [],
                'lock_version' => 0,
                'previous_event_hash' => null,
                'hash' => $hash,
                'idempotency_key' => $receipt->idempotency_key,
                'caused_by' => $receipt->created_by,
                'ip_address' => null,
                'user_agent' => null,
                'reason' => null,
                'created_at' => $receipt->created_at,
            ]);

            $genesisHash = $hash;
            $seq = 1;
            $lockVersion = 0;

            // Event 2: receipt_issued (if status was ever issued)
            if (in_array($receipt->status, ['issued', 'printed', 'cancelled'])) {
                $issuedPayload = [
                    'receipt_number' => $receipt->receipt_number,
                    'total_amount' => (string) $receipt->total_amount,
                    'status' => 'issued',
                    'notes' => $receipt->notes,
                    'approved_by' => $receipt->approved_by,
                    'qr_payload' => $receipt->qr_payload,
                ];

                $eventData = [
                    'receipt_id' => $receipt->id,
                    'event_type' => 'receipt_issued',
                    'sequence' => $seq,
                    'before_state' => ['status' => 'draft'],
                    'after_state' => $issuedPayload,
                    'fee_snapshot' => [],
                    'context_snapshot' => [],
                    'lock_version' => 1,
                    'previous_event_hash' => $genesisHash,
                    'created_at' => $receipt->approved_by ? $receipt->updated_at->toDateTimeString() : $receipt->created_at->toDateTimeString(),
                ];

                $hash = hash('sha256', json_encode($eventData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . $genesisHash);

                ReceiptEvent::create([
                    'id' => (string) Str::uuid(),
                    'receipt_id' => $receipt->id,
                    'event_type' => 'receipt_issued',
                    'sequence' => $seq,
                    'before_state' => ['status' => 'draft'],
                    'after_state' => $issuedPayload,
                    'fee_snapshot' => [],
                    'context_snapshot' => [],
                    'lock_version' => 1,
                    'previous_event_hash' => $genesisHash,
                    'hash' => $hash,
                    'caused_by' => $receipt->approved_by,
                    'created_at' => $receipt->approved_by ? $receipt->updated_at : $receipt->created_at,
                ]);

                $genesisHash = $hash;
                $seq++;
                $lockVersion = 1;
            }

            // Event 3: receipt_cancelled (if cancelled)
            if ($receipt->status === 'cancelled') {
                $cancelledPayload = [
                    'receipt_number' => $receipt->receipt_number,
                    'total_amount' => (string) $receipt->total_amount,
                    'status' => 'cancelled',
                    'cancelled_by' => $receipt->cancelled_by,
                    'cancel_reason' => $receipt->cancel_reason,
                    'cancelled_at' => $receipt->cancelled_at?->toDateTimeString(),
                ];

                $eventData = [
                    'receipt_id' => $receipt->id,
                    'event_type' => 'receipt_cancelled',
                    'sequence' => $seq,
                    'before_state' => ['status' => 'issued', 'lock_version' => $lockVersion],
                    'after_state' => $cancelledPayload,
                    'fee_snapshot' => [],
                    'context_snapshot' => [],
                    'lock_version' => $lockVersion + 1,
                    'previous_event_hash' => $genesisHash,
                    'created_at' => $receipt->cancelled_at?->toDateTimeString() ?? now()->toDateTimeString(),
                ];

                $hash = hash('sha256', json_encode($eventData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . $genesisHash);

                ReceiptEvent::create([
                    'id' => (string) Str::uuid(),
                    'receipt_id' => $receipt->id,
                    'event_type' => 'receipt_cancelled',
                    'sequence' => $seq,
                    'before_state' => ['status' => 'issued', 'lock_version' => $lockVersion],
                    'after_state' => $cancelledPayload,
                    'fee_snapshot' => [],
                    'context_snapshot' => [],
                    'lock_version' => $lockVersion + 1,
                    'previous_event_hash' => $genesisHash,
                    'hash' => $hash,
                    'caused_by' => $receipt->cancelled_by,
                    'reason' => $receipt->cancel_reason,
                    'created_at' => $receipt->cancelled_at ?? now(),
                ]);
            }

            // Event 4: receipt_printed (if printed)
            if ($receipt->status === 'printed') {
                $printedPayload = [
                    'status' => 'printed',
                    'printed_at' => $receipt->printed_at?->toDateTimeString(),
                ];

                $eventData = [
                    'receipt_id' => $receipt->id,
                    'event_type' => 'receipt_printed',
                    'sequence' => $seq,
                    'before_state' => ['status' => 'issued'],
                    'after_state' => $printedPayload,
                    'lock_version' => $lockVersion + 1,
                    'previous_event_hash' => $genesisHash,
                    'created_at' => $receipt->printed_at?->toDateTimeString() ?? now()->toDateTimeString(),
                ];

                $hash = hash('sha256', json_encode($eventData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . $genesisHash);

                ReceiptEvent::create([
                    'id' => (string) Str::uuid(),
                    'receipt_id' => $receipt->id,
                    'event_type' => 'receipt_printed',
                    'sequence' => $seq,
                    'before_state' => ['status' => 'issued'],
                    'after_state' => $printedPayload,
                    'lock_version' => $lockVersion + 1,
                    'previous_event_hash' => $genesisHash,
                    'hash' => $hash,
                    'created_at' => $receipt->printed_at ?? now(),
                ]);
            }

            $migrated++;
        }
    }

    protected function migrateExecutions(): void
    {
        $executions = WorkflowExecution::orderBy('started_at')->get();
        $migrated = 0;

        foreach ($executions as $execution) {
            $existingEvent = WorkflowExecutionEvent::where('execution_id', $execution->id)->first();
            if ($existingEvent) {
                continue;
            }

            $genesisHash = null;

            // Event 1: execution_started
            $startPayload = [
                'workflow_version_id' => $execution->workflow_version_id,
                'register_id' => $execution->register_id,
                'started_by' => $execution->started_by,
                'step_index' => 0,
                'values' => $execution->values_snapshot ?? [],
            ];

            $eventData = [
                'execution_id' => $execution->id,
                'event_type' => 'execution_started',
                'sequence' => 0,
                'event_payload' => $startPayload,
                'calculated_items' => [],
                'fee_snapshot' => [],
                'context_snapshot' => [],
                'previous_event_hash' => null,
                'created_at' => $execution->started_at->toDateTimeString(),
            ];

            $hash = hash('sha256', json_encode($eventData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . 'genesis');

            WorkflowExecutionEvent::create([
                'id' => (string) Str::uuid(),
                'execution_id' => $execution->id,
                'event_type' => 'execution_started',
                'sequence' => 0,
                'event_payload' => $startPayload,
                'calculated_items' => [],
                'fee_snapshot' => [],
                'context_snapshot' => [],
                'previous_event_hash' => null,
                'hash' => $hash,
                'idempotency_key' => null,
                'caused_by' => $execution->started_by,
                'ip_address' => $execution->ip_address,
                'user_agent' => $execution->user_agent,
                'created_at' => $execution->started_at,
            ]);

            $genesisHash = $hash;

            // Event 2: execution_completed or execution_cancelled
            if ($execution->status === 'completed') {
                $completePayload = [
                    'total_amount' => (string) $execution->total_amount,
                    'calculated_items_count' => count($execution->calculated_items ?? []),
                    'receipt_id' => $execution->receipt_id,
                ];

                $eventData = [
                    'execution_id' => $execution->id,
                    'event_type' => 'execution_completed',
                    'sequence' => 1,
                    'event_payload' => $completePayload,
                    'calculated_items' => $execution->calculated_items ?? [],
                    'fee_snapshot' => [],
                    'context_snapshot' => [],
                    'previous_event_hash' => $genesisHash,
                    'created_at' => $execution->completed_at?->toDateTimeString() ?? now()->toDateTimeString(),
                ];

                $hash = hash('sha256', json_encode($eventData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . $genesisHash);

                WorkflowExecutionEvent::create([
                    'id' => (string) Str::uuid(),
                    'execution_id' => $execution->id,
                    'event_type' => 'execution_completed',
                    'sequence' => 1,
                    'event_payload' => $completePayload,
                    'calculated_items' => $execution->calculated_items ?? [],
                    'fee_snapshot' => [],
                    'context_snapshot' => [],
                    'previous_event_hash' => $genesisHash,
                    'hash' => $hash,
                    'caused_by' => $execution->started_by,
                    'created_at' => $execution->completed_at ?? now(),
                ]);
            } elseif ($execution->status === 'cancelled') {
                $cancelPayload = [
                    'reason' => $execution->cancel_reason,
                    'total_amount_at_cancel' => (string) $execution->total_amount,
                ];

                $eventData = [
                    'execution_id' => $execution->id,
                    'event_type' => 'execution_cancelled',
                    'sequence' => 1,
                    'event_payload' => $cancelPayload,
                    'calculated_items' => [],
                    'fee_snapshot' => [],
                    'context_snapshot' => [],
                    'previous_event_hash' => $genesisHash,
                    'created_at' => $execution->cancelled_at?->toDateTimeString() ?? now()->toDateTimeString(),
                ];

                $hash = hash('sha256', json_encode($eventData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . $genesisHash);

                WorkflowExecutionEvent::create([
                    'id' => (string) Str::uuid(),
                    'execution_id' => $execution->id,
                    'event_type' => 'execution_cancelled',
                    'sequence' => 1,
                    'event_payload' => $cancelPayload,
                    'calculated_items' => [],
                    'fee_snapshot' => [],
                    'context_snapshot' => [],
                    'previous_event_hash' => $genesisHash,
                    'hash' => $hash,
                    'caused_by' => $execution->started_by,
                    'reason' => $execution->cancel_reason,
                    'created_at' => $execution->cancelled_at ?? now(),
                ]);
            }

            $migrated++;
        }
    }

    public function down(): void
    {
        // Do NOT delete events in production.
        // This is only for local development rollback.
        if (app()->environment('production')) {
            throw new \RuntimeException('Cannot rollback event migration in production. Events are immutable.');
        }

        ReceiptEvent::truncate();
        WorkflowExecutionEvent::truncate();
    }
};
