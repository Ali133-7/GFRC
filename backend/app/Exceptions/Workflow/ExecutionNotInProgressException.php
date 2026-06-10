<?php

namespace App\Exceptions\Workflow;

use Exception;

class ExecutionNotInProgressException extends Exception
{
    public function __construct(string $message = 'Execution is not in progress', int $code = 409, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
