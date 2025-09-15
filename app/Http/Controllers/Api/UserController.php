<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Requests\UpdateUserRoleRequest;
use App\Http\Resources\MessageResource;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;

class UserController extends BaseApiController
{
    use AuthorizesRequests;

    /**
     * Update a user's role (Admin only).
     */
    public function updateRole(UpdateUserRoleRequest $request, User $user): MessageResource
    {
        $this->authorize('manageRole', $user);

        $oldRole = $user->role;
        $newRole = $request->validated('role');

        // Prevent role change if no change needed
        if ($oldRole->value === $newRole) {
            return new MessageResource('User role is already set to '.$newRole);
        }

        // Update the role explicitly (bypassing mass assignment protection)
        $user->role = $newRole;
        $user->save();

        return new MessageResource("User role updated from '$oldRole->value' to '$newRole' successfully");
    }

    /**
     * Get user's current role (for authorized users).
     */
    public function getRole(User $user): JsonResponse
    {
        $this->authorize('view', $user);

        return $this->dataResponse([
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
        ]);
    }
}
