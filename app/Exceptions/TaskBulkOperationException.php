<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class TaskBulkOperationException extends Exception
{
    protected array $errors = [];

    protected int $processed = 0;

    protected int $conflicts = 0;

    public function __construct(
        string $message = '',
        array $errors = [],
        int $processed = 0,
        int $conflicts = 0,
        int $code = 0,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
        $this->errors = $errors;
        $this->processed = $processed;
        $this->conflicts = $conflicts;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'errors' => $this->errors,
            'processed' => $this->processed,
            'conflicts' => $this->conflicts,
        ];
    }
}
