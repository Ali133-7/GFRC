<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Receipt\CancelReceiptRequest;
use App\Http\Requests\Receipt\IssueReceiptRequest;
use App\Http\Requests\Receipt\ReviseReceiptRequest;
use App\Http\Requests\Receipt\StoreReceiptRequest;
use App\Http\Requests\Receipt\UpdateReceiptRequest;
use App\Http\Resources\ReceiptResource;
use App\Models\Receipt;
use App\Services\ReceiptService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class ReceiptController extends ApiController
{
    public function __construct(protected ReceiptService $receiptService) {}

    public function index(): JsonResponse
    {
        $this->authorize('view', Receipt::class);
        $query = Receipt::with('register', 'creator', 'items')
            ->when(request('register_id'), fn($q, $v) => $q->where('register_id', $v))
            ->when(request('register_id') && !request('status'), fn($q) => $q->where('status', '!=', 'cancelled'))
            ->when(request('status'), fn($q, $v) => $q->where('status', $v))
            ->when(request('date_from'), fn($q, $v) => $q->whereDate('created_at', '>=', $v))
            ->when(request('date_to'), fn($q, $v) => $q->whereDate('created_at', '<=', $v))
            ->when(request('search'), function ($q, $s) {
                $q->where(function ($sub) use ($s) {
                    $sub->where('receipt_number', 'like', "%{$s}%")
                        ->orWhereHas('items', fn($i) => $i->where('text_value', 'like', "%{$s}%"));
                });
            })
            ->when(!request('register_id') && !auth()->user()->hasRole('super_admin') && !auth()->user()->can('view-all-receipts'), 
                fn($q) => $q->where('created_by', auth()->id()))
            ->orderByDesc('created_at');

        $receipts = $query->paginate(request('per_page', 25));

        return $this->success(
            ReceiptResource::collection($receipts),
            '',
            $this->paginationMeta($receipts)
        );
    }

    public function store(StoreReceiptRequest $request): JsonResponse
    {
        $receipt = $this->receiptService->create($request->validated(), auth()->id());
        return $this->success(new ReceiptResource($receipt), 'تم إنشاء الوصل بنجاح');
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('view', Receipt::class);
        $receipt = Receipt::with('register', 'creator', 'items', 'revisions.reviser')->findOrFail($id);
        
        // Row-level access: non-admins can only view their own receipts
        if (!auth()->user()->hasRole('super_admin') && !auth()->user()->can('view-all-receipts')) {
            if ($receipt->created_by !== auth()->id()) {
                abort(403, 'غير مصرح بعرض هذا الوصل');
            }
        }

        return $this->success(new ReceiptResource($receipt));
    }

    public function update(UpdateReceiptRequest $request, string $id): JsonResponse
    {
        $receipt = Receipt::findOrFail($id);
        if ($receipt->status !== 'draft' && $receipt->status !== 'pending') {
            return $this->error('لا يمكن تعديل الوصل إلا في حالة المسودة أو قيد الانتظار', [], 'INVALID_STATUS');
        }
        $receipt = $this->receiptService->update($receipt, $request->validated());
        return $this->success(new ReceiptResource($receipt), 'تم تحديث الوصل بنجاح');
    }

    public function issue(IssueReceiptRequest $request, string $id): JsonResponse
    {
        $receipt = Receipt::findOrFail($id);
        $this->authorize('issue', $receipt);
        if ($receipt->status !== 'draft' && $receipt->status !== 'pending') {
            return $this->error('لا يمكن ترحيل الوصل في هذه الحالة', [], 'INVALID_STATUS');
        }
        $receipt = $this->receiptService->issue($receipt, auth()->id());
        return $this->success(new ReceiptResource($receipt), 'تم ترحيل الوصل بنجاح');
    }

    public function cancel(CancelReceiptRequest $request, string $id): JsonResponse
    {
        $receipt = Receipt::findOrFail($id);
        $this->authorize('cancel', $receipt);
        if ($receipt->status === 'cancelled') {
            return $this->error('الوصل ملغى مسبقاً', [], 'ALREADY_CANCELLED');
        }
        if ($receipt->status === 'draft') {
            return $this->error('لا يمكن إلغاء وصل غير مرحّل', [], 'INVALID_STATUS', 422);
        }
        try {
            $receipt = $this->receiptService->cancel($receipt, $request->input('reason'), auth()->id());
            return $this->success(new ReceiptResource($receipt), 'تم إلغاء الوصل بنجاح');
        } catch (\RuntimeException $e) {
            return $this->error($e->getMessage(), [], 'CANCEL_FAILED', 422);
        }
    }

    public function revise(ReviseReceiptRequest $request, string $id): JsonResponse
    {
        $receipt = Receipt::findOrFail($id);
        $this->authorize('revise', $receipt);
        if ($receipt->status !== 'issued' && $receipt->status !== 'printed') {
            return $this->error('لا يمكن تعديل الوصل إلا بعد الترحيل', [], 'INVALID_STATUS');
        }
        $receipt = $this->receiptService->revise($receipt, $request->validated(), auth()->id());
        return $this->success(new ReceiptResource($receipt), 'تم تعديل الوصل بنجاح');
    }

    public function print(string $id): Response
    {
        $this->authorize('print', Receipt::class);
        $receipt = Receipt::findOrFail($id);
        $receipt->update(['printed_at' => now()]);
        $pdf = $this->receiptService->generatePdf($receipt);
        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => "inline; filename={$receipt->receipt_number}.pdf",
        ]);
    }

    public function qr(string $id): Response
    {
        $this->authorize('view', Receipt::class);
        $receipt = Receipt::findOrFail($id);
        $svg = $this->receiptService->generateQrImage($receipt);
        return response($svg, 200, ['Content-Type' => 'image/svg+xml']);
    }

    public function revisions(string $id): JsonResponse
    {
        $this->authorize('view', Receipt::class);
        $receipt = Receipt::with('revisions.reviser')->findOrFail($id);
        return $this->success(\App\Http\Resources\ReceiptRevisionResource::collection($receipt->revisions));
    }
}
