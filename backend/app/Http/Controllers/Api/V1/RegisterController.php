<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Register\ReorderRegisterFieldsRequest;
use App\Http\Requests\Register\StoreRegisterFieldRequest;
use App\Http\Requests\Register\StoreRegisterRequest;
use App\Http\Requests\Register\UpdateRegisterFieldRequest;
use App\Http\Requests\Register\UpdateRegisterRequest;
use App\Http\Resources\RegisterFieldResource;
use App\Http\Resources\RegisterResource;
use App\Models\Register;
use App\Models\RegisterField;
use App\Services\RegisterService;
use Illuminate\Http\JsonResponse;

class RegisterController extends ApiController
{
    public function __construct(protected RegisterService $registerService) {}

    public function index(): JsonResponse
    {
        $this->authorize('view', Register::class);
        $registers = Register::with('fields')
            ->when(request('search'), fn($q, $s) => $q->where('name_ar', 'like', "%{$s}%"))
            ->paginate(request('per_page', 25));

        return $this->success(
            RegisterResource::collection($registers),
            '',
            $this->paginationMeta($registers)
        );
    }

    public function store(StoreRegisterRequest $request): JsonResponse
    {
        $this->authorize('manage', Register::class);
        $register = $this->registerService->create($request->validated(), auth()->id());
        return $this->success(new RegisterResource($register), 'تم إنشاء السجل بنجاح');
    }

    public function show(string $id): JsonResponse
    {
        $this->authorize('view', Register::class);
        $register = Register::with('fields')->findOrFail($id);
        return $this->success(new RegisterResource($register));
    }

    public function update(UpdateRegisterRequest $request, string $id): JsonResponse
    {
        $this->authorize('manage', Register::class);
        $register = Register::findOrFail($id);
        $register = $this->registerService->update($register, $request->validated());
        return $this->success(new RegisterResource($register), 'تم تحديث السجل بنجاح');
    }

    public function fields(string $id): JsonResponse
    {
        $this->authorize('view', Register::class);
        $register = Register::findOrFail($id);
        return $this->success(RegisterFieldResource::collection($register->fields));
    }

    public function storeField(StoreRegisterFieldRequest $request, string $id): JsonResponse
    {
        $register = Register::findOrFail($id);
        $this->authorize('manageFields', $register);
        $field = $this->registerService->addField($register, $request->validated());
        return $this->success(new RegisterFieldResource($field), 'تم إضافة الحقل بنجاح');
    }

    public function updateField(UpdateRegisterFieldRequest $request, string $id, string $fieldId): JsonResponse
    {
        $field = RegisterField::where('register_id', $id)->findOrFail($fieldId);
        $this->authorize('manageFields', $field->register);
        $field = $this->registerService->updateField($field, $request->validated());
        return $this->success(new RegisterFieldResource($field), 'تم تحديث الحقل بنجاح');
    }

    public function destroyField(string $id, string $fieldId): JsonResponse
    {
        $field = RegisterField::where('register_id', $id)->findOrFail($fieldId);
        $this->authorize('manageFields', $field->register);
        $field->delete(); // soft delete via SoftDeletes trait
        return $this->success([], 'تم حذف الحقل بنجاح');
    }

    public function reorderFields(ReorderRegisterFieldsRequest $request, string $id): JsonResponse
    {
        $register = Register::findOrFail($id);
        $this->authorize('manageFields', $register);
        $this->registerService->reorderFields($register, $request->input('fields'));
        return $this->success([], 'تم إعادة الترتيب بنجاح');
    }
}
