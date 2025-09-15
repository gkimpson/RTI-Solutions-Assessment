<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskLogOperation;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskLog extends Model
{
    use HasFactory;

    // Operation type constants (deprecated - use TaskLogOperation enum)
    public const OPERATION_CREATE = 'create';

    public const OPERATION_UPDATE = 'update';

    public const OPERATION_DELETE = 'delete';

    public const OPERATION_RESTORE = 'restore';

    public const OPERATION_TOGGLE_STATUS = 'toggle_status';

    protected $fillable = [
        'task_id',
        'operation_type',
        'changes',
        'old_values',
        'new_values',
        'performed_at',
    ];

    /**
     * Fields protected from mass assignment for security.
     */
    protected $guarded = [
        'id',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'changes' => 'array',
        'old_values' => 'array',
        'new_values' => 'array',
        'performed_at' => 'datetime',
        'operation_type' => TaskLogOperation::class,
    ];

    // Relationships
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Static helper methods

    public static function logOperation(Task $task, TaskLogOperation|string $operationType, ?User $user = null, array $changes = [], array $oldValues = [], array $newValues = []): self
    {
        $operationValue = $operationType instanceof TaskLogOperation ? $operationType->value : $operationType;

        $taskLog = new self([
            'task_id' => $task->id,
            'operation_type' => $operationValue,
            'changes' => $changes,
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'performed_at' => now(),
        ]);

        // Set user_id explicitly to prevent mass assignment vulnerability
        $taskLog->user_id = $user?->id;
        $taskLog->save();

        return $taskLog;
    }

    public static function logCreate(Task $task, ?User $user = null): self
    {
        return self::logOperation($task, TaskLogOperation::Create, $user, [], [], $task->toArray());
    }

    public static function logUpdate(Task $task, array $changes, array $oldValues, ?User $user = null): self
    {
        return self::logOperation($task, TaskLogOperation::Update, $user, $changes, $oldValues, $task->toArray());
    }

    public static function logDelete(Task $task, ?User $user = null): self
    {
        return self::logOperation($task, TaskLogOperation::Delete, $user, ['deleted' => true], $task->toArray());
    }

    public static function logRestore(Task $task, ?User $user = null): self
    {
        return self::logOperation($task, TaskLogOperation::Restore, $user, ['restored' => true], [], $task->toArray());
    }

    public static function logStatusToggle(Task $task, TaskStatus|string $fromStatus, TaskStatus|string $toStatus, ?User $user = null): self
    {
        $fromValue = $fromStatus instanceof TaskStatus ? $fromStatus->value : $fromStatus;
        $toValue = $toStatus instanceof TaskStatus ? $toStatus->value : $toStatus;

        return self::logOperation(
            $task,
            TaskLogOperation::ToggleStatus,
            $user,
            ['status' => ['from' => $fromValue, 'to' => $toValue]]
        );
    }

    // Helper methods

    private function getFieldLabel(string $field): string
    {
        return match ($field) {
            'title' => 'Title',
            'description' => 'Description',
            'status' => 'Status',
            'priority' => 'Priority',
            'due_date' => 'Due Date',
            'assigned_to' => 'Assigned To',
            'metadata' => 'Metadata',
            'version' => 'Version',
            'deleted' => 'Deleted',
            'restored' => 'Restored',
            'bulk_operation' => 'Bulk Operation',
            default => ucfirst(str_replace('_', ' ', $field)),
        };
    }

    private function formatFieldValue(string $field, mixed $value): string
    {
        if ($value === null) {
            return 'None';
        }

        return match ($field) {
            'status' => TaskStatus::fromString($value)?->label() ?? $value,
            'priority' => TaskPriority::fromString($value)?->label() ?? $value,
            'assigned_to' => $value ? "User ID: {$value}" : 'Unassigned',
            'due_date' => $value ? $value : 'No due date',
            'deleted', 'restored', 'bulk_operation' => $value ? 'Yes' : 'No',
            'metadata' => is_array($value) ? json_encode($value) : $value,
            default => (string) $value,
        };
    }
}
