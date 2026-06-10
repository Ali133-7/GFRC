<?php

namespace App\Exceptions\Workflow;

use Exception;

class RuleEvaluationException extends Exception
{
    public function __construct(string $message = 'Rule evaluation failed', int $code = 0, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
