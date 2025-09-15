<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

class ValidMetadata implements ValidationRule
{
    /**
     * Run the validation rule.
     *
     * @throws \JsonException
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if ($value === null) {
            return;
        }

        if (! is_array($value)) {
            $fail('The :attribute must be a valid JSON object.');

            return;
        }

        // Check for maximum depth (prevent deeply nested structures)
        if ($this->getArrayDepth($value) > 3) {
            $fail('The :attribute cannot have more than 3 levels of nesting.');

            return;
        }

        // Check for maximum size (prevent large payloads)
        $serialized = json_encode($value, JSON_THROW_ON_ERROR);
        if (strlen($serialized) > 10240) { // 10KB limit
            $fail('The :attribute cannot exceed 10KB in size.');

            return;
        }

        // Validate structure: only allow specific keys and data types
        foreach ($value as $key => $val) {
            // Keys must be strings and not empty
            if (! is_string($key) || empty($key)) {
                $fail('The :attribute contains invalid key format.');

                return;
            }

            // Key length limit
            if (strlen($key) > 50) {
                $fail('The :attribute key "'.substr($key, 0, 20).'..." is too long (max 50 characters).');

                return;
            }

            // Value type validation
            if (! $this->isValidValueType($val)) {
                $fail('The :attribute contains unsupported data type for key "'.$key.'".');

                return;
            }
        }
    }

    /**
     * Check if a value type is allowed in metadata
     */
    private function isValidValueType(mixed $value): bool
    {
        // Allow null
        if ($value === null) {
            return true;
        }

        // Allow strings (with length limit)
        if (is_string($value)) {
            return strlen($value) <= 1000;
        }

        // Allow integers and floats
        if (is_int($value) || is_float($value)) {
            return true;
        }

        // Allow booleans
        if (is_bool($value)) {
            return true;
        }

        // Allow arrays (recursive check)
        if (is_array($value)) {
            foreach ($value as $item) {
                if (! $this->isValidValueType($item)) {
                    return false;
                }
            }

            return true;
        }

        // Disallow objects, resources, etc.
        return false;
    }

    /**
     * Calculate the maximum depth of a nested array
     */
    private function getArrayDepth(array $array): int
    {
        $maxDepth = 1;

        foreach ($array as $value) {
            if (is_array($value)) {
                $depth = $this->getArrayDepth($value) + 1;
                $maxDepth = max($maxDepth, $depth);
            }
        }

        return $maxDepth;
    }
}
