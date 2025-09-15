<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class ApiResponseService
{
    public function error(
        string $message,
        int $statusCode = Response::HTTP_BAD_REQUEST,
        array $errors = []
    ): JsonResponse {
        $response = [
            'success' => false,
            'message' => $message,
            'timestamp' => $this->getTimestamp(),
        ];

        if (! empty($errors)) {
            $response['errors'] = $errors;
        }

        return response()->json($response, $statusCode);
    }

    public function success($data = null, int $statusCode = Response::HTTP_OK, array $meta = []): JsonResponse
    {
        $response = [
            'success' => true,
            'data' => $data,
            'timestamp' => $this->getTimestamp(),
        ];

        if (! empty($meta)) {
            $response['meta'] = $meta;
        }

        return response()->json($response, $statusCode);
    }

    private function getTimestamp(): string
    {
        return Carbon::now()->toIso8601String();
    }
}
