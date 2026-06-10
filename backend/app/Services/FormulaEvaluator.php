<?php

namespace App\Services;

use App\Exceptions\Workflow\RuleEvaluationException;
use Symfony\Component\ExpressionLanguage\ExpressionLanguage;
use Symfony\Component\ExpressionLanguage\SyntaxError;

/**
 * Safe formula evaluator using Symfony ExpressionLanguage.
 *
 * Whitelist: min, max, round, abs only.
 * No eval(). No system calls. No PHP functions outside whitelist.
 */
class FormulaEvaluator
{
    private ExpressionLanguage $language;

    private const ALLOWED_FUNCTIONS = ['min', 'max', 'round', 'abs'];

    public function __construct()
    {
        $this->language = new ExpressionLanguage();
    }

    /**
     * Evaluate a formula against a safe context.
     *
     * @param string $formula The formula expression
     * @param array $context Variable names => values
     * @param int $scale Decimal scale for financial precision
     * @return string Evaluated result as string (for bc-math compatibility)
     * @throws RuleEvaluationException
     */
    public function evaluate(string $formula, array $context, int $scale = 3): string
    {
        $this->validateFormula($formula);

        $safeContext = [];
        foreach ($context as $key => $value) {
            // Only allow scalar values
            if (is_array($value) || is_object($value)) {
                throw new RuleEvaluationException("Invalid context value for variable: {$key}");
            }
            // Preserve numeric values as strings for BC Math compatibility
            $safeContext[$key] = is_numeric($value) ? $value : $value;
        }

        try {
            $result = $this->language->evaluate($formula, $safeContext);
        } catch (SyntaxError $e) {
            throw new RuleEvaluationException("Formula syntax error: {$e->getMessage()}");
        } catch (\Exception $e) {
            throw new RuleEvaluationException("Formula evaluation error: {$e->getMessage()}");
        }

        // Convert to string with proper decimal representation for BC Math
        if (is_numeric($result)) {
            return number_format((float) $result, $scale, '.', '');
        }
        return (string) $result;
    }

    /**
     * Validate that a formula contains no forbidden constructs.
     *
     * @throws RuleEvaluationException
     */
    public function validateFormula(string $formula): void
    {
        $forbidden = [
            'eval',
            'exec',
            'system',
            'passthru',
            'shell_exec',
            'popen',
            'proc_open',
            '`',
            '$_',
            '$GLOBALS',
            'include',
            'require',
            'file_get_contents',
            'file_put_contents',
            'fopen',
            'fwrite',
            'class_exists',
            'new',
            '::',
        ];

        foreach ($forbidden as $token) {
            if (stripos($formula, $token) !== false) {
                throw new RuleEvaluationException("Forbidden token '{$token}' in formula");
            }
        }

        // Validate function calls against whitelist
        if (preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(/', $formula, $matches)) {
            foreach ($matches[1] as $func) {
                if (!in_array(strtolower($func), self::ALLOWED_FUNCTIONS, true)) {
                    throw new RuleEvaluationException("Function '{$func}' is not in the allowed whitelist");
                }
            }
        }
    }

    /**
     * Check if a formula is syntactically valid without evaluating.
     */
    public function isValid(string $formula): bool
    {
        try {
            $this->validateFormula($formula);
            $this->language->parse($formula, ['value']);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
