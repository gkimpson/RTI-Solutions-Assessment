<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Bulk Action Enum
 *
 * Represents the different types of bulk operations that can be performed
 * on multiple tasks simultaneously.
 */
enum BulkAction: string
{
    case Delete = 'delete';
    case Restore = 'restore';
    case UpdateStatus = 'update_status';

    /**
     * Get the human-readable label for the bulk action.
     */
    public function label(): string
    {
        return match ($this) {
            self::Delete => 'Delete Tasks',
            self::Restore => 'Restore Tasks',
            self::UpdateStatus => 'Update Status',
        };
    }

    /**
     * Get the color representation for the action.
     */
    public function color(): string
    {
        return match ($this) {
            self::Delete => 'red',
            self::Restore => 'green',
            self::UpdateStatus => 'blue',
        };
    }

    /**
     * Get the icon for this bulk action.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Delete => 'trash',
            self::Restore => 'refresh',
            self::UpdateStatus => 'edit',
        };
    }

    /**
     * Check if this action requires a status parameter.
     */
    public function requiresStatus(): bool
    {
        return $this === self::UpdateStatus;
    }

    /**
     * Check if this action is destructive (requires confirmation).
     */
    public function isDestructive(): bool
    {
        return match ($this) {
            self::Delete => true,
            self::Restore, self::UpdateStatus => false,
        };
    }

    /**
     * Check if this action is reversible.
     */
    public function isReversible(): bool
    {
        return match ($this) {
            self::Delete => true, // Can be restored
            self::Restore => true, // Can be deleted again
            self::UpdateStatus => false, // Cannot easily undo status changes
        };
    }

    /**
     * Check if this action requires version checking (optimistic locking).
     */
    public function requiresVersionCheck(): bool
    {
        return $this === self::UpdateStatus;
    }

    /**
     * Get all possible bulk action values as strings.
     */
    public static function values(): array
    {
        return array_map(fn (self $action) => $action->value, self::cases());
    }

    /**
     * Get all bulk action cases with their labels and metadata.
     */
    public static function options(): array
    {
        return array_map(
            fn (self $action) => [
                'value' => $action->value,
                'label' => $action->label(),
                'color' => $action->color(),
                'icon' => $action->icon(),
                'is_destructive' => $action->isDestructive(),
                'is_reversible' => $action->isReversible(),
                'requires_status' => $action->requiresStatus(),
                'requires_version_check' => $action->requiresVersionCheck(),
            ],
            self::cases()
        );
    }

    /**
     * Create action from string value with validation.
     */
    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
