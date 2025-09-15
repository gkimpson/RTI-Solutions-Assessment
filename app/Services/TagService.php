<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\TagDeletionException;
use App\Models\Tag;

class TagService
{
    /**
     * Create a new tag
     */
    public function createTag(array $data): Tag
    {
        return Tag::create($data);
    }

    /**
     * Update an existing tag
     */
    public function updateTag(Tag $tag, array $data): Tag
    {
        $tag->update($data);

        return $tag->refresh();
    }

    /**
     * Delete a tag if it has no associated tasks
     *
     * @throws TagDeletionException
     */
    public function deleteTag(Tag $tag): bool
    {
        $taskCount = $tag->tasks()->count();
        if ($taskCount > 0) {
            throw new TagDeletionException(
                'Cannot delete tag that is still assigned to tasks',
                $taskCount
            );
        }

        return $tag->delete();
    }
}
