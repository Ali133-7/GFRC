<?php

namespace App\Services;

use App\Models\IdempotencyKey;
use App\Models\ReceiptEvent;
use App\Models\WorkflowExecutionEvent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * EventStore - Append-only event persistence with hash chaining.
 *
 * This is the ONLY way to write events to the system.
 * No direct model creation is allowed for event tables.
 *
 * Hash chain: hash = SHA-256(canonical_json(event_data) + previous_event_hash)
 */
class EventStore
{
    /**
     * Append a workflow execution event.
     * Returns the created event.
     *
     * @throws \RuntimeException on idempotency conflict or hash chain mismatch
     */
    public function appendExecutionEvent(
        string $executionId,
        string $eventType,
        array $payload,
        array $calculatedItems = [],
        array $feeSnapshot = [],
        array $contextSnapshot = [],
        ?string $idempotencyKey = null,
        ?string $causedBy = null,
        ?string $expectedPreviousHash = null
    ): WorkflowExecutionEvent {
        return DB::transaction(function () use (
            $executionId,
            $eventType,
            $payload,
            $calculatedItems,
            $feeSnapshot,
            $contextSnapshot,
            $idempotencyKey,
            $causedBy,
            $expectedPreviousHash
        ) {
            // Idempotency check
            if ($idempotencyKey) {
                $existing = IdempotencyKey::findActive($idempotencyKey);
                if ($existing) {
                    $event = WorkflowExecutionEvent::where('idempotency_key', $idempotencyKey)->first();
                    if ($event) {
                        return $event;
                    }
                }
            }

            // Get next sequence with row-level lock
            $lastEvent = WorkflowExecutionEvent::where('execution_id', $executionId)
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->first();

            $nextSequence = $lastEvent ? $lastEvent->sequence + 1 : 0;
            $previousHash = $lastEvent?->hash;

            // Verify hash chain if caller provided expected previous hash
            if ($expectedPreviousHash !== null && $previousHash !== $expectedPreviousHash) {
                throw new \RuntimeException(
                    "Hash chain mismatch: expected {$expectedPreviousHash}, got {$previousHash}"
                );
            }

            // Build event data for hashing
            $eventData = [
                'execution_id' => $executionId,
                'event_type' => $eventType,
                'sequence' => $nextSequence,
                'event_payload' => $payload,
                'calculated_items' => $calculatedItems,
                'fee_snapshot' => $feeSnapshot,
                'context_snapshot' => $contextSnapshot,
                'previous_event_hash' => $previousHash,
                'created_at' => now()->toDateTimeString(),
            ];

            $hash = $this->computeHash($eventData, $previousHash);

            // Create event (append-only)
            $event = WorkflowExecutionEvent::create([
                'execution_id' => $executionId,
                'event_type' => $eventType,
                'sequence' => $nextSequence,
                'event_payload' => $payload,
                'calculated_items' => $calculatedItems,
                'fee_snapshot' => $feeSnapshot,
                'context_snapshot' => $contextSnapshot,
                'previous_event_hash' => $previousHash,
                'hash' => $hash,
                'idempotency_key' => $idempotencyKey,
                'caused_by' => $causedBy,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'created_at' => now(),
            ]);

            // Record idempotency key
            if ($idempotencyKey) {
                IdempotencyKey::create([
                    'key' => $idempotencyKey,
                    'entity_type' => 'workflow_execution',
                    'entity_id' => $executionId,
                    'request_hash' => hash('sha256', json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                    'response_snapshot' => null,
                ]);
            }

            return $event;
        });
    }

    /**
     * Append a receipt event.
     * Returns the created event.
     *
     * @throws \RuntimeException on idempotency conflict or hash chain mismatch
     */
    public function appendReceiptEvent(
        string $receiptId,
        string $eventType,
        array $afterState,
        ?array $beforeState = null,
        array $feeSnapshot = [],
        array $contextSnapshot = [],
        int $lockVersion = 0,
        ?string $idempotencyKey = null,
        ?string $causedBy = null,
        ?string $reason = null,
        ?string $expectedPreviousHash = null
    ): ReceiptEvent {
        return DB::transaction(function () use (
            $receiptId,
            $eventType,
            $afterState,
            $beforeState,
            $feeSnapshot,
            $contextSnapshot,
            $lockVersion,
            $idempotencyKey,
            $causedBy,
            $reason,
            $expectedPreviousHash
        ) {
            // Idempotency check
            if ($idempotencyKey) {
                $existing = IdempotencyKey::findActive($idempotencyKey);
                if ($existing) {
                    $event = ReceiptEvent::where('idempotency_key', $idempotencyKey)->first();
                    if ($event) {
                        return $event;
                    }
                }
            }

            // Get next sequence with row-level lock
            $lastEvent = ReceiptEvent::where('receipt_id', $receiptId)
                ->orderByDesc('sequence')
                ->lockForUpdate()
                ->first();

            $nextSequence = $lastEvent ? $lastEvent->sequence + 1 : 0;
            $previousHash = $lastEvent?->hash;

            // Verify hash chain if caller provided expected previous hash
            if ($expectedPreviousHash !== null && $previousHash !== $expectedPreviousHash) {
                throw new \RuntimeException(
                    "Hash chain mismatch: expected {$expectedPreviousHash}, got {$previousHash}"
                );
            }

            // Build event data for hashing
            $eventData = [
                'receipt_id' => $receiptId,
                'event_type' => $eventType,
                'sequence' => $nextSequence,
                'before_state' => $beforeState,
                'after_state' => $afterState,
                'fee_snapshot' => $feeSnapshot,
                'context_snapshot' => $contextSnapshot,
                'lock_version' => $lockVersion,
                'previous_event_hash' => $previousHash,
                'created_at' => now()->toDateTimeString(),
            ];

            $hash = $this->computeHash($eventData, $previousHash);

            // Create event (append-only)
            $event = ReceiptEvent::create([
                'receipt_id' => $receiptId,
                'event_type' => $eventType,
                'sequence' => $nextSequence,
                'before_state' => $beforeState,
                'after_state' => $afterState,
                'fee_snapshot' => $feeSnapshot,
                'context_snapshot' => $contextSnapshot,
                'lock_version' => $lockVersion,
                'previous_event_hash' => $previousHash,
                'hash' => $hash,
                'idempotency_key' => $idempotencyKey,
                'caused_by' => $causedBy,
                'ip_address' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'reason' => $reason,
                'created_at' => now(),
            ]);

            // Record idempotency key
            if ($idempotencyKey) {
                IdempotencyKey::create([
                    'key' => $idempotencyKey,
                    'entity_type' => 'receipt',
                    'entity_id' => $receiptId,
                    'request_hash' => hash('sha256', json_encode($afterState, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)),
                    'response_snapshot' => null,
                ]);
            }

            return $event;
        });
    }

    /**
     * Get the last event hash for an entity.
     */
    public function getLastEventHash(string $entityType, string $entityId): ?string
    {
        if ($entityType === 'workflow_execution') {
            $event = WorkflowExecutionEvent::where('execution_id', $entityId)
                ->orderByDesc('sequence')
                ->first();
            return $event?->hash;
        }

        if ($entityType === 'receipt') {
            $event = ReceiptEvent::where('receipt_id', $entityId)
                ->orderByDesc('sequence')
                ->first();
            return $event?->hash;
        }

        return null;
    }

    /**
     * Get all events for a workflow execution, ordered by sequence.
     */
    public function getExecutionEvents(string $executionId): array
    {
        return WorkflowExecutionEvent::where('execution_id', $executionId)
            ->orderBy('sequence')
            ->get()
            ->toArray();
    }

    /**
     * Get all events for a receipt, ordered by sequence.
     */
    public function getReceiptEvents(string $receiptId): array
    {
        return ReceiptEvent::where('receipt_id', $receiptId)
            ->orderBy('sequence')
            ->get()
            ->toArray();
    }

    /**
     * Compute SHA-256 hash for an event.
     * hash = SHA-256(canonical_json(event_data) + previous_event_hash)
     */
    public function computeHash(array $eventData, ?string $previousHash): string
    {
        $canonical = $this->canonicalJson($eventData);
        $input = $canonical . ($previousHash ?? 'genesis');
        return hash('sha256', $input);
    }

    /**
     * Encode data as canonical JSON (sorted keys, consistent formatting).
     */
    protected function canonicalJson(mixed $data): string
    {
        return json_encode(
            $this->sortKeysRecursive($data),
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRESERVE_ZERO_FRACTION
        );
    }

    /**
     * Recursively sort array keys for deterministic JSON output.
     */
    protected function sortKeysRecursive(mixed $data): mixed
    {
        if (is_array($data)) {
            if (array_keys($data) !== range(0, count($data) - 1)) {
                ksort($data);
                foreach ($data as $key => $value) {
                    $data[$key] = $this->sortKeysRecursive($value);
                }
            } else {
                foreach ($data as $key => $value) {
                    $data[$key] = $this->sortKeysRecursive($value);
                }
            }
        }
        return $data;
    }
}
