<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class TagCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        // Check if the first item is an array (cached data) or object (Eloquent model)
        $isCachedData = $this->collection->isNotEmpty() && is_array($this->collection->first());

        if ($isCachedData) {
            // For cached data, return the arrays directly
            $data = $this->collection->map(function ($tag) {
                return [
                    'id' => $tag['id'],
                    'name' => $tag['name'],
                    'color' => $tag['color'],
                    'tasks_count' => $tag['tasks_count'] ?? null,
                    'created_at' => $tag['created_at'],
                    'updated_at' => $tag['updated_at'],
                ];
            });
        } else {
            // For Eloquent models, use the TagResource
            $data = $this->collection;
        }

        return [
            'data' => $data,
            'meta' => [
                'total' => $this->collection->count(),
                'most_used_tags' => $this->getMostUsedTags(),
            ],
        ];
    }

    /**
     * Get most used tags (those with tasks_count if available).
     */
    private function getMostUsedTags(): array
    {
        $filtered = $this->collection
            ->filter(function ($tag) {
                // Handle both object and array cases
                return is_object($tag) ? isset($tag->tasks_count) : (is_array($tag) && isset($tag['tasks_count']));
            })
            ->sortByDesc(function ($tag) {
                // Handle both object and array cases
                return is_object($tag) ? $tag->tasks_count : $tag['tasks_count'];
            })
            ->take(5);

        $result = [];
        foreach ($filtered as $tag) {
            // Handle both object and array cases
            $name = is_object($tag) ? $tag->name : $tag['name'];
            $tasksCount = is_object($tag) ? $tag->tasks_count : $tag['tasks_count'];

            $result[] = [
                'name' => $name,
                'tasks_count' => $tasksCount,
            ];
        }

        return $result;
    }
}
