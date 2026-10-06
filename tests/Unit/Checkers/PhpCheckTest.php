<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\PhpCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use PHPUnit\Framework\TestCase;

class PhpCheckTest extends TestCase
{
    public function test_it_reports_the_running_php_version()
    {
        $result = (new PhpCheck())->check();

        $this->assertSame('PHP ' . PHP_VERSION, $result->getDetails()[0]['message']);
    }

    public function test_it_produces_one_fact_per_extension_and_ini_directive()
    {
        $result = (new PhpCheck())->check();

        // 1 PHP version + 8 required + 4 common extensions + 4 ini directives
        $this->assertCount(17, $result->getDetails());
    }

    public function test_a_loaded_required_extension_passes()
    {
        $result = (new PhpCheck())->check();

        $jsonFact = $this->factFor($result->getDetails(), 'ext-json');

        $this->assertSame(CheckStatus::PASS, $jsonFact['status']);
    }

    public function test_pdo_extension_is_never_marked_critical_when_loaded()
    {
        $this->assertTrue(extension_loaded('pdo'));

        $result = (new PhpCheck())->check();
        $pdoFact = $this->factFor($result->getDetails(), 'ext-pdo');

        $this->assertSame(CheckStatus::PASS, $pdoFact['status']);
    }

    private function factFor(array $facts, $needle)
    {
        foreach ($facts as $fact) {
            if (strpos($fact['message'], $needle) === 0) {
                return $fact;
            }
        }

        $this->fail("No fact found starting with '{$needle}'.");
    }
}
