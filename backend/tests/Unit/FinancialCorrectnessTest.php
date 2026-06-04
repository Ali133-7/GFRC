<?php

namespace Tests\Unit;

use App\Services\CalculationContext;
use App\Services\FeeEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FinancialCorrectnessTest extends TestCase
{
    protected FeeEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new FeeEngine();
        $this->engine->setContext(CalculationContext::default());
    }

    #[Test]
    public function large_numbers_preserve_precision(): void
    {
        $result = $this->engine->calculate('999999999.999 + 0.001', []);
        $this->assertEquals('1000000000.000', $result);
    }

    #[Test]
    public function very_large_multiplication(): void
    {
        $result = $this->engine->calculate('999999999.999 * 1000', []);
        $this->assertEquals('999999999999.000', $result);
    }

    #[Test]
    public function decimal_precision_not_lost(): void
    {
        $result = $this->engine->calculate('0.001 + 0.002 + 0.003', []);
        $this->assertEquals('0.006', $result);
    }

    #[Test]
    public function repeating_decimal_rounds_correctly(): void
    {
        $result = $this->engine->calculate('1 / 3', []);
        $this->assertEquals('0.333', $result);
    }

    #[Test]
    public function rounding_half_up(): void
    {
        $result = $this->engine->calculate('2.5 / 10', []);
        $this->assertEquals('0.250', $result);
    }

    #[Test]
    public function rounding_half_up_at_boundary(): void
    {
        $result = $this->engine->calculate('1.0005', []);
        $this->assertEquals('1.001', $result);
    }

    #[Test]
    public function negative_values_handled(): void
    {
        $result = $this->engine->calculate('100 + -50', []);
        $this->assertEquals('50.000', $result);
    }

    #[Test]
    public function negative_result(): void
    {
        $result = $this->engine->calculate('50 - 100', []);
        $this->assertEquals('-50.000', $result);
    }

    #[Test]
    public function division_by_zero_throws_exception(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Division by zero');
        $this->engine->calculate('100 / 0', []);
    }

    #[Test]
    public function zero_dividend_returns_zero(): void
    {
        $result = $this->engine->calculate('0 / 100', []);
        $this->assertEquals('0.000', $result);
    }

    #[Test]
    public function complex_financial_formula(): void
    {
        $result = $this->engine->calculate('{{base}} * {{rate}} + {{fixed}} - {{discount}}', [
            'base' => '10000',
            'rate' => '0.025',
            'fixed' => '50',
            'discount' => '25.500',
        ]);
        $this->assertEquals('274.500', $result);
    }

    #[Test]
    public function percentage_calculation(): void
    {
        $result = $this->engine->calculate('{{amount}} * {{percentage}} / 100', [
            'amount' => '5000',
            'percentage' => '15',
        ]);
        $this->assertEquals('750.000', $result);
    }

    #[Test]
    public function compound_calculation(): void
    {
        $result = $this->engine->calculate('({{a}} + {{b}}) * {{c}} / {{d}}', [
            'a' => '100',
            'b' => '200',
            'c' => '3',
            'd' => '2',
        ]);
        $this->assertEquals('450.000', $result);
    }

    #[Test]
    public function all_results_are_strings(): void
    {
        $formulas = [
            '10 + 20',
            '100 - 50',
            '10 * 3',
            '100 / 4',
            '(10 + 20) * 3',
            '100 / 3',
            '0.001 + 0.002',
            '999999999.999 * 1000',
        ];

        foreach ($formulas as $formula) {
            $result = $this->engine->calculate($formula, []);
            $this->assertIsString($result, "Formula '{$formula}' should return string");
        }
    }

    #[Test]
    public function all_results_have_decimal_places(): void
    {
        $formulas = [
            '10 + 20',
            '100 / 3',
            '0.001 + 0.002',
        ];

        foreach ($formulas as $formula) {
            $result = $this->engine->calculate($formula, []);
            $this->assertMatchesRegularExpression('/\.\d{3}$/', $result, "Formula '{$formula}' should have 3 decimal places");
        }
    }

    #[Test]
    public function bounds_checking_prevents_overflow(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('exceeds maximum');

        $ctx = new CalculationContext(
            scale: 3,
            strictMode: true,
            maxValue: '1000.000'
        );
        $engine = new FeeEngine();
        $engine->setContext($ctx);
        $engine->calculate('999999999999.999 * 1000', []);
    }
}
