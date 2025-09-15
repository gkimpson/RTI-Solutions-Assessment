<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\HigherOrderCollectionProxy;
use LaravelIdea\Helper\App\Models\_IH_Task_QB;

/**
 * @property HigherOrderCollectionProxy|int|mixed $version
 */
class Task extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'status',
        'priority',
        'due_date',
        'assigned_to',
        'user_id',
        'metadata',
        'version',
    ];

    protected $casts = [
        'due_date' => 'date',
        'metadata' => 'array',
        'version' => 'integer',
        'status' => TaskStatus::class,
        'priority' => TaskPriority::class,
    ];

    protected $attributes = [
        'version' => 1,
    ];

    public static function getStatuses(): array
    {
        return TaskStatus::values();
    }

    public static function getPriorities(): array
    {
        return TaskPriority::values();
    }

    // Relationships
    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'task_tag');
    }

    public function logs(): HasMany
    {
        return $this->hasMany(TaskLog::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    
}
