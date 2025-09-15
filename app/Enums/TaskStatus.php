<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Task Status Enum
 *
 * Represents the possible states of a task throughout its lifecycle.
 * Each status has methods to provide human-readable labels and determine state relationships.
 */
enum TaskStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';

    /**
     * Get the human-readable label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::InProgress => 'In Progress',
            self::Completed => 'Completed',
        };
    }

    /**
     * Get the color representation for the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Pending => 'yellow',
            self::InProgress => 'blue',
            self::Completed => 'green',
        };
    }

    /**
     * Check if this status indicates work is in progress.
     */
    public function isActive(): bool
    {
        return $this === self::InProgress;
    }

    /**
     * Get all possible status values as strings.
     */
    public static function values(): array
    {
        return array_map(fn (self $status) => $status->value, self::cases());
    }

    /**
     * Get all status cases with their labels.
     */
    public static function options(): array
    {
        return array_map(
            fn (self $status) => [
                'value' => $status->value,
                'label' => $status->label(),
                'color' => $status->color(),
            ],
            self::cases()
        );
    }

    /**
     * Create status from string value with validation.
     */
    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
