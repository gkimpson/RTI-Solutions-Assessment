<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidTaskAssignment implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $user = Auth::user();

        if (! $user) {
            $fail('You must be authenticated to assign tasks.');

            return;
        }

        // Check if the assigned user exists
        if (! User::where('id', $value)->exists()) {
            $fail('The selected assignee does not exist.');

            return;
        }

        // Non-admin users can only assign tasks to themselves
        if ($value !== $user->id && ! $user->isAdmin()) {
            $fail('You can only assign tasks to yourself.');
        }
    }
}
