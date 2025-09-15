<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Exception;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Carbon;
use Illuminate\Translation\PotentiallyTranslatedString;

class ValidDueDate implements ValidationRule
{
    private bool $allowPastDates;

    /**
     * Create a new ValidDueDate instance.
     *
     * @param  bool  $allowPastDates  Whether to allow past dates (useful for updates)
     */
    public function __construct(bool $allowPastDates = false)
    {
        $this->allowPastDates = $allowPastDates;
    }

    /**
     * Run the validation rule.
     *
     * @param  Closure(string): PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        try {
            $date = Carbon::parse($value);
        } catch (Exception) {
            $fail('The :attribute must be a valid date.');

            return;
        }

        // Check if the date is in the past (unless explicitly allowed)
        if (! $this->allowPastDates && $date->isPast() && ! $date->isToday()) {
            $fail('The :attribute cannot be in the past.');
        }

        // Check if the date is too far in the future (more than 10 years)
        if ($date->isAfter(now()->addYears(10))) {
            $fail('The :attribute cannot be more than 10 years in the future.');
        }
    }
}
