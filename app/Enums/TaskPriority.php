<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Task Priority Enum
 *
 * Represents the urgency/importance level of a task.
 * Each priority has methods for display, sorting, and categorization.
 */
enum TaskPriority: string
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';

    /**
     * Get the human-readable label for the priority.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Low',
            self::Medium => 'Medium',
            self::High => 'High',
        };
    }

    /**
     * Get the color representation for the priority.
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'orange',
            self::High => 'red',
        };
    }

    /**
     * Get the sort order for this priority (higher number = higher priority).
     */
    public function sortOrder(): int
    {
        return match ($this) {
            self::Low => 1,
            self::Medium => 2,
            self::High => 3,
        };
    }

    /**
     * Get the badge icon for this priority.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Low => 'arrow-down',
            self::Medium => 'minus',
            self::High => 'arrow-up',
        };
    }

    /**
     * Get all possible priority values as strings.
     */
    public static function values(): array
    {
        return array_map(fn (self $priority) => $priority->value, self::cases());
    }

    /**
     * Get all priority cases with their labels and metadata.
     */
    public static function options(): array
    {
        return array_map(
            fn (self $priority) => [
                'value' => $priority->value,
                'label' => $priority->label(),
                'color' => $priority->color(),
                'sort_order' => $priority->sortOrder(),
                'icon' => $priority->icon(),
            ],
            self::cases()
        );
    }

    /**
     * Create priority from string value with validation.
     */
    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Get the default priority for new tasks.
     */
    public static function default(): self
    {
        return self::Medium;
    }

    /**
     * Compare this priority with another priority.
     * Returns: -1 if this is lower, 0 if equal, 1 if this is higher
     */
    public function compareTo(self $other): int
    {
        return $this->sortOrder() <=> $other->sortOrder();
    }
}
