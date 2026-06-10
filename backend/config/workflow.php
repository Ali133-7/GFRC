<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Workflow Engine Configuration
    |--------------------------------------------------------------------------
    |
    | Enterprise Workflow Engine settings for GFRC.
    |
    */

    // Hours before an in-progress execution is marked as abandoned
    'abandoned_hours' => (int) env('WORKFLOW_ABANDONED_HOURS', 24),

    // Default decimal scale for financial calculations
    'financial_scale' => (int) env('WORKFLOW_FINANCIAL_SCALE', 3),

    // Enable strict formula validation (reject any non-whitelisted functions)
    'strict_formula_validation' => (bool) env('WORKFLOW_STRICT_FORMULA_VALIDATION', true),

    // Allowed functions in formula expressions
    'allowed_formula_functions' => ['min', 'max', 'round', 'abs'],

    // Enable debug panel endpoints
    'debug_panel_enabled' => (bool) env('WORKFLOW_DEBUG_PANEL_ENABLED', true),
];
