<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\CacheCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Tests\TestCase;

class CacheCheckTest extends TestCase
{
    public function test_array_driver_is_a_warning_and_skips_the_round_trip()
    {
        $this->app['config']->set('cache.default', 'array');

        $result = $this->app->make(CacheCheck::class)->check();

        $this->assertSame(CheckStatus::WARNING, $result->getStatus());
        $this->assertCount(1, $result->getDetails());
    }

    public function test_file_driver_passes_the_read_write_round_trip()
    {
        $this->app['config']->set('cache.default', 'file');

        $result = $this->app->make(CacheCheck::class)->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
        $this->assertCount(2, $result->getDetails());
    }

    public function test_the_round_trip_never_touches_a_real_application_key()
    {
        $this->app['config']->set('cache.default', 'file');
        $this->app['cache']->put('a_real_app_key', 'do-not-touch', 60);

        $this->app->make(CacheCheck::class)->check();

        $this->assertSame('do-not-touch', $this->app['cache']->get('a_real_app_key'));
    }
}
