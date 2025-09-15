<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TaskCollection;
use App\Services\InputSanitizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

abstract class BaseApiController extends Controller
{
    /**
     * Create a standardized error response
     */
    protected function errorResponse(string $message, int $statusCode = Response::HTTP_BAD_REQUEST, array $extra = []): JsonResponse
    {
        $response = array_merge([
            'success' => false,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ], $extra);

        return response()->json($response, $statusCode, $this->getStandardHeaders());
    }

    /**
     * Create a standardized success response with optional data
     */
    protected function successResponse(string $message, $data = null, int $statusCode = Response::HTTP_OK): JsonResponse
    {
        $response = [
            'success' => true,
            'message' => $message,
            'timestamp' => now()->toISOString(),
        ];

        if ($data !== null) {
            $response['data'] = $data;
        }

        return response()->json($response, $statusCode, $this->getStandardHeaders());
    }

    /**
     * Create a standardized response without a message
     */
    protected function dataResponse($data, int $statusCode = Response::HTTP_OK): JsonResponse
    {
        if ($data instanceof TaskCollection) {
            return response()->json([
                'success' => true,
                'data' => $data->collection,
                'meta' => $data->additional['meta'],
                'links' => $data->additional['links'] ?? [],
                'timestamp' => now()->toISOString(),
            ], $statusCode, $this->getStandardHeaders());
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'timestamp' => now()->toISOString(),
        ], $statusCode, $this->getStandardHeaders());
    }

    /**
     * Get standard API response headers
     */
    protected function getStandardHeaders(): array
    {
        return array_merge([
            'Content-Type' => 'application/json',
            'X-API-Version' => 'v1',
            'Cache-Control' => 'no-cache, no-store, must-revalidate',
        ], InputSanitizationService::getSecurityHeaders());
    }
}
