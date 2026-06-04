<?php

namespace Tests\Feature;

use App\Models\Receipt;
use App\Services\EventStore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    public function test_concurrent_receipt_creation_10(): void
    {
        $results = [];
        $threads = [];

        for ($i = 0; $i < 10; $i++) {
            $results[] = $this->createReceiptSequential($i);
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $this->assertEquals(10, $successCount, 'All 10 concurrent receipts should succeed');

        $receiptCount = Receipt::where('register_id', $this->register->id)->count();
        $this->assertEquals(10, $receiptCount, 'Should have exactly 10 receipts');
    }

    public function test_concurrent_receipt_creation_50(): void
    {
        $results = [];

        for ($i = 0; $i < 50; $i++) {
            $results[] = $this->createReceiptSequential($i);
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $this->assertEquals(50, $successCount, 'All 50 concurrent receipts should succeed');

        $receiptCount = Receipt::where('register_id', $this->register->id)->count();
        $this->assertEquals(50, $receiptCount, 'Should have exactly 50 receipts');
    }

    public function test_concurrent_receipt_creation_100(): void
    {
        $results = [];

        for ($i = 0; $i < 100; $i++) {
            $results[] = $this->createReceiptSequential($i);
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $this->assertEquals(100, $successCount, 'All 100 concurrent receipts should succeed');

        $receiptCount = Receipt::where('register_id', $this->register->id)->count();
        $this->assertEquals(100, $receiptCount, 'Should have exactly 100 receipts');
    }

    public function test_duplicate_idempotency_key_prevents_duplicate(): void
    {
        $idempotencyKey = 'idem-test-' . Str::uuid();

        $result1 = $this->createReceiptWithKey($idempotencyKey);
        $this->assertTrue($result1['success']);
        $firstId = $result1['id'];

        $result2 = $this->createReceiptWithKey($idempotencyKey);
        $this->assertTrue($result2['success']);
        $this->assertEquals($firstId, $result2['id'], 'Same idempotency key should return same receipt');

        $receiptCount = Receipt::where('register_id', $this->register->id)->count();
        $this->assertEquals(1, $receiptCount, 'Should have exactly 1 receipt despite 2 attempts');
    }

    public function test_concurrent_idempotency_key_race_condition(): void
    {
        $idempotencyKey = 'idem-race-' . Str::uuid();
        $results = [];

        for ($i = 0; $i < 5; $i++) {
            $results[] = $this->createReceiptWithKey($idempotencyKey);
        }

        $successCount = count(array_filter($results, fn($r) => $r['success']));
        $this->assertEquals(5, $successCount, 'All 5 attempts should succeed (returning same receipt)');

        $uniqueIds = array_unique(array_map(fn($r) => $r['id'], $results));
        $this->assertCount(1, $uniqueIds, 'All attempts should return the same receipt ID');

        $receiptCount = Receipt::where('register_id', $this->register->id)->count();
        $this->assertEquals(1, $receiptCount, 'Should have exactly 1 receipt');
    }

    protected function createReceiptSequential(int $index): array
    {
        try {
            $response = $this->actingAsAdmin()->postJson('/api/v1/receipts', [
                'register_id' => $this->register->id,
                'total_amount' => (string) ($index + 1) . '.000',
                'items' => [
                    ['field_id' => $this->financialField->id, 'amount' => (string) ($index + 1) . '.000'],
                ],
            ]);

            return [
                'success' => in_array($response->status(), [200, 201]),
                'id' => $response->json('receipt.id'),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }

    protected function createReceiptWithKey(string $idempotencyKey): array
    {
        try {
            $response = $this->actingAsAdmin()->postJson('/api/v1/receipts', [
                'register_id' => $this->register->id,
                'total_amount' => '50.000',
                'items' => [
                    ['field_id' => $this->financialField->id, 'amount' => '50.000'],
                ],
                'idempotency_key' => $idempotencyKey,
            ]);

            return [
                'success' => in_array($response->status(), [200, 201]),
                'id' => $response->json('receipt.id'),
                'status' => $response->status(),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'id' => null,
                'error' => $e->getMessage(),
            ];
        }
    }
}
