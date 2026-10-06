<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit;

use Anivesh\LaravelProductionGuard\DTO\CheckResult;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Services\ScoreCalculator;
use PHPUnit\Framework\TestCase;

class ScoreCalculatorTest extends TestCase
{
    public function test_it_returns_zero_for_an_empty_result_set()
    {
        $calculator = new ScoreCalculator(['database' => 15]);

        $this->assertSame(0, $calculator->calculate([]));
        $this->assertSame(CheckStatus::UNKNOWN, $calculator->deriveStatus([]));
    }

    public function test_full_marks_across_two_equally_weighted_categories_scores_100()
    {
        $calculator = new ScoreCalculator(['database' => 10, 'cache' => 10]);

        $results = [
            new CheckResult('DB', 'database', CheckStatus::PASS, 10, 10, 'ok'),
            new CheckResult('Cache', 'cache', CheckStatus::PASS, 10, 10, 'ok'),
        ];

        $this->assertSame(100, $calculator->calculate($results));
        $this->assertSame(CheckStatus::PASS, $calculator->deriveStatus($results));
    }

    public function test_unknown_and_not_applicable_results_never_affect_the_score()
    {
        $calculator = new ScoreCalculator(['database' => 10, 'horizon' => 10]);

        $results = [
            new CheckResult('DB', 'database', CheckStatus::PASS, 10, 10, 'ok'),
            new CheckResult('Horizon', 'horizon', CheckStatus::NOT_APPLICABLE, 0, 0, 'not installed'),
        ];

        $this->assertSame(100, $calculator->calculate($results));
    }

    public function test_a_category_with_no_configured_weight_does_not_affect_the_score()
    {
        $calculator = new ScoreCalculator(['database' => 10]);

        $results = [
            new CheckResult('DB', 'database', CheckStatus::PASS, 10, 10, 'ok'),
            new CheckResult('Unweighted', 'unweighted_category', CheckStatus::CRITICAL, 0, 10, 'bad'),
        ];

        // The unweighted-but-critical category contributes 0 weight to the
        // average, so it cannot move the numeric score...
        $this->assertSame(100, $calculator->calculate($results));
        // ...but a CRITICAL result must still flip the overall status,
        // regardless of score.
        $this->assertSame(CheckStatus::CRITICAL, $calculator->deriveStatus($results));
    }

    public function test_status_reflects_the_worst_result_not_the_score()
    {
        $calculator = new ScoreCalculator(['database' => 10, 'security' => 10]);

        $results = [
            new CheckResult('DB', 'database', CheckStatus::PASS, 10, 10, 'ok'),
            new CheckResult('Security', 'security', CheckStatus::WARNING, 8, 10, 'warn'),
        ];

        $this->assertSame(90, $calculator->calculate($results));
        $this->assertSame(CheckStatus::WARNING, $calculator->deriveStatus($results));
    }

    public function test_an_unknown_result_prevents_a_false_pass()
    {
        $calculator = new ScoreCalculator(['database' => 10]);

        $results = [
            new CheckResult('DB', 'database', CheckStatus::PASS, 10, 10, 'ok'),
            new CheckResult('Scheduler', 'scheduler', CheckStatus::UNKNOWN, 0, 0, 'cannot verify'),
        ];

        $this->assertSame(CheckStatus::UNKNOWN, $calculator->deriveStatus($results));
    }

    public function test_not_supported_behaves_identically_to_unknown()
    {
        $calculator = new ScoreCalculator(['database' => 10]);

        $results = [
            new CheckResult('DB', 'database', CheckStatus::PASS, 10, 10, 'ok'),
            new CheckResult('Version', 'database', CheckStatus::NOT_SUPPORTED, 0, 0, 'driver not supported'),
        ];

        $this->assertSame(100, $calculator->calculate($results));
        $this->assertSame(CheckStatus::UNKNOWN, $calculator->deriveStatus($results));
    }

    public function test_multiple_results_within_one_category_are_averaged_before_weighting()
    {
        $calculator = new ScoreCalculator(['database' => 10]);

        $results = [
            new CheckResult('Connection', 'database', CheckStatus::PASS, 10, 10, 'ok'),
            new CheckResult('Latency', 'database', CheckStatus::PASS, 10, 10, 'ok'),
            new CheckResult('Version', 'database', CheckStatus::WARNING, 5, 10, 'slow'),
        ];

        // (10 + 10 + 5) / 30 = 83.33% -> rounds to 83.
        $this->assertSame(83, $calculator->calculate($results));
    }

    public function test_the_weighted_average_across_differently_weighted_categories_is_correct()
    {
        $calculator = new ScoreCalculator(['database' => 30, 'security' => 10]);

        $results = [
            // database: 100%, weight 30 -> contributes 3000
            new CheckResult('DB', 'database', CheckStatus::PASS, 10, 10, 'ok'),
            // security: 0%, weight 10 -> contributes 0
            new CheckResult('Security', 'security', CheckStatus::CRITICAL, 0, 10, 'bad'),
        ];

        // (3000 + 0) / (30 + 10) = 75.
        $this->assertSame(75, $calculator->calculate($results));
    }
}
