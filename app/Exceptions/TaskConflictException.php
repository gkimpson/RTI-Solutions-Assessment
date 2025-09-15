<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class TaskConflictException extends Exception
{
    public function __construct(
        string $message = 'Task has been modified by another user. Please refresh and try again.',
        int $code = 409,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
