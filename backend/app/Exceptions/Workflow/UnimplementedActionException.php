<?php

namespace App\Exceptions\Workflow;

use Exception;

class UnimplementedActionException extends Exception
{
    public function __construct(string $actionType, ?string $ruleId = null, int $code = 500, ?\Throwable $previous = null)
    {
        $message = "Action type [{$actionType}] is defined but not implemented.";
        if ($ruleId) {
            $message .= " Triggered by rule [{$ruleId}].";
        }
        parent::__construct($message, $code, $previous);
    }
}
