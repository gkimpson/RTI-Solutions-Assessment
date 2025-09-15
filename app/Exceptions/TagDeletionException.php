<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class TagDeletionException extends Exception
{
    protected int $taskCount = 0;

    public function __construct(string $message = '', int $taskCount = 0, int $code = 0, ?Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
        $this->taskCount = $taskCount;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'tasks_count' => $this->taskCount,
        ];
    }
}
