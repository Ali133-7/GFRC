<?php

namespace App\Exceptions\Workflow;

use Exception;

class ValidationBlockedException extends Exception
{
    public array $blocks;

    public function __construct(array $blocks, string $message = 'فشل التحقق', int $code = 422, ?\Throwable $previous = null)
    {
        $this->blocks = $blocks;
        parent::__construct($message, $code, $previous);
    }
}
