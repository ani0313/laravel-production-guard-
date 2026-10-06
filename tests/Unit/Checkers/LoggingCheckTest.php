<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\LoggingCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Tests\TestCase;

class LoggingCheckTest extends TestCase
{
    public function test_a_configured_channel_passes()
    {
        $this->app['config']->set('logging.default', 'stack');

        $result = $this->app->make(LoggingCheck::class)->check();

        $this->assertSame(CheckStatus::PASS, $result->getDetails()[0]['status']);
        $this->assertSame('Log channel: stack', $result->getDetails()[0]['message']);
    }

    public function test_storage_logs_writable_passes()
    {
        $result = $this->app->make(LoggingCheck::class)->check();

        $this->assertSame(CheckStatus::PASS, $result->getDetails()[1]['status']);
    }

    public function test_test_write_is_disabled_by_default()
    {
        $result = $this->app->make(LoggingCheck::class)->check();

        $this->assertCount(2, $result->getDetails());
    }

    public function test_test_write_runs_only_when_explicitly_enabled()
    {
        $this->app['config']->set('production-guard.logging.test_write_enabled', true);

        $result = $this->app->make(LoggingCheck::class)->check();

        $this->assertCount(3, $result->getDetails());
        $this->assertSame(CheckStatus::PASS, $result->getDetails()[2]['status']);
    }
}
