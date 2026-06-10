<?php

namespace App\Exceptions\Workflow;

use Exception;

/**
 * Thrown when a financial action (set_fee / calculate) carrying a positive amount
 * targets a field that is absent from the current step.
 *
 * Failing closed here is deliberate: silently dropping the amount would produce a
 * zero / understated total on a government receipt (لا state corruption صامت).
 */
class FinancialIntegrityException extends Exception
{
    public function __construct(string $message = 'Financial integrity violation', int $code = 422, ?\Throwable $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}
