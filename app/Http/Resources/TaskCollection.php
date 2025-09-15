<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TaskCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        $data = [
            'data' => $this->collection,
        ];

        // Add additional data (meta, links) to the response
        if (isset($this->additional['meta'])) {
            $data['meta'] = array_merge(
                [
                    'total' => $this->collection->count(),
                    'status_counts' => $this->getStatusCounts(),
                    'priority_counts' => $this->getPriorityCounts(),
                ],
                $this->additional['meta']
            );
        } else {
            $data['meta'] = [
                'total' => $this->collection->count(),
                'status_counts' => $this->getStatusCounts(),
                'priority_counts' => $this->getPriorityCounts(),
            ];
        }

        // Add links if they exist
        if (isset($this->additional['links'])) {
            $data['links'] = $this->additional['links'];
        }

        return $data;
    }

    /**
     * Get status distribution counts.
     */
    private function getStatusCounts(): array
    {
        return $this->collection
            ->groupBy('status')
            ->map(function ($group) {
                return $group->count();
            })
            ->toArray();
    }

    /**
     * Get priority distribution counts.
     */
    private function getPriorityCounts(): array
    {
        return $this->collection
            ->groupBy('priority')
            ->map(function ($group) {
                return $group->count();
            })
            ->toArray();
    }
}
