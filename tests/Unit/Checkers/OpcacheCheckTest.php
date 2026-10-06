<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\OpcacheCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use PHPUnit\Framework\TestCase;

class OpcacheCheckTest extends TestCase
{
    private function checkWith($iniEnabled, $liveStatus)
    {
        return new class($iniEnabled, $liveStatus) extends OpcacheCheck {
            private $fakeIniEnabled;
            private $fakeLiveStatus;

            public function __construct($iniEnabled, $liveStatus)
            {
                $this->fakeIniEnabled = $iniEnabled;
                $this->fakeLiveStatus = $liveStatus;
            }

            protected function iniEnabled()
            {
                return $this->fakeIniEnabled;
            }

            protected function liveStatus()
            {
                return $this->fakeLiveStatus;
            }
        };
    }

    public function test_disabled_by_ini_is_a_warning()
    {
        $result = $this->checkWith(false, null)->check();

        $this->assertSame(CheckStatus::WARNING, $result->getStatus());
        $this->assertSame('OPcache is disabled.', $result->getDetails()[0]['message']);
    }

    public function test_enabled_by_ini_but_unreadable_live_status_is_unknown_not_a_false_warning()
    {
        $result = $this->checkWith(true, null)->check();

        $this->assertSame(CheckStatus::UNKNOWN, $result->getStatus());
    }

    public function test_enabled_with_full_status_passes_and_reports_memory_and_hit_rate()
    {
        $status = [
            'opcache_enabled' => true,
            'memory_usage' => ['used_memory' => 25 * 1024 * 1024, 'free_memory' => 75 * 1024 * 1024],
            'opcache_statistics' => ['opcache_hit_rate' => 98.75],
        ];

        $result = $this->checkWith(true, $status)->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
        $this->assertCount(3, $result->getDetails());
        $this->assertSame('OPcache memory usage: 25% (25 MB used of 100 MB)', $result->getDetails()[1]['message']);
        $this->assertSame('OPcache hit rate: 98.8%', $result->getDetails()[2]['message']);
    }

    public function test_missing_memory_and_hit_rate_keys_are_skipped_not_faked()
    {
        $result = $this->checkWith(true, ['opcache_enabled' => true])->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
        $this->assertCount(1, $result->getDetails());
    }
}
