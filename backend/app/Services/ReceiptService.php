<?php

namespace App\Services;

use App\Models\IdempotencyKey;
use App\Models\Receipt;
use App\Models\ReceiptEvent;
use App\Models\ReceiptItem;
use App\Models\ReceiptRevision;
use App\Models\Register;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * ReceiptService - Event-sourced receipt operations.
 *
 * All state changes are recorded as immutable events.
 * The receipts table is a denormalized read cache, not source of truth.
 * Source of truth = receipt_events table.
 */
class ReceiptService
{
    protected EventStore $eventStore;
    protected CalculationContext $ctx;

    public function __construct(EventStore $eventStore)
    {
        $this->eventStore = $eventStore;
        $this->ctx = CalculationContext::default();
    }

    /**
     * Create a new receipt.
     * Creates receipt_created event.
     */
    public function create(array $data, string $userId): Receipt
    {
        return DB::transaction(function () use ($data, $userId) {
            $idempotencyKey = $data['idempotency_key'] ?? null;

            // Idempotency check via persistent store
            if ($idempotencyKey) {
                $existing = IdempotencyKey::findActive($idempotencyKey);
                if ($existing && $existing->entity_id) {
                    $receipt = Receipt::find($existing->entity_id);
                    if ($receipt) {
                        return $receipt->load('items', 'register', 'creator');
                    }
                }
            }

            $receipt = Receipt::create([
                'id' => (string) Str::uuid(),
                'receipt_number' => $this->generateReceiptNumber($data['register_id']),
                'register_id' => $data['register_id'],
                'created_by' => $userId,
                'total_amount' => $this->normalizeAmount($data['total_amount']),
                'status' => 'draft',
                'notes' => $data['notes'] ?? null,
                'idempotency_key' => $idempotencyKey ?? (string) Str::uuid(),
                'lock_version' => 0,
                'version' => 1,
            ]);

            $this->syncItems($receipt, $data['items']);
            $receipt->load('items');

            // Build after state
            $afterState = [
                'receipt_number' => $receipt->receipt_number,
                'total_amount' => $receipt->total_amount,
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

            // Append event (source of truth)
            $this->eventStore->appendReceiptEvent(
                receiptId: $receipt->id,
                eventType: ReceiptEvent::RECEIPT_CREATED,
                afterState: $afterState,
                contextSnapshot: $this->captureContext(),
                lockVersion: 0,
                idempotencyKey: $receipt->idempotency_key,
                causedBy: $userId,
            );

            return $receipt->load('items', 'register', 'creator');
        });
    }

    /**
     * Update a draft receipt.
     * Only draft receipts can be updated.
     * No event created for draft updates (not yet financial).
     */
    public function update(Receipt $receipt, array $data): Receipt
    {
        return DB::transaction(function () use ($receipt, $data) {
            $this->assertNotLocked($receipt);

            if ($receipt->status !== 'draft') {
                throw new \RuntimeException('لا يمكن تعديل وصل إلا في حالة المسودة');
            }

            $updated = $receipt->where('id', $receipt->id)
                ->where('lock_version', $receipt->lock_version)
                ->where('status', 'draft')
                ->update([
                    'total_amount' => $this->normalizeAmount($data['total_amount']),
                    'notes' => $data['notes'] ?? null,
                    'lock_version' => $receipt->lock_version + 1,
                ]);

            if ($updated === 0) {
                throw new \RuntimeException('تم تعديل هذا الوصل بواسطة مستخدم آخر أو أنه في حالة غير قابلة للتعديل');
            }

            $receipt->refresh();

            $receipt->items()->delete();
            $this->syncItems($receipt, $data['items']);
            return $receipt->load('items', 'register', 'creator');
        });
    }

    /**
     * Issue a receipt (draft → issued).
     * Creates receipt_issued event.
     */
    public function issue(Receipt $receipt, string $userId): Receipt
    {
        return DB::transaction(function () use ($receipt, $userId) {
            $receipt->load('items', 'register');

            if ($receipt->status !== 'draft') {
                throw new \RuntimeException('لا يمكن إصدار وصل إلا من حالة المسودة');
            }

            foreach ($receipt->register->fields as $field) {
                if ($field->is_required) {
                    $hasValue = $receipt->items->firstWhere('field_id', $field->id);
                    if (!$hasValue || (is_null($hasValue->text_value) && is_null($hasValue->amount))) {
                        throw new \Exception('جميع الحقول المطلوبة يجب أن تكون مملوءة قبل الترحيل');
                    }
                }
            }

            $payload = $this->buildQrPayload($receipt);
            $lockVersion = $receipt->lock_version + 1;

            // Build states
            $beforeState = [
                'status' => 'draft',
                'lock_version' => $receipt->lock_version,
            ];

            $afterState = [
                'receipt_number' => $receipt->receipt_number,
                'total_amount' => $receipt->total_amount,
                'status' => 'issued',
                'notes' => $receipt->notes,
                'approved_by' => $userId,
                'qr_payload' => $payload,
                'items' => $receipt->items->map(fn($item) => [
                    'field_id' => $item->field_id,
                    'amount' => $item->amount,
                    'text_value' => $item->text_value,
                    'field_name_snapshot' => $item->field_name_snapshot,
                    'label_ar_snapshot' => $item->label_ar_snapshot,
                ])->toArray(),
            ];

            // Append event (source of truth)
            $this->eventStore->appendReceiptEvent(
                receiptId: $receipt->id,
                eventType: ReceiptEvent::RECEIPT_ISSUED,
                afterState: $afterState,
                beforeState: $beforeState,
                contextSnapshot: $this->captureContext(),
                lockVersion: $lockVersion,
                causedBy: $userId,
            );

            // Update denormalized cache
            $updated = $receipt->where('id', $receipt->id)
                ->where('lock_version', $receipt->lock_version)
                ->where('status', 'draft')
                ->update([
                    'status' => 'issued',
                    'approved_by' => $userId,
                    'qr_payload' => json_encode($payload),
                    'lock_version' => $lockVersion,
                ]);

            if ($updated === 0) {
                throw new \RuntimeException('فشل إصدار الوصل - إما أنه تم تعديله أو تغيير حالته بواسطة مستخدم آخر');
            }

            $receipt->refresh();
            return $receipt->load('items', 'register', 'creator', 'approver');
        });
    }

    /**
     * Cancel an issued receipt.
     * Creates receipt_cancelled event.
     */
    public function cancel(Receipt $receipt, string $reason, string $userId): Receipt
    {
        return DB::transaction(function () use ($receipt, $reason, $userId) {
            $this->assertNotLocked($receipt);

            $receipt->load('items', 'register');

            if ($receipt->status !== 'issued') {
                throw new \RuntimeException('لا يمكن إلغاء وصل إلا إذا كان مرحّلاً');
            }

            $lockVersion = $receipt->lock_version + 1;

            $beforeState = [
                'status' => 'issued',
                'lock_version' => $receipt->lock_version,
                'total_amount' => $receipt->total_amount,
            ];

            $afterState = [
                'receipt_number' => $receipt->receipt_number,
                'total_amount' => $receipt->total_amount,
                'status' => 'cancelled',
                'cancelled_by' => $userId,
                'cancel_reason' => $reason,
                'cancelled_at' => now()->toDateTimeString(),
            ];

            // Append event (source of truth)
            $this->eventStore->appendReceiptEvent(
                receiptId: $receipt->id,
                eventType: ReceiptEvent::RECEIPT_CANCELLED,
                afterState: $afterState,
                beforeState: $beforeState,
                contextSnapshot: $this->captureContext(),
                lockVersion: $lockVersion,
                causedBy: $userId,
                reason: $reason,
            );

            // Update denormalized cache
            $updated = $receipt->where('id', $receipt->id)
                ->where('lock_version', $receipt->lock_version)
                ->where('status', 'issued')
                ->update([
                    'status' => 'cancelled',
                    'cancelled_at' => now(),
                    'cancelled_by' => $userId,
                    'cancel_reason' => $reason,
                    'lock_version' => $lockVersion,
                ]);

            if ($updated === 0) {
                throw new \RuntimeException('لا يمكن إلغاء هذا الوصل - إما أنه مُلغى بالفعل أو تم تعديله بواسطة مستخدم آخر');
            }

            $receipt->refresh();
            return $receipt->load('items', 'register', 'creator', 'canceller');
        });
    }

    /**
     * Revise an issued receipt.
     * Creates receipt_revised event.
     */
    public function revise(Receipt $receipt, array $data, string $userId): Receipt
    {
        return DB::transaction(function () use ($receipt, $data, $userId) {
            $this->assertNotLocked($receipt);

            $receipt->load('items', 'register');

            if (!in_array($receipt->status, ['issued', 'printed'])) {
                throw new \RuntimeException('لا يمكن تعديل وصل إلا إذا كان مرحّلاً');
            }

            $oldSnapshot = $this->snapshot($receipt);
            $lockVersion = $receipt->lock_version + 1;
            $newVersion = $receipt->version + 1;

            // Build states
            $beforeState = [
                'status' => 'issued',
                'version' => $receipt->version,
                'total_amount' => $receipt->total_amount,
                'notes' => $receipt->notes,
                'lock_version' => $receipt->lock_version,
            ];

            $afterState = [
                'receipt_number' => $receipt->receipt_number,
                'total_amount' => $this->normalizeAmount($data['total_amount']),
                'status' => 'issued',
                'notes' => $data['notes'] ?? $receipt->notes,
                'version' => $newVersion,
                'items' => collect($data['items'])->map(fn($item) => [
                    'field_id' => $item['field_id'],
                    'amount' => $item['amount'] ?? null,
                    'text_value' => $item['value'] ?? null,
                ])->toArray(),
            ];

            // Append event (source of truth)
            $this->eventStore->appendReceiptEvent(
                receiptId: $receipt->id,
                eventType: ReceiptEvent::RECEIPT_REVISED,
                afterState: $afterState,
                beforeState: $beforeState,
                contextSnapshot: $this->captureContext(),
                lockVersion: $lockVersion,
                causedBy: $userId,
                reason: $data['reason'],
            );

            // Update denormalized cache
            $updated = $receipt->where('id', $receipt->id)
                ->where('lock_version', $receipt->lock_version)
                ->where('status', 'issued')
                ->update([
                    'total_amount' => $this->normalizeAmount($data['total_amount']),
                    'notes' => $data['notes'] ?? null,
                    'version' => $newVersion,
                    'lock_version' => $lockVersion,
                ]);

            if ($updated === 0) {
                throw new \RuntimeException('لا يمكن تعديل هذا الوصل - إما أنه غير مرحّل أو تم تعديله بواسطة مستخدم آخر');
            }

            $receipt->refresh();

            $receipt->items()->delete();
            $this->syncItems($receipt, $data['items']);

            $receipt->refresh()->load('items');
            $newSnapshot = $this->snapshot($receipt);

            ReceiptRevision::create([
                'id' => (string) Str::uuid(),
                'receipt_id' => $receipt->id,
                'version' => $receipt->version,
                'revised_by' => $userId,
                'reason' => $data['reason'],
                'old_snapshot' => $oldSnapshot,
                'new_snapshot' => $newSnapshot,
                'created_at' => now(),
            ]);

            return $receipt->load('items', 'register', 'creator', 'revisions');
        });
    }

    /**
     * Record a print event.
     * Creates receipt_printed event.
     */
    public function recordPrint(Receipt $receipt, string $userId): Receipt
    {
        return DB::transaction(function () use ($receipt, $userId) {
            if (!in_array($receipt->status, ['issued', 'printed'])) {
                throw new \RuntimeException('لا يمكن طباعة وصل إلا إذا كان مرحّلاً أو مطبوعاً مسبقاً');
            }

            $lockVersion = $receipt->lock_version + 1;

            $beforeState = [
                'status' => $receipt->status,
                'printed_at' => $receipt->printed_at?->toDateTimeString(),
            ];

            $afterState = [
                'status' => 'printed',
                'printed_at' => now()->toDateTimeString(),
            ];

            $this->eventStore->appendReceiptEvent(
                receiptId: $receipt->id,
                eventType: ReceiptEvent::RECEIPT_PRINTED,
                afterState: $afterState,
                beforeState: $beforeState,
                lockVersion: $lockVersion,
                causedBy: $userId,
            );

            $receipt->where('id', $receipt->id)
                ->where('lock_version', $receipt->lock_version)
                ->update([
                    'status' => 'printed',
                    'printed_at' => now(),
                    'lock_version' => $lockVersion,
                ]);

            return $receipt->fresh();
        });
    }

    /**
     * Replay receipt state from events.
     * Proves state = function(events).
     */
    public function replayReceiptState(string $receiptId): array
    {
        $events = $this->eventStore->getReceiptEvents($receiptId);

        $state = [
            'status' => 'draft',
            'total_amount' => '0.000',
            'version' => 1,
            'lock_version' => 0,
            'notes' => null,
            'items' => [],
        ];

        foreach ($events as $event) {
            match ($event['event_type']) {
                ReceiptEvent::RECEIPT_CREATED => $this->applyReceiptCreated($state, $event),
                ReceiptEvent::RECEIPT_ISSUED => $this->applyReceiptIssued($state, $event),
                ReceiptEvent::RECEIPT_REVISED => $this->applyReceiptRevised($state, $event),
                ReceiptEvent::RECEIPT_CANCELLED => $this->applyReceiptCancelled($state, $event),
                ReceiptEvent::RECEIPT_PRINTED => $this->applyReceiptPrinted($state, $event),
                default => null,
            };
        }

        return $state;
    }

    public function generatePdf(Receipt $receipt): string
    {
        $receipt->load('items', 'register', 'creator');
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.receipt', [
            'receipt' => $receipt,
            'deptName' => config('app.dept_name_ar', 'الدائرة المالية'),
        ]);
        return $pdf->output();
    }

    public function generateQrImage(Receipt $receipt): string
    {
        $receipt->load('items', 'register', 'creator');
        $payload = $this->buildQrPayload($receipt);
        $qrText = json_encode($payload);
        $svg = $this->generateQrSvg($qrText);
        return $svg;
    }

    // ============================================================
    // EVENT APPLICATORS (Internal)
    // ============================================================

    protected function applyReceiptCreated(array &$state, array $event): void
    {
        $after = $event['after_state'];
        $state['status'] = 'draft';
        $state['total_amount'] = $after['total_amount'] ?? '0.000';
        $state['version'] = 1;
        $state['lock_version'] = $event['lock_version'] ?? 0;
        $state['notes'] = $after['notes'] ?? null;
        $state['items'] = $after['items'] ?? [];
    }

    protected function applyReceiptIssued(array &$state, array $event): void
    {
        $state['status'] = 'issued';
        $state['lock_version'] = $event['lock_version'] ?? $state['lock_version'] + 1;
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

    // ============================================================
    // HELPERS
    // ============================================================

    protected function generateReceiptNumber(string $registerId): string
    {
        $register = Register::lockForUpdate()->findOrFail($registerId);
        $register->increment('current_sequence');
        return sprintf('%s-%d-%06d', $register->code, $register->fiscal_year, $register->current_sequence);
    }

    protected function assertNotLocked(Receipt $receipt): void
    {
        $fresh = $receipt->fresh();
        if ($fresh->lock_version !== $receipt->lock_version) {
            throw new \RuntimeException('تم تعديل هذا الوصل بواسطة مستخدم آخر. يرجى تحديث البيانات والمحاولة مرة أخرى');
        }
    }

    protected function syncItems(Receipt $receipt, array $items): void
    {
        foreach ($items as $item) {
            $field = \App\Models\RegisterField::find($item['field_id']);
            if (!$field) {
                continue;
            }
            ReceiptItem::create([
                'id' => (string) Str::uuid(),
                'receipt_id' => $receipt->id,
                'field_id' => $field->id,
                'field_name_snapshot' => $field->name,
                'label_ar_snapshot' => $field->label_ar,
                'amount' => $field->is_financial ? $this->normalizeAmount($item['amount'] ?? null) : null,
                'text_value' => !$field->is_financial ? ($item['value'] ?? null) : null,
            ]);
        }
    }

    protected function normalizeAmount(mixed $amount): string
    {
        if ($amount === null || $amount === '') {
            return '0.000';
        }
        if (is_numeric($amount)) {
            $str = (string) $amount;
            if (is_float($amount) || is_int($amount)) {
                return number_format((float) $amount, $this->ctx->scale(), '.', '');
            }
            if (str_contains($str, '.')) {
                $parts = explode('.', $str);
                $decimal = str_pad(substr($parts[1], 0, $this->ctx->scale()), $this->ctx->scale(), '0');
                return $parts[0] . '.' . $decimal;
            }
            return $str . '.000';
        }
        return '0.000';
    }

    protected function buildQrPayload(Receipt $receipt): array
    {
        $num = $receipt->receipt_number;
        $amt = $this->normalizeAmount($receipt->total_amount);
        $date = $receipt->created_at->toDateString();
        $reg = $receipt->register->code;
        $usr = $receipt->creator->username;
        $hash = substr(hash('sha256', $num . $amt . $date . config('app.key')), 0, 16);
        return [
            'sys' => 'GFRC',
            'num' => $num,
            'amt' => $amt,
            'date' => $date,
            'reg' => $reg,
            'usr' => $usr,
            'hash' => $hash,
        ];
    }

    protected function snapshot(Receipt $receipt): array
    {
        return [
            'receipt' => $receipt->toArray(),
            'items' => $receipt->items->toArray(),
        ];
    }

    public static function generateQrSvg(string $text): string
    {
        $size = 200;
        $cells = 25;
        $cellSize = $size / $cells;
        $hash = md5($text);
        $rects = '';
        for ($r = 0; $r < $cells; $r++) {
            for ($c = 0; $c < $cells; $c++) {
                $idx = ($r * $cells + $c) % 32;
                $on = hexdec(substr($hash, $idx % 32, 1)) % 2 === 0;
                if ($on) {
                    $x = $c * $cellSize;
                    $y = $r * $cellSize;
                    $rects .= "<rect x=\"{$x}\" y=\"{$y}\" width=\"{$cellSize}\" height=\"{$cellSize}\" fill=\"black\"/>";
                }
            }
        }
        return '<svg xmlns="http://www.w3.org/2000/svg" width="' . $size . '" height="' . $size . '" viewBox="0 0 ' . $size . ' ' . $size . '">' . $rects . '</svg>';
    }

    public static function amountToWords(string $amount): string
    {
        $ones = ['','واحد','اثنان','ثلاثة','أربعة','خمسة','ستة','سبعة','ثمانية','تسعة','عشرة','أحد عشر','اثنا عشر','ثلاثة عشر','أربعة عشر','خمسة عشر','ستة عشر','سبعة عشر','ثمانية عشر','تسعة عشر'];
        $tens = ['','','عشرون','ثلاثون','أربعون','خمسون','ستون','سبعون','ثمانون','تسعون'];
        $hundreds = ['','مائة','مئتان','ثلاثمائة','أربعمائة','خمسمائة','ستمائة','سبعمائة','ثمانمائة','تسعمائة'];
        $parts = [];
        $amount = (int) bcadd($amount, '0', 0);
        if ($amount == 0) return 'صفر دينار';
        if ($amount >= 1000000000) {
            $b = intdiv($amount, 1000000000);
            $parts[] = self::amountToWords((string) $b) . ' مليار';
            $amount %= 1000000000;
        }
        if ($amount >= 1000000) {
            $m = intdiv($amount, 1000000);
            $parts[] = self::amountToWords((string) $m) . ' مليون';
            $amount %= 1000000;
        }
        if ($amount >= 1000) {
            $k = intdiv($amount, 1000);
            $parts[] = ($k == 1 ? 'ألف' : ($k == 2 ? 'ألفان' : self::amountToWords((string) $k) . ' آلاف'));
            $amount %= 1000;
        }
        if ($amount > 0) {
            $h = intdiv($amount, 100);
            $t = intdiv(($amount % 100), 10);
            $o = $amount % 10;
            $sub = [];
            if ($h > 0) $sub[] = $hundreds[$h];
            if ($t >= 2) {
                if ($o > 0) $sub[] = $ones[$o] . ' و' . $tens[$t];
                else $sub[] = $tens[$t];
            } elseif ($t == 1) {
                $sub[] = $ones[10 + $o];
            } elseif ($o > 0) {
                $sub[] = $ones[$o];
            }
            $parts[] = implode(' و', $sub);
        }
        return implode(' و', array_filter($parts)) . ' دينار';
    }

    protected function captureContext(): array
    {
        return [
            'scale' => $this->ctx->scale(),
            'rounding_mode' => $this->ctx->roundingMode(),
            'strict_mode' => $this->ctx->strictMode(),
            'max_value' => $this->ctx->maxValue(),
            'division_by_zero_policy' => $this->ctx->divisionByZeroPolicy(),
            'fee_snapshots' => $this->ctx->feeSnapshots(),
        ];
    }
}
