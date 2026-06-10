<?php

namespace App\Exceptions\Workflow;

use Exception;

class TemporalOverlapException extends Exception
{
    public function __construct(string $message = 'Temporal overlap detected', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
