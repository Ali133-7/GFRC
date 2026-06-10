<?php

namespace Tests\Unit;

use App\Exceptions\Workflow\RuleEvaluationException;
use App\Services\FormulaEvaluator;
use Tests\TestCase;

class FormulaEvaluatorTest extends TestCase
{
    private FormulaEvaluator $evaluator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->evaluator = new FormulaEvaluator();
    }

    public function test_basic_arithmetic(): void
    {
        $result = $this->evaluator->evaluate('10 + 5 * 2', [], 3);
        $this->assertEquals('20.000', $result);
    }

    public function test_allowed_functions(): void
    {
        $result = $this->evaluator->evaluate('max(10, 20) + min(5, 3)', [], 3);
        $this->assertEquals('23.000', $result);
    }

    public function test_rejects_eval_in_formula(): void
    {
        $this->expectException(RuleEvaluationException::class);
        $this->evaluator->evaluate("eval('phpinfo()')", []);
    }

    public function test_rejects_system_in_formula(): void
    {
        $this->expectException(RuleEvaluationException::class);
        $this->evaluator->evaluate("system('ls')", []);
    }

    public function test_rejects_shell_exec_in_formula(): void
    {
        $this->expectException(RuleEvaluationException::class);
        $this->evaluator->evaluate("shell_exec('whoami')", []);
    }

    public function test_rejects_unallowed_function(): void
    {
        $this->expectException(RuleEvaluationException::class);
        $this->evaluator->evaluate('array_merge([1], [2])', []);
    }

    public function test_rejects_php_code_injection(): void
    {
        $this->expectException(RuleEvaluationException::class);
        $this->evaluator->evaluate('1; system("rm -rf /"); 2', []);
    }

    public function test_context_variables(): void
    {
        $result = $this->evaluator->evaluate('amount * 0.15', ['amount' => 1000], 3);
        $this->assertEquals('150.000', $result);
    }

    public function test_precision_with_scale(): void
    {
        $result = $this->evaluator->evaluate('1 / 3', [], 3);
        $this->assertEquals('0.333', $result);
    }

    public function test_invalid_syntax_throws(): void
    {
        $this->expectException(RuleEvaluationException::class);
        $this->evaluator->evaluate('10 + * 5', []);
    }

    public function test_is_valid_returns_true_for_safe_formula(): void
    {
        $this->assertTrue($this->evaluator->isValid('value + 5'));
    }

    public function test_is_valid_returns_false_for_dangerous_formula(): void
    {
        $this->assertFalse($this->evaluator->isValid('system("ls")'));
    }
}
