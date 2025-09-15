<?php

declare(strict_types=1);

namespace App\DTO;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use DateTime;
use InvalidArgumentException;

class TaskData
{
    /**
     * Valid status values (deprecated - use TaskStatus enum)
     */
    private const VALID_STATUSES = ['pending', 'in_progress', 'completed'];

    /**
     * Valid priority values (deprecated - use TaskPriority enum)
     */
    private const VALID_PRIORITIES = ['low', 'medium', 'high'];

    private array $providedFields = [];

    public function __construct(
        public readonly string $title,
        public readonly ?string $description = null,
        public readonly string $status = 'pending',
        public readonly string $priority = 'medium',
        public readonly ?string $dueDate = null,
        public readonly ?int $assignedTo = null,
        public readonly ?array $metadata = null,
        public readonly ?array $tagIds = null,
    ) {
        $this->validate();
    }

    public static function fromArray(array $data): self
    {
        // Convert tag_ids to integers if they exist
        $tagIds = null;
        if (isset($data['tag_ids']) && is_array($data['tag_ids'])) {
            $tagIds = array_map('intval', $data['tag_ids']);
        }

        // Convert assigned_to to integer if it exists and is not null
        $assignedTo = null;
        if (array_key_exists('assigned_to', $data) && $data['assigned_to'] !== null) {
            $assignedTo = (int) $data['assigned_to'];
        }

        $instance = new self(
            title: $data['title'],
            description: $data['description'] ?? null,
            status: $data['status'] ?? 'pending',
            priority: $data['priority'] ?? 'medium',
            dueDate: $data['due_date'] ?? null,
            assignedTo: $assignedTo,
            metadata: $data['metadata'] ?? null,
            tagIds: $tagIds,
        );

        $instance->providedFields = array_keys($data);

        return $instance;
    }

    public function toArray(): array
    {
        $result = [];

        // Only include fields that were actually provided in the request
        if (in_array('title', $this->providedFields, true)) {
            $result['title'] = $this->title;
        }
        if (in_array('description', $this->providedFields, true)) {
            $result['description'] = $this->description;
        }
        if (in_array('status', $this->providedFields, true)) {
            $result['status'] = $this->status;
        }
        if (in_array('priority', $this->providedFields, true)) {
            $result['priority'] = $this->priority;
        }
        if (in_array('due_date', $this->providedFields, true)) {
            $result['due_date'] = $this->dueDate;
        }
        if (in_array('assigned_to', $this->providedFields, true)) {
            $result['assigned_to'] = $this->assignedTo;
        }
        if (in_array('metadata', $this->providedFields, true)) {
            $result['metadata'] = $this->metadata;
        }
        if (in_array('tag_ids', $this->providedFields, true)) {
            $result['tag_ids'] = $this->tagIds;
        }

        return $result;
    }

    /**
     * Check if the task has metadata.
     */
    public function hasMetadata(): bool
    {
        return ! empty($this->metadata);
    }

    /**
     * Validate the DTO data.
     *
     * @throws InvalidArgumentException When validation fails
     */
    private function validate(): void
    {
        // Validate title
        if (empty(trim($this->title))) {
            throw new InvalidArgumentException('Title cannot be empty');
        }

        if (strlen($this->title) > 255) {
            throw new InvalidArgumentException('Title cannot be longer than 255 characters');
        }

        // Validate status
        if (! in_array($this->status, TaskStatus::values())) {
            throw new InvalidArgumentException(
                sprintf('Status must be one of: %s', implode(', ', TaskStatus::values()))
            );
        }

        // Validate priority
        if (! in_array($this->priority, TaskPriority::values())) {
            throw new InvalidArgumentException(
                sprintf('Priority must be one of: %s', implode(', ', TaskPriority::values()))
            );
        }

        // Validate description length
        if ($this->description !== null && strlen($this->description) > 65535) {
            throw new InvalidArgumentException('Description cannot be longer than 65535 characters');
        }

        // Validate due date format
        if ($this->dueDate !== null && ! $this->isValidDate($this->dueDate)) {
            throw new InvalidArgumentException('Due date must be a valid date in YYYY-MM-DD format');
        }

        // Validate assigned_to is positive integer
        if ($this->assignedTo !== null && $this->assignedTo <= 0) {
            throw new InvalidArgumentException('Assigned user ID must be a positive integer');
        }

        // Validate tag IDs are positive integers
        if ($this->tagIds !== null) {
            foreach ($this->tagIds as $tagId) {
                if (! is_int($tagId) || $tagId <= 0) {
                    throw new InvalidArgumentException('All tag IDs must be positive integers');
                }
            }
        }

        // Metadata validation is handled by type system since it's typed as ?array
    }

    /**
     * Check if a date string is valid.
     */
    private function isValidDate(string $date): bool
    {
        $format = 'Y-m-d';
        $datetime = DateTime::createFromFormat($format, $date);

        return $datetime && $datetime->format($format) === $date;
    }
}
