<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Resource for bulk operation results.
 *
 * This resource transforms the result of bulk operations into a standardized
 * JSON response format that includes processing statistics, performance metrics,
 * and detailed error information.
 *
 * Enhanced Response Format:
 * {
 *   "message": "X tasks [action] successfully (Y version conflicts encountered)",
 *   "processed": X,           // Number of tasks successfully processed
 *   "total": Z,               // Total number of tasks in the request
 *   "conflicts": Y,           // Number of version conflicts encountered
 *   "success_rate": 85.5,     // Percentage of successful operations
 *   "errors": [...],          // Array of error messages for failed operations (only if errors exist)
 *   "performance": {          // Performance metrics (optional)
 *     "processing_time": 1.23,
 *     "average_time_per_task": 0.12
 *   }
 * }
 *
 * @property-read string $message
 * @property-read int|null $processed
 * @property-read int|null $total
 * @property-read int|null $conflicts
 * @property-read array|null $errors
 * @property-read float|null $processing_time
 * @property-read array|null $debug_info
 * @property mixed $action
 * @property mixed $conflicted_tasks
 */
class BulkOperationResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $processed = $this->processed ?? 0;
        $total = $this->total ?? 0;
        $conflicts = $this->conflicts ?? 0;
        $errors = $this->errors ?? [];

        $response = [
            'action' => $this->action,
            'message' => $this->message,
            'processed_count' => $processed,
            'total_count' => $total,
            'conflict_count' => $conflicts,
            'success_rate' => $this->calculateSuccessRate($processed, $total),
            'errors' => $errors,
            'conflicted_tasks' => $this->conflicted_tasks,
        ];

        // Only include errors if they exist
        if (! empty($errors)) {
            $response['error_summary'] = $this->generateErrorSummary($errors);
        }

        // Add performance metrics if available
        if (isset($this->processing_time)) {
            $response['performance'] = [
                'processing_time' => round($this->processing_time, 3),
                'average_time_per_task' => $total > 0 ? round($this->processing_time / $total, 3) : 0,
            ];
        }

        // Add debug information in development
        if (config('app.debug') && isset($this->debug_info)) {
            $response['debug'] = $this->debug_info;
        }

        return $response;
    }

    /**
     * Calculate success rate as a percentage.
     */
    private function calculateSuccessRate(int $processed, int $total): float
    {
        if ($total === 0) {
            return 0.0;
        }

        return round($processed / $total * 100, 2);
    }

    /**
     * Generate a summary of errors by type.
     */
    private function generateErrorSummary(array $errors): array
    {
        $summary = [
            'authorization' => 0,
            'conflict' => 0,
            'not_found' => 0,
            'validation' => 0,
            'other' => 0,
        ];

        foreach ($errors as $error) {
            $errorLower = strtolower($error);

            if (str_contains($errorLower, 'not authorized') || str_contains($errorLower, 'unauthorized')) {
                $summary['authorization']++;
            } elseif (str_contains($errorLower, 'version conflict') || str_contains($errorLower, 'conflict')) {
                $summary['conflict']++;
            } elseif (str_contains($errorLower, 'not found')) {
                $summary['not_found']++;
            } elseif (str_contains($errorLower, 'validation') || str_contains($errorLower, 'invalid')) {
                $summary['validation']++;
            } else {
                $summary['other']++;
            }
        }

        // Remove categories with zero errors for cleaner response
        return array_filter($summary, static fn ($count) => $count > 0);
    }
}
