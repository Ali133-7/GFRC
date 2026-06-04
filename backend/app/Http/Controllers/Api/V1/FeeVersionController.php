<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\FeeVersion;
use App\Models\OfficialFee;
use App\Services\FeeEngine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FeeVersionController extends ApiController
{
    protected FeeEngine $feeEngine;

    public function __construct(FeeEngine $feeEngine)
    {
        $this->feeEngine = $feeEngine;
    }

    public function index(string $feeId): JsonResponse
    {
        $fee = OfficialFee::findOrFail($feeId);
        $versions = $fee->feeVersions()->with('creator')->orderBy('version', 'desc')->get();
        return $this->success($versions);
    }

    public function store(Request $request, string $feeId): JsonResponse
    {
        $fee = OfficialFee::findOrFail($feeId);
        $this->authorize('update', $fee);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0',
            'effective_from' => 'required|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'change_reason' => 'nullable|string',
        ]);

        $latestVersion = $fee->feeVersions()->max('version') ?? 0;

        $version = FeeVersion::create([
            'fee_id' => $fee->id,
            'version' => $latestVersion + 1,
            'amount' => $data['amount'],
            'effective_from' => $data['effective_from'],
            'effective_to' => $data['effective_to'] ?? null,
            'change_reason' => $data['change_reason'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        // Update the parent fee's current amount and version
        $fee->update([
            'amount' => $data['amount'],
            'version' => $latestVersion + 1,
        ]);

        return $this->success($version->load('fee'), 'تم إنشاء نسخة الرسم بنجاح', [], 201);
    }

    public function resolve(string $code): JsonResponse
    {
        $feeVersion = $this->feeEngine->resolve($code);

        if (!$feeVersion) {
            return $this->error('الرمز غير موجود أو غير فعال حالياً', 404);
        }

        return $this->success([
            'fee_code' => $code,
            'fee_name' => $feeVersion->fee->name_ar,
            'amount' => $feeVersion->amount,
            'version' => $feeVersion->version,
            'effective_from' => $feeVersion->effective_from,
            'effective_to' => $feeVersion->effective_to,
        ]);
    }

    public function bulkResolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'codes' => 'required|array',
            'codes.*' => 'string',
            'as_of' => 'nullable|date',
        ]);

        $asOf = $request->input('as_of') ? now()->parse($request->input('as_of')) : null;
        $results = [];

        foreach ($data['codes'] as $code) {
            $feeVersion = $this->feeEngine->resolve($code, $asOf);
            $results[$code] = $feeVersion ? [
                'fee_name' => $feeVersion->fee->name_ar,
                'amount' => $feeVersion->amount,
                'version' => $feeVersion->version,
                'effective_from' => $feeVersion->effective_from,
            ] : null;
        }

        return $this->success($results);
    }

    /**
     * List all active official fees with their codes for selection in workflows.
     */
    public function listActive(): JsonResponse
    {
        $fees = OfficialFee::where('is_active', true)
            ->whereNotNull('fee_code')
            ->orderBy('name_ar')
            ->get(['id', 'fee_code', 'name_ar', 'name_en', 'amount']);

        return $this->success($fees);
    }
}
