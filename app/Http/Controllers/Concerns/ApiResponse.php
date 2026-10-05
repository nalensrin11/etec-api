<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;

trait ApiResponse
{
    protected function success(mixed $data = null, string $message = 'Request completed successfully.', int $status = 200): JsonResponse
    {
        return response()->json(['message' => $message, 'status' => $status, 'data' => $data], $status);
    }

    protected function paginated(LengthAwarePaginator $paginator, string $message = 'Records retrieved successfully.'): JsonResponse
    {
        return $this->success($paginator->items(), $message)->setData([
            'message' => $message,
            'status' => 200,
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'from' => $paginator->firstItem(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'to' => $paginator->lastItem(),
                'total' => $paginator->total(),
            ],
        ]);
    }
}
