<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ApiController extends Controller
{
    protected function success($data = [], string $message = '', array $meta = [], int $status = 200): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'meta' => $meta,
        ], $status);
    }

    protected function error(string $message, array|int $errors = [], string $errorCode = 'ERROR', int $status = 422): JsonResponse
    {
        // Allow shorthand: $this->error($message, 400)
        if (is_int($errors)) {
            $status = $errors;
            $errors = [];
        }

        return response()->json([
            'success' => false,
            'message' => $message,
            'errors' => $errors,
            'error_code' => $errorCode,
        ], $status);
    }

    protected function paginationMeta($paginator): array
    {
        return [
            'pagination' => [
                'page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ];
    }
}
