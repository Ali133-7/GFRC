<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\FeeVersion;
use App\Models\OfficialFee;
use App\Models\OfficialFeeCategory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class OfficialFeeController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('manage-settings', OfficialFee::class);
        $query = OfficialFee::with('category')->active();

        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name_ar', 'like', "%{$search}%")
                  ->orWhere('name_en', 'like', "%{$search}%");
            });
        }

        $fees = $query->orderBy('name_ar')->paginate($request->input('per_page', 25));
        return $this->success($fees->items(), '', $this->paginationMeta($fees));
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('manage-settings', OfficialFee::class);
        $data = $request->validate([
            'category_id' => 'required|string|exists:official_fee_categories,id',
            'fee_code' => 'required|string|max:50|unique:official_fees,fee_code',
            'name_ar' => 'required|string|max:200',
            'name_en' => 'nullable|string|max:200',
            'amount' => 'required|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'is_active' => 'boolean',
        ]);

        $data['id'] = (string) Str::uuid();
        $fee = OfficialFee::create($data);

        // The execution engine resolves fees from fee_versions, so every fee must have a
        // version. Without this, set_fee/FeeEngine find no active version and fail.
        FeeVersion::create([
            'fee_id' => $fee->id,
            'version' => 1,
            'amount' => $data['amount'],
            'effective_from' => $data['effective_from'] ?? now(),
            'effective_to' => $data['effective_to'] ?? null,
        ]);

        return $this->success($fee->load('category'), 'تم إضافة الرسم بنجاح');
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('manage-settings', OfficialFee::class);
        $fee = OfficialFee::with('category')->findOrFail($id);
        return $this->success($fee);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $this->authorize('manage-settings', OfficialFee::class);
        $fee = OfficialFee::findOrFail($id);
        $data = $request->validate([
            'category_id' => 'sometimes|string|exists:official_fee_categories,id',
            'fee_code' => ['sometimes', 'string', 'max:50', Rule::unique('official_fees', 'fee_code')->ignore($fee->id)],
            'name_ar' => 'sometimes|string|max:200',
            'name_en' => 'nullable|string|max:200',
            'amount' => 'sometimes|numeric|min:0',
            'effective_from' => 'nullable|date',
            'effective_to' => 'nullable|date|after_or_equal:effective_from',
            'is_active' => 'boolean',
        ]);

        $fee->update($data);

        // Keep the engine's source of truth (fee_versions) in sync with the edited amount.
        if (array_key_exists('amount', $data)) {
            $version = $fee->feeVersions()->orderByDesc('version')->first();
            if ($version) {
                $version->update(['amount' => $data['amount']]);
            } else {
                FeeVersion::create([
                    'fee_id' => $fee->id,
                    'version' => 1,
                    'amount' => $data['amount'],
                    'effective_from' => $data['effective_from'] ?? now(),
                    'effective_to' => $data['effective_to'] ?? null,
                ]);
            }
        }

        return $this->success($fee->load('category'), 'تم تحديث الرسم بنجاح');
    }

    public function destroy(string $id): JsonResponse
    {
        $this->authorize('manage-settings', OfficialFee::class);
        $fee = OfficialFee::findOrFail($id);
        $fee->delete();
        return $this->success([], 'تم حذف الرسم بنجاح');
    }

    public function categories(): JsonResponse
    {
        $this->authorize('manage-settings', OfficialFee::class);
        $categories = OfficialFeeCategory::where('is_active', true)->orderBy('name_ar')->get();
        return $this->success($categories);
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $this->authorize('manage-settings', OfficialFee::class);
        $data = $request->validate([
            'name_ar' => 'required|string|max:200',
            'name_en' => 'nullable|string|max:200',
            'code' => 'required|string|max:50|unique:official_fee_categories,code',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $data['id'] = (string) Str::uuid();
        $category = OfficialFeeCategory::create($data);
        return $this->success($category, 'تم إضافة التصنيف بنجاح');
    }

    public function updateCategory(Request $request, string $id): JsonResponse
    {
        $this->authorize('manage-settings', OfficialFee::class);
        $category = OfficialFeeCategory::findOrFail($id);
        $data = $request->validate([
            'name_ar' => 'sometimes|string|max:200',
            'name_en' => 'nullable|string|max:200',
            'code' => ['sometimes', 'string', 'max:50', Rule::unique('official_fee_categories', 'code')->ignore($category->id)],
            'description' => 'nullable|string',
            'is_active' => 'boolean',
        ]);

        $category->update($data);
        return $this->success($category, 'تم تحديث التصنيف بنجاح');
    }

    public function destroyCategory(string $id): JsonResponse
    {
        $this->authorize('manage-settings', OfficialFee::class);
        $category = OfficialFeeCategory::findOrFail($id);
        $category->delete();
        return $this->success([], 'تم حذف التصنيف بنجاح');
    }
}
