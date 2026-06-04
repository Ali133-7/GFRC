<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowController extends ApiController
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('view', Workflow::class);

        $query = Workflow::with(['register', 'creator'])
            ->when($request->register_id, fn($q, $id) => $q->where('register_id', $id))
            ->when($request->search, fn($q, $s) => $q->where(function ($sq) use ($s) {
                $sq->where('name_ar', 'like', "%{$s}%")
                   ->orWhere('name_en', 'like', "%{$s}%")
                   ->orWhere('code', 'like', "%{$s}%");
            }))
            ->when($request->has('is_active'), fn($q) => $q->where('is_active', $request->boolean('is_active')))
            ->when($request->boolean('has_active_version'), fn($q) => $q->whereHas('versions', fn($vq) => $vq->where('status', 'active')))
            ->orderBy('sort_order')
            ->orderBy('name_ar');

        if ($request->boolean('paginate')) {
            $paginated = $query->paginate($request->per_page ?? 25);
            return $this->success($paginated->items(), '', array_merge(
                $this->paginationMeta($paginated),
                ['total_count' => $paginated->total()]
            ));
        }

        return $this->success($query->get());
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Workflow::class);

        $data = $request->validate([
            'register_id' => 'required|string|exists:registers,id',
            'code' => 'required|string|max:50|unique:workflows,code',
            'name_ar' => 'required|string|max:200',
            'name_en' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'sort_order' => 'nullable|integer',
        ]);

        $data['created_by'] = $request->user()->id;
        $data['current_version'] = 1;

        $workflow = DB::transaction(function () use ($data) {
            $workflow = Workflow::create($data);

            // Create initial draft version
            WorkflowVersion::create([
                'workflow_id' => $workflow->id,
                'version' => 1,
                'status' => 'draft',
                'change_summary' => 'إنشاء Workflow جديد',
            ]);

            return $workflow;
        });

        return $this->success($workflow->load('versions'), 'تم إنشاء Workflow بنجاح', [], 201);
    }

    public function show(string $id): JsonResponse
    {
        $workflow = Workflow::with(['register', 'creator', 'versions' => fn($q) => $q->orderBy('version', 'desc')])
            ->findOrFail($id);

        $this->authorize('view', $workflow);

        return $this->success($workflow);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $workflow = Workflow::findOrFail($id);
        $this->authorize('update', $workflow);

        $data = $request->validate([
            'code' => 'sometimes|string|max:50|unique:workflows,code,' . $workflow->id,
            'name_ar' => 'sometimes|string|max:200',
            'name_en' => 'nullable|string|max:200',
            'description' => 'nullable|string',
            'icon' => 'nullable|string|max:50',
            'is_active' => 'sometimes|boolean',
            'sort_order' => 'nullable|integer',
        ]);

        $workflow->update($data);

        return $this->success($workflow->fresh()->load('versions'));
    }

    public function destroy(string $id): JsonResponse
    {
        $workflow = Workflow::findOrFail($id);
        $this->authorize('delete', $workflow);

        $workflow->delete();

        return $this->success([], 'تم حذف Workflow بنجاح');
    }
}
