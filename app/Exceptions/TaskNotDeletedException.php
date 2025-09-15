<?php

declare(strict_types=1);

namespace App\Exceptions;

use Exception;

class TaskNotDeletedException extends Exception
{
    public function __construct(
        string $message = 'Task is not deleted and cannot be restored.',
        int $code = 400,
        ?Exception $previous = null
    ) {
        parent::__construct($message, $code, $previous);
    }
}
