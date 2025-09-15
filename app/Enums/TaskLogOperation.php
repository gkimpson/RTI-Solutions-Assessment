<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Task Log Operation Enum
 *
 * Represents the different types of operations that can be performed on tasks
 * and logged in the audit trail system.
 */
enum TaskLogOperation: string
{
    case Create = 'create';
    case Update = 'update';
    case Delete = 'delete';
    case Restore = 'restore';
    case ToggleStatus = 'toggle_status';

    /**
     * Get the human-readable label for the operation.
     */
    public function label(): string
    {
        return match ($this) {
            self::Create => 'Created',
            self::Update => 'Updated',
            self::Delete => 'Deleted',
            self::Restore => 'Restored',
            self::ToggleStatus => 'Status Changed',
        };
    }

    /**
     * Get the past tense action verb for the operation.
     */
    public function pastTense(): string
    {
        return match ($this) {
            self::Create => 'created',
            self::Update => 'updated',
            self::Delete => 'deleted',
            self::Restore => 'restored',
            self::ToggleStatus => 'changed status',
        };
    }

    /**
     * Get the color representation for the operation.
     */
    public function color(): string
    {
        return match ($this) {
            self::Create => 'green',
            self::Update => 'blue',
            self::Delete => 'red',
            self::Restore => 'orange',
            self::ToggleStatus => 'purple',
        };
    }

    /**
     * Get the icon for this operation.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Create => 'plus',
            self::Update => 'pencil',
            self::Delete => 'trash',
            self::Restore => 'refresh',
            self::ToggleStatus => 'arrow-right',
        };
    }

    /**
     * Check if this operation is destructive (can cause data loss).
     */
    public function isDestructive(): bool
    {
        return match ($this) {
            self::Delete => true,
            self::Create, self::Update, self::Restore, self::ToggleStatus => false,
        };
    }

    /**
     * Check if this operation is reversible.
     */
    public function isReversible(): bool
    {
        return match ($this) {
            self::Delete => true, // Can be restored
            self::Create => false, // Cannot undo creation
            self::Update => false, // Cannot undo updates (use old values)
            self::Restore => true, // Can be deleted again
            self::ToggleStatus => true, // Can toggle back
        };
    }

    /**
     * Get the severity level of this operation for logging purposes.
     */
    public function severity(): string
    {
        return match ($this) {
            self::Create => 'info',
            self::Update => 'info',
            self::Delete => 'warning',
            self::Restore => 'notice',
            self::ToggleStatus => 'info',
        };
    }

    /**
     * Get all possible operation values as strings.
     */
    public static function values(): array
    {
        return array_map(fn (self $operation) => $operation->value, self::cases());
    }

    /**
     * Get all operation cases with their labels and metadata.
     */
    public static function options(): array
    {
        return array_map(
            fn (self $operation) => [
                'value' => $operation->value,
                'label' => $operation->label(),
                'past_tense' => $operation->pastTense(),
                'color' => $operation->color(),
                'icon' => $operation->icon(),
                'is_destructive' => $operation->isDestructive(),
                'is_reversible' => $operation->isReversible(),
                'severity' => $operation->severity(),
            ],
            self::cases()
        );
    }

    /**
     * Create operation from string value with validation.
     */
    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }
}
