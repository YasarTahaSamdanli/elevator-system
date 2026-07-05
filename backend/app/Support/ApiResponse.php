<?php

namespace App\Support;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Pagination\LengthAwarePaginator;

class ApiResponse
{
    /**
     * @param  array<string, mixed>  $meta
     */
    public static function success(
        mixed $data = null,
        string $message = 'Operation completed successfully.',
        array $meta = [],
        int $status = 200,
    ): JsonResponse {
        return response()->json([
            'success' => true,
            'data' => $data,
            'message' => $message,
            'meta' => $meta,
        ], $status);
    }

    /**
     * Paginated list response following the meta.pagination contract
     * (SOLUTION_ARCHITECTURE.md §12).
     *
     * @param  LengthAwarePaginator<int, covariant \Illuminate\Database\Eloquent\Model>  $paginator
     * @param  class-string<JsonResource>  $resource
     */
    public static function paginated(
        LengthAwarePaginator $paginator,
        string $resource,
        string $message = 'Operation completed successfully.',
    ): JsonResponse {
        return self::success(
            data: $resource::collection($paginator->getCollection()),
            message: $message,
            meta: [
                'pagination' => [
                    'page' => $paginator->currentPage(),
                    'per_page' => $paginator->perPage(),
                    'total' => $paginator->total(),
                    'total_pages' => $paginator->lastPage(),
                ],
            ],
        );
    }

    /**
     * @param  array<string, mixed>  $details
     */
    public static function error(
        string $message,
        string $code,
        array $details = [],
        int $status = 400,
    ): JsonResponse {
        return response()->json([
            'success' => false,
            'message' => $message,
            'error' => [
                'code' => $code,
                'details' => $details,
            ],
        ], $status);
    }
}
