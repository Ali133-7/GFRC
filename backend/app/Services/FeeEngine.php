<?php

namespace App\Services;

use App\Models\FeeVersion;
use App\Models\OfficialFee;
use DateTimeInterface;

/**
 * Fee Engine - resolves fee versions and evaluates calculation formulas.
 *
 * Expression evaluation uses Shunting-Yard algorithm with BC Math only.
 * No float arithmetic is used inside calculations.
 *
 * Public API (backward compatible):
 *   - resolve(string $feeCode, ?DateTimeInterface $asOf): ?FeeVersion
 *   - resolveMany(array $feeCodes, ?DateTimeInterface $asOf): array
 *   - calculate(string $formula, array $values, array $feeAmounts = []): string
 */
class FeeEngine
{
    // Token types
    private const T_NUMBER = 'NUMBER';
    private const T_OPERATOR = 'OPERATOR';
    private const T_PAREN_OPEN = 'PAREN_OPEN';
    private const T_PAREN_CLOSE = 'PAREN_CLOSE';

    // Operator precedence and associativity
    private const OPERATORS = [
        '+' => ['precedence' => 1, 'associativity' => 'left'],
        '-' => ['precedence' => 1, 'associativity' => 'left'],
        '*' => ['precedence' => 2, 'associativity' => 'left'],
        '/' => ['precedence' => 2, 'associativity' => 'left'],
    ];

    protected ?CalculationContext $context = null;

    public function setContext(CalculationContext $context): void
    {
        $this->context = $context;
    }

    protected function getContext(): CalculationContext
    {
        return $this->context ?? CalculationContext::default();
    }

    // -- Fee Resolution --

