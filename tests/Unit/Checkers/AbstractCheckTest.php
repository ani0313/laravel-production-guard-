<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Tests\Fixtures\AggregatingCheck;
use PHPUnit\Framework\TestCase;

class AbstractCheckTest extends TestCase
{
    public function test_all_pass_facts_score_full_marks_and_report_a_summary_message()
    {
        $result = (new AggregatingCheck([
            ['status' => CheckStatus::PASS, 'message' => 'a'],
            ['status' => CheckStatus::PASS, 'message' => 'b'],
        ]))->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
        $this->assertSame(20.0, $result->getScore());
        $this->assertSame(20.0, $result->getMaxScore());
        $this->assertSame('All 2 check(s) passed.', $result->getMessage());
    }

    public function test_a_warning_fact_earns_half_points_and_sets_overall_status()
    {
        $result = (new AggregatingCheck([
            ['status' => CheckStatus::PASS, 'message' => 'a'],
            ['status' => CheckStatus::WARNING, 'message' => 'b is risky', 'recommendation' => 'fix b'],
        ]))->check();

        $this->assertSame(CheckStatus::WARNING, $result->getStatus());
        $this->assertSame(15.0, $result->getScore());
        $this->assertSame(20.0, $result->getMaxScore());
        $this->assertSame('b is risky', $result->getMessage());
        $this->assertSame(['fix b'], $result->getRecommendations());
    }

    public function test_a_critical_fact_wins_over_a_warning_fact()
    {
        $result = (new AggregatingCheck([
            ['status' => CheckStatus::WARNING, 'message' => 'warn'],
            ['status' => CheckStatus::CRITICAL, 'message' => 'bad'],
        ]))->check();

        $this->assertSame(CheckStatus::CRITICAL, $result->getStatus());
    }

    public function test_not_applicable_facts_are_excluded_from_scoring_and_from_the_message()
    {
        $result = (new AggregatingCheck([
            ['status' => CheckStatus::PASS, 'message' => 'a'],
            ['status' => CheckStatus::NOT_APPLICABLE, 'message' => 'optional feature not installed'],
        ]))->check();

        $this->assertSame(10.0, $result->getScore());
        $this->assertSame(10.0, $result->getMaxScore());
        $this->assertSame('All 2 check(s) passed.', $result->getMessage());
    }

    public function test_an_unknown_fact_never_moves_the_score_but_still_flags_the_status()
    {
        $result = (new AggregatingCheck([
            ['status' => CheckStatus::PASS, 'message' => 'a'],
            ['status' => CheckStatus::UNKNOWN, 'message' => 'could not verify c'],
        ]))->check();

        $this->assertSame(10.0, $result->getScore());
        $this->assertSame(10.0, $result->getMaxScore());
        $this->assertSame(CheckStatus::UNKNOWN, $result->getStatus());
        $this->assertSame('could not verify c', $result->getMessage());
    }

    public function test_details_carry_every_fact_for_the_cli_reporter()
    {
        $facts = [
            ['status' => CheckStatus::PASS, 'message' => 'a'],
            ['status' => CheckStatus::WARNING, 'message' => 'b'],
        ];

        $result = (new AggregatingCheck($facts))->check();

        $this->assertSame($facts, $result->getDetails());
    }
}
