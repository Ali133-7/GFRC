<?php

namespace App\Exceptions\Workflow;

use Exception;

class ExecutionPausedException extends Exception
{
    public function __construct(string $message = 'هذا التنفيذ موقوف مؤقتاً', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
