<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\Audit\AuditLogIndexRequest;
use App\Http\Resources\AuditLogResource;
use App\Services\AuditService;
use Illuminate\Http\JsonResponse;

class AuditLogController extends ApiController
{
    public function __construct(protected AuditService $auditService) {}

    public function index(AuditLogIndexRequest $request): JsonResponse
    {
        $logs = $this->auditService->index($request->validated());
        return $this->success(
            AuditLogResource::collection($logs),
            '',
            $this->paginationMeta($logs)
        );
    }
}
