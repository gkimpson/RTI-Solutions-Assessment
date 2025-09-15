<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * User Role Enum
 *
 * Represents the different roles a user can have in the system.
 * Each role defines permissions and access levels throughout the application.
 */
enum UserRole: string
{
    case Admin = 'admin';
    case User = 'user';

    /**
     * Get the human-readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::User => 'User',
        };
    }

    /**
     * Get the color representation for the role.
     */
    public function color(): string
    {
        return match ($this) {
            self::Admin => 'purple',
            self::User => 'blue',
        };
    }

    /**
     * Get the hierarchical level of this role (higher number = more permissions).
     */
    public function level(): int
    {
        return match ($this) {
            self::Admin => 100,
            self::User => 10,
        };
    }

    /**
     * Get the permissions array for this role.
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => [
                'tasks' => ['view_all', 'create', 'update', 'delete', 'restore'],
                'tags' => ['create', 'update', 'delete'],
                'users' => ['view_all', 'create', 'update', 'delete'],
                'roles' => ['modify'],
                'system' => ['admin'],
            ],
            self::User => [
                'tasks' => ['view_own', 'create', 'update_own', 'delete_own'],
                'tags' => ['view'],
                'users' => ['view_profile'],
                'system' => ['user'],
            ],
        };
    }

    /**
     * Get all possible role values as strings.
     */
    public static function values(): array
    {
        return array_map(fn (self $role) => $role->value, self::cases());
    }

    /**
     * Get all role cases with their labels and metadata.
     */
    public static function options(): array
    {
        return array_map(
            fn (self $role) => [
                'value' => $role->value,
                'label' => $role->label(),
                'color' => $role->color(),
                'level' => $role->level(),
            ],
            self::cases()
        );
    }

    /**
     * Create role from string value with validation.
     */
    public static function fromString(string $value): ?self
    {
        return self::tryFrom($value);
    }

    /**
     * Get the default role for new users.
     */
    public static function default(): self
    {
        return self::User;
    }

    /**
     * Compare this role with another role by level.
     * Returns: -1 if this is lower, 0 if equal, 1 if this is higher
     */
    public function compareTo(self $other): int
    {
        return $this->level() <=> $other->level();
    }
}
