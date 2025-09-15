<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Exceptions\TagDeletionException;
use App\Http\Requests\StoreTagRequest;
use App\Http\Requests\UpdateTagRequest;
use App\Http\Resources\TagCollection;
use App\Http\Resources\TagResource;
use App\Models\Tag;
use App\Services\TagService;
use Exception;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class TagController extends BaseApiController
{
    use AuthorizesRequests;

    protected TagService $tagService;

    public function __construct(TagService $tagService)
    {
        $this->tagService = $tagService;
    }

    /**
     * Display a listing of tags.
     */
    public function index(Request $request): TagCollection
    {
        $this->authorize('viewAny', Tag::class);

        $query = Tag::query();

        // Include task counts (only admins can see task counts)
        if ($request->boolean('with_counts') && $request->user()->can('viewWithCounts', Tag::class)) {
            $query->withCount('tasks');
        }

        // Include related tasks (only admins can see related tasks)
        if ($request->boolean('with_tasks') && $request->user()->can('viewWithTasks', Tag::class)) {
            $query->with(['tasks' => function ($q) use ($request): void {
                $q->with('assignedUser');

                // Non-admin users can only see their assigned tasks
                if (! $request->user()->isAdmin()) {
                    $q->where('assigned_to', $request->user()->id);
                }
            },
            ]);
        }

        // Search by name
        if ($request->has('search')) {
            $query->where('name', 'LIKE', '%'.$request->search.'%');
        }

        // Filter by color
        if ($request->has('color')) {
            $query->where('color', $request->color);
        }

        // Order by
        $sortBy = $request->get('sort_by', 'name');
        $sortDirection = $request->get('sort_direction', 'asc');

        if (in_array($sortBy, ['name', 'created_at', 'updated_at'])) {
            $query->orderBy($sortBy, $sortDirection);
        } elseif ($sortBy === 'tasks_count' && $request->boolean('with_counts') && $request->user()->can('viewWithCounts', Tag::class)) {
            $query->orderBy('tasks_count', $sortDirection);
        }

        $tags = $query->get();

        return new TagCollection($tags);
    }

    /**
     * Store a newly created tag.
     */
    public function store(StoreTagRequest $request): JsonResponse
    {
        try {
            $this->authorize('create', Tag::class);

            $tag = $this->tagService->createTag($request->validated());

            return $this->successResponse(
                'Tag created successfully',
                new TagResource($tag),
                Response::HTTP_CREATED
            );
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse('Failed to create tag: '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Display the specified tag.
     */
    public function show(string $id): JsonResponse
    {
        try {
            $tag = Tag::with(['tasks' => function ($q): void {
                $q->with('assignedUser');
            },
            ])->findOrFail($id);

            $this->authorize('view', $tag);

            return $this->dataResponse(new TagResource($tag));
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Tag not found', Response::HTTP_NOT_FOUND);
        }
    }

    /**
     * Update the specified tag.
     */
    public function update(UpdateTagRequest $request, string $id): JsonResponse
    {
        try {
            $tag = Tag::query()->findOrFail($id);

            $this->authorize('update', $tag);

            $tag = $this->tagService->updateTag($tag, $request->validated());

            return $this->successResponse(
                'Tag updated successfully',
                new TagResource($tag)
            );
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Tag not found', Response::HTTP_NOT_FOUND);
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse('Failed to update tag: '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Remove the specified tag.
     */
    public function destroy(string $id): JsonResponse
    {
        try {
            $tag = Tag::query()->findOrFail($id);

            $this->authorize('delete', $tag);

            $this->tagService->deleteTag($tag);

            return $this->successResponse('Tag deleted successfully');
        } catch (ModelNotFoundException) {
            return $this->errorResponse('Tag not found', Response::HTTP_NOT_FOUND);
        } catch (TagDeletionException $e) {
            return $this->errorResponse($e->getMessage(), Response::HTTP_CONFLICT, $e->toArray());
        } catch (AuthorizationException $e) {
            throw $e;
        } catch (Exception $e) {
            return $this->errorResponse('Failed to delete tag: '.$e->getMessage(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