    /**
     * Resolve a fee code to its active version at a given point in time.
     */
    public function resolve(string $feeCode, ?DateTimeInterface $asOf = null): ?FeeVersion
    {
        $asOf ??= now();

        return FeeVersion::whereHas('fee', function ($q) use ($feeCode) {
            $q->where('fee_code', $feeCode);
        })
            ->where('effective_from', '<=', $asOf)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $asOf);
            })
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Resolve a fee code to its active version, ensuring the parent OfficialFee is also active.
     * This is the authoritative resolution method for financial execution.
     * 
     * Falls back to OfficialFee.amount if no FeeVersion exists (for backward compatibility
     * with fees created before the versioning system was introduced).
     */
    public function resolveActive(string $feeCode, ?DateTimeInterface $asOf = null): ?FeeVersion
    {
        $asOf ??= now();

        // First, try to find an active FeeVersion
        $feeVersion = FeeVersion::whereHas('fee', function ($q) use ($feeCode) {
            $q->where('fee_code', $feeCode)
              ->where('is_active', true);
        })
            ->where('effective_from', '<=', $asOf)
            ->where(function ($q) use ($asOf) {
                $q->whereNull('effective_to')
                    ->orWhere('effective_to', '>=', $asOf);
            })
            ->orderByDesc('version')
            ->first();

        if ($feeVersion) {
            return $feeVersion;
        }

        // Fallback: use OfficialFee.amount directly if no FeeVersion exists
        $officialFee = \App\Models\OfficialFee::where('fee_code', $feeCode)
            ->where('is_active', true)
            ->first();

        if (!$officialFee) {
            return null;
        }

        // Create a synthetic FeeVersion from OfficialFee data
        $feeVersion = new FeeVersion();
        $feeVersion->id = $officialFee->id;
        $feeVersion->fee_id = $officialFee->id;
        $feeVersion->version = $officialFee->version ?? 1;
        $feeVersion->amount = $officialFee->amount;
        $feeVersion->effective_from = $officialFee->effective_from ?? $asOf;
        $feeVersion->effective_to = $officialFee->effective_to;
        $feeVersion->setRelation('fee', $officialFee);

        return $feeVersion;
    }

    /**
     * Resolve multiple fee codes at once.
     *
     * @return array<string, FeeVersion|null>
     */
    public function resolveMany(array $feeCodes, ?DateTimeInterface $asOf = null): array
    {
        $results = [];
        foreach (array_unique($feeCodes) as $code) {
            $results[$code] = $this->resolve($code, $asOf);
        }
        return $results;
    }

    // -- Public Calculation API --

    /**
     * Calculate amount from a formula with field values.
     *
     * Returns BC Math decimal string (e.g. "123.456").
     * NEVER returns float. All consumers must handle string amounts.
     */
    public function calculate(string $formula, array $values, array $feeAmounts = []): string
    {
        $expression = $this->prepareExpression($formula, $values, $feeAmounts);
        return $this->evaluateExpression($expression);
    }

    /**
     * Calculate and return the raw BC string (alias of calculate).
     * Kept for explicit clarity in internal callers.
     */
    public function calculateRaw(string $formula, array $values, array $feeAmounts = []): string
    {
        return $this->calculate($formula, $values, $feeAmounts);
    }

    // -- Expression Preparation --

    /**
     * Replace placeholders with numeric values.
     *
     * Values are converted to BC-safe decimal strings (never float).
     */
    protected function prepareExpression(string $formula, array $values, array $feeAmounts = []): string
    {
        $expression = $formula;

        // Replace fee_{{code}} with fee amounts FIRST (before generic {{field_id}} replacement)
        $expression = preg_replace_callback('/fee_\{\{([\w-]+)\}\}/', function ($matches) use ($feeAmounts) {
            $code = $matches[1];
            $amount = $feeAmounts[$code] ?? '0';
            return $this->toDecimalString($amount);
        }, $expression);

        // Replace {{field_id}} with field values (support UUIDs with hyphens)
        $expression = preg_replace_callback('/\{\{([\w-]+)\}\}/', function ($matches) use ($values) {
            $key = $matches[1];
            $value = $values[$key] ?? '0';
            return $this->toDecimalString($value);
        }, $expression);

        return $expression;
    }

    /**
     * Convert any value to a BC-safe decimal string.
     *
     * Never uses float arithmetic. Handles strings, ints, floats, numerics.
     */
    protected function toDecimalString(mixed $value): string
    {
        if (is_string($value)) {
            $value = trim($value);
            if (is_numeric($value)) {
                if (str_contains($value, '.')) {
                    return $value;
                }
                return $value . '.0';
            }
            return '0.0';
        }
        if (is_int($value)) {
            return (string) $value . '.0';
        }
        if (is_float($value)) {
            $str = rtrim(rtrim(number_format($value, 10, '.', ''), '0'), '.');
            if (!str_contains($str, '.')) {
                $str .= '.0';
            }
            return $str;
        }
        return '0.0';
    }

    // -- Shunting-Yard Evaluation Pipeline --

    /**
     * Full pipeline: expression string -> tokens -> RPN -> BC evaluation.
     */
    protected function evaluateExpression(string $expression): string
    {
        $ctx = $this->getContext();
        $tokens = $this->tokenize($expression);
        $rpn = $this->toRPN($tokens);
        $result = $this->evaluateRPN($rpn, $ctx);
        $result = $ctx->round($result);
        return $ctx->validateBounds($result);
    }

    // -- Tokenizer --

    /**
     * Convert expression string to token stream.
     *
     * Handles: numbers (integers, decimals), operators, parentheses.
     * Handles unary minus (negation) by marking as special token.
     */
    protected function tokenize(string $expression): array
    {
        $expression = preg_replace('/\s+/', '', $expression);
        $tokens = [];
        $i = 0;
        $len = strlen($expression);

        while ($i < $len) {
            $ch = $expression[$i];

            // Number (integer or decimal)
            if (ctype_digit($ch) || $ch === '.') {
                $num = '';
                $hasDot = false;
                while ($i < $len) {
                    $c = $expression[$i];
                    if (ctype_digit($c)) {
                        $num .= $c;
                    } elseif ($c === '.' && !$hasDot) {
                        $hasDot = true;
                        $num .= $c;
                    } else {
                        break;
                    }
                    $i++;
                }
                if (!$hasDot) {
                    $num .= '.0';
                }
                $tokens[] = [self::T_NUMBER, $num];
                continue;
            }

            // Operators
            if (isset(self::OPERATORS[$ch])) {
                if ($ch === '-' && (
                    empty($tokens) ||
                    end($tokens)[0] === self::T_PAREN_OPEN ||
                    end($tokens)[0] === self::T_OPERATOR
                )) {
                    $tokens[] = ['UNARY_MINUS', '-'];
                } else {
                    $tokens[] = [self::T_OPERATOR, $ch];
                }
                $i++;
                continue;
            }

            // Parentheses
            if ($ch === '(') {
                $tokens[] = [self::T_PAREN_OPEN, '('];
                $i++;
                continue;
            }
            if ($ch === ')') {
                $tokens[] = [self::T_PAREN_CLOSE, ')'];
                $i++;
                continue;
            }

            // Skip unknown characters
            $i++;
        }

        return $tokens;
    }

    // -- Shunting-Yard: Infix to RPN --

    /**
     * Convert infix token stream to Reverse Polish Notation (postfix).
     *
     * Handles operator precedence and associativity correctly.
     */
    protected function toRPN(array $tokens): array
    {
        $output = [];
        $operators = [];

        foreach ($tokens as $token) {
            [$type, $value] = $token;

            if ($type === self::T_NUMBER) {
                $output[] = $token;
            } elseif ($type === 'UNARY_MINUS') {
                $operators[] = ['UNARY_MINUS', 'u-'];
            } elseif ($type === self::T_OPERATOR) {
                $o1 = $value;
                while (!empty($operators)) {
                    $top = end($operators);
                    $o2 = $top[1];

                    if ($o2 === 'u-') {
                        $output[] = array_pop($operators);
                        continue;
                    }

                    if (!isset(self::OPERATORS[$o2])) {
                        break;
                    }

                    $o1Prec = self::OPERATORS[$o1]['precedence'];
                    $o2Prec = self::OPERATORS[$o2]['precedence'];

                    if (
                        ($o1Prec < $o2Prec) ||
                        ($o1Prec === $o2Prec && self::OPERATORS[$o1]['associativity'] === 'left')
                    ) {
                        $output[] = array_pop($operators);
                    } else {
                        break;
                    }
                }
                $operators[] = $token;
            } elseif ($type === self::T_PAREN_OPEN) {
                $operators[] = $token;
            } elseif ($type === self::T_PAREN_CLOSE) {
                while (!empty($operators) && end($operators)[0] !== self::T_PAREN_OPEN) {
                    $output[] = array_pop($operators);
                }
                if (!empty($operators) && end($operators)[0] === self::T_PAREN_OPEN) {
                    array_pop($operators);
                }
            }
        }

        while (!empty($operators)) {
            $output[] = array_pop($operators);
        }

        return $output;
    }

    // -- RPN Evaluator (BC Math Only) --

    /**
     * Evaluate RPN token stream using BC Math exclusively.
     *
     * No float arithmetic. All operations use bcadd, bcsub, bcmul, bcdiv.
     */
    protected function evaluateRPN(array $rpn, CalculationContext $ctx): string
    {
        $stack = [];
        $scale = $ctx->scale();

        foreach ($rpn as $token) {
            [$type, $value] = $token;

            if ($type === self::T_NUMBER) {
                $stack[] = $value;
            } elseif ($type === 'UNARY_MINUS') {
                if (empty($stack)) {
                    throw new \RuntimeException('Invalid expression: unary minus with no operand');
                }
                $a = array_pop($stack);
                $stack[] = bcsub('0', $a, $scale);
            } elseif ($type === self::T_OPERATOR) {
                if (count($stack) < 2) {
                    throw new \RuntimeException("Invalid expression: operator {$value} with insufficient operands");
                }
                $b = array_pop($stack);
                $a = array_pop($stack);
                $result = $this->bcOperation($a, $b, $value, $ctx);
                $stack[] = $result;
            }
        }

        if (count($stack) !== 1) {
            throw new \RuntimeException('Invalid expression: stack has ' . count($stack) . ' items after evaluation');
        }

        return $stack[0];
    }

    /**
     * Perform a single BC math operation with proper error handling.
     */
    protected function bcOperation(string $a, string $b, string $op, CalculationContext $ctx): string
    {
        $scale = $ctx->scale();

        return match ($op) {
            '+' => bcadd($a, $b, $scale),
            '-' => bcsub($a, $b, $scale),
            '*' => bcmul($a, $b, $scale),
            '/' => bccomp($b, '0', $scale) === 0
                ? $ctx->handleDivisionByZero()
                : bcdiv($a, $b, $scale),
            default => throw new \RuntimeException("Unknown operator: {$op}"),
        };
    }
}
