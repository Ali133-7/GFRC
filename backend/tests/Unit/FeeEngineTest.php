<?php

namespace Tests\Unit;

use App\Services\CalculationContext;
use App\Services\FeeEngine;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeeEngineTest extends TestCase
{
    protected FeeEngine $engine;

    protected function setUp(): void
    {
        parent::setUp();
        $this->engine = new FeeEngine();
        $this->engine->setContext(CalculationContext::default());
    }

    #[Test]
    public function calculate_simple_addition(): void
    {
        $result = $this->engine->calculate('10 + 20', []);
        $this->assertEquals('30.000', $result);
    }

    #[Test]
    public function calculate_simple_subtraction(): void
    {
        $result = $this->engine->calculate('100 - 30', []);
        $this->assertEquals('70.000', $result);
    }

    #[Test]
    public function calculate_simple_multiplication(): void
    {
        $result = $this->engine->calculate('10 * 3', []);
        $this->assertEquals('30.000', $result);
    }

    #[Test]
    public function calculate_simple_division(): void
    {
        $result = $this->engine->calculate('100 / 4', []);
        $this->assertEquals('25.000', $result);
    }

    #[Test]
    public function calculate_operator_precedence(): void
    {
        $result = $this->engine->calculate('10 + 20 * 3', []);
        $this->assertEquals('70.000', $result);
    }

    #[Test]
    public function calculate_parentheses(): void
    {
        $result = $this->engine->calculate('(10 + 20) * 3', []);
        $this->assertEquals('90.000', $result);
    }

    #[Test]
    public function calculate_nested_parentheses(): void
    {
        $result = $this->engine->calculate('((2 + 3) * (4 + 1))', []);
        $this->assertEquals('25.000', $result);
    }

    #[Test]
    public function calculate_with_placeholders(): void
    {
        $result = $this->engine->calculate('{{amount}} * {{rate}}', [
            'amount' => '100',
            'rate' => '0.15',
        ]);
        $this->assertEquals('15.000', $result);
    }

    #[Test]
    public function calculate_decimal_precision(): void
    {
        $result = $this->engine->calculate('10.500 + 20.750', []);
        $this->assertEquals('31.250', $result);
    }

    #[Test]
    public function calculate_large_numbers(): void
    {
        $result = $this->engine->calculate('999999999 * 1000', []);
        $this->assertEquals('999999999000.000', $result);
    }

    #[Test]
    public function calculate_unary_minus(): void
    {
        $result = $this->engine->calculate('100 + -50', []);
        $this->assertEquals('50.000', $result);
    }

    #[Test]
    public function calculate_division_by_zero_throws(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->engine->calculate('100 / 0', []);
    }

    #[Test]
    public function calculate_complex_formula(): void
    {
        $result = $this->engine->calculate('{{base}} * {{rate}} + {{fixed}}', [
            'base' => '1000',
            'rate' => '0.05',
            'fixed' => '25',
        ]);
        $this->assertEquals('75.000', $result);
    }

    #[Test]
    public function calculate_rounding_half_up(): void
    {
        $result = $this->engine->calculate('10 / 3', []);
        $this->assertEquals('3.333', $result);
    }

    #[Test]
    public function calculate_returns_string_not_float(): void
    {
        $result = $this->engine->calculate('10 + 20', []);
        $this->assertIsString($result);
        $this->assertStringContainsString('.', $result);
    }

    #[Test]
    public function calculate_very_small_decimals(): void
    {
        $result = $this->engine->calculate('0.001 + 0.002', []);
        $this->assertEquals('0.003', $result);
    }

    #[Test]
    public function calculate_mixed_operations(): void
    {
        $result = $this->engine->calculate('100 + 50 * 2 - 25 / 5', []);
        $this->assertEquals('195.000', $result);
    }

    #[Test]
    public function calculate_fee_amounts(): void
    {
        $result = $this->engine->calculate('fee_{{gov}} + fee_{{service}}', [], [
            'gov' => '15.500',
            'service' => '25.000',
        ]);
        $this->assertEquals('40.500', $result);
    }
}
