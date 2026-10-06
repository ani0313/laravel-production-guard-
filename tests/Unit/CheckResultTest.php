<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit;

use Anivesh\LaravelProductionGuard\DTO\CheckResult;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Exception;
use PHPUnit\Framework\TestCase;

class CheckResultTest extends TestCase
{
    public function test_to_array_matches_the_documented_json_shape()
    {
        $result = new CheckResult('Disk Space', 'storage', CheckStatus::WARNING, 7, 10, 'Disk usage is 86%', [], ['Free additional disk space.']);

        $this->assertSame([
            'category' => 'storage',
            'name' => 'Disk Space',
            'status' => 'warning',
            'score' => 7,
            'max_score' => 10,
            'message' => 'Disk usage is 86%',
            'details' => [],
            'recommendations' => ['Free additional disk space.'],
        ], $result->toArray());
    }

    public function test_is_scorable_is_true_only_for_pass_warning_critical()
    {
        $this->assertTrue((new CheckResult('n', 'c', CheckStatus::PASS, 1, 1, 'm'))->isScorable());
        $this->assertTrue((new CheckResult('n', 'c', CheckStatus::WARNING, 1, 1, 'm'))->isScorable());
        $this->assertTrue((new CheckResult('n', 'c', CheckStatus::CRITICAL, 1, 1, 'm'))->isScorable());
        $this->assertFalse((new CheckResult('n', 'c', CheckStatus::UNKNOWN, 0, 0, 'm'))->isScorable());
        $this->assertFalse((new CheckResult('n', 'c', CheckStatus::NOT_SUPPORTED, 0, 0, 'm'))->isScorable());
        $this->assertFalse((new CheckResult('n', 'c', CheckStatus::NOT_APPLICABLE, 0, 0, 'm'))->isScorable());
    }

    public function test_from_exception_builds_an_unknown_result_without_leaking_internals_beyond_the_message()
    {
        $result = CheckResult::fromException('My Check', 'database', new Exception('boom'));

        $this->assertSame(CheckStatus::UNKNOWN, $result->getStatus());
        $this->assertSame('My Check', $result->getName());
        $this->assertSame('database', $result->getCategory());
        $this->assertContains('boom', $result->getMessage());
    }
}
