<?php

namespace App\Services;

/**
 * Unified financial calculation context.
 *
 * Ensures deterministic, auditable calculations across:
 * - FeeEngine (formula evaluation)
 * - RuleEngineV2 (rule actions)
 * - ReceiptService (totals)
 *
 * All monetary calculations MUST pass through this context.
 */
class CalculationContext
{
    public const ROUNDING_HALF_UP = 'HALF_UP';
    public const ROUNDING_HALF_EVEN = 'HALF_EVEN';
    public const ROUNDING_TRUNCATE = 'TRUNCATE';

    public const DIVISION_BY_ZERO_ERROR = 'ERROR';
    public const DIVISION_BY_ZERO_ZERO = 'ZERO';

    private int $scale;
    private string $roundingMode;
    private bool $strictMode;
    private string $maxValue;
    private string $divisionByZeroPolicy;
    private array $feeSnapshots = [];

    public function __construct(
        int $scale = 3,
        string $roundingMode = self::ROUNDING_HALF_UP,
        bool $strictMode = true,
        string $maxValue = '999999999999.999',
        string $divisionByZeroPolicy = self::DIVISION_BY_ZERO_ERROR
    ) {
        $this->scale = $scale;
        $this->roundingMode = $roundingMode;
        $this->strictMode = $strictMode;
        $this->maxValue = $maxValue;
        $this->divisionByZeroPolicy = $divisionByZeroPolicy;
    }

    public function scale(): int
    {
        return $this->scale;
    }

    public function roundingMode(): string
    {
        return $this->roundingMode;
    }

    public function strictMode(): bool
    {
        return $this->strictMode;
    }

    public function maxValue(): string
    {
        return $this->maxValue;
    }

    public function divisionByZeroPolicy(): string
    {
        return $this->divisionByZeroPolicy;
    }

    /**
     * Record a fee version used during calculation (for audit trail).
     */
    public function recordFeeSnapshot(string $feeCode, array $snapshot): void
    {
        $this->feeSnapshots[$feeCode] = $snapshot;
    }

    /**
     * Get all recorded fee snapshots.
     */
    public function feeSnapshots(): array
    {
        return $this->feeSnapshots;
    }

    /**
     * Round a BC math result according to the configured rounding mode.
     */
    public function round(string $value): string
    {
        if ($this->roundingMode === self::ROUNDING_TRUNCATE) {
            return bcadd($value, '0', $this->scale);
        }

        // HALF_UP rounding: round half away from zero
        $multiplier = bcpow('10', (string) $this->scale);
        $shifted = bcmul($value, $multiplier, $this->scale + 4);
        $intPart = bcadd($shifted, '0', 0);

        // Compute fractional part with enough precision
        $fracPart = bcsub($shifted, $intPart, $this->scale + 4);

        // Determine the rounding threshold: 0.5 at the shifted scale
        $half = '0.' . str_repeat('0', $this->scale) . '5';

        // Use proper scale for comparison to avoid truncation issues
        $compareScale = $this->scale + 4;

        if (bccomp($value, '0', $compareScale) >= 0) {
            // Positive: round up if frac >= 0.5
            if (bccomp($fracPart, $half, $compareScale) >= 0) {
                $intPart = bcadd($intPart, '1', 0);
            }
        } else {
            // Negative: round down (away from zero) if frac <= -0.5
            if (bccomp($fracPart, '-' . $half, $compareScale) <= 0) {
                $intPart = bcsub($intPart, '1', 0);
            }
        }

        return bcdiv($intPart, $multiplier, $this->scale);
    }

    /**
     * Validate a value is within bounds.
     *
     * @throws \RuntimeException if value exceeds max in strict mode
     */
    public function validateBounds(string $value): string
    {
        if ($this->strictMode && bccomp($value, $this->maxValue) > 0) {
            throw new \RuntimeException(
                "Calculation result {$value} exceeds maximum allowed value {$this->maxValue}"
            );
        }
        return $value;
    }

    /**
     * Normalize a value to the configured scale.
     * Ensures consistent decimal formatting for comparisons.
     */
    public function normalize(string $value): string
    {
        return bcadd($value, '0', $this->scale);
    }

    /**
     * Handle division by zero according to policy.
     *
     * @throws \RuntimeException if policy is ERROR
     */
    public function handleDivisionByZero(): string
    {
        if ($this->divisionByZeroPolicy === self::DIVISION_BY_ZERO_ERROR) {
            throw new \RuntimeException('Division by zero in financial calculation');
        }
        return '0.' . str_repeat('0', $this->scale);
    }

    /**
     * Create the default context for IQD (3 decimal places, half-up rounding).
     */
    public static function default(): self
    {
        return new self(
            scale: 3,
            roundingMode: self::ROUNDING_HALF_UP,
            strictMode: true,
            maxValue: '999999999999.999',
            divisionByZeroPolicy: self::DIVISION_BY_ZERO_ERROR
        );
    }
}
