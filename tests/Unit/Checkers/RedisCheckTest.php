<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\RedisCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Tests\TestCase;

class RedisCheckTest extends TestCase
{
    public function test_not_applicable_when_redis_is_not_configured()
    {
        $this->app['config']->set('database.redis', []);

        $result = $this->app->make(RedisCheck::class)->check();

        $this->assertSame(CheckStatus::NOT_APPLICABLE, $result->getStatus());
    }

    public function test_critical_when_configured_but_unreachable()
    {
        $this->app['config']->set('database.redis', [
            'client' => 'predis',
            'default' => ['host' => '127.0.0.1', 'port' => 1, 'timeout' => 0.5],
        ]);

        $result = $this->app->make(RedisCheck::class)->check();

        $this->assertSame(CheckStatus::CRITICAL, $result->getStatus());
    }

    public function test_a_real_redis_round_trip_never_touches_a_real_application_key()
    {
        if (!$this->localRedisIsReachable()) {
            $this->markTestSkipped('No local Redis reachable on 127.0.0.1:6379.');
        }

        $this->app['config']->set('database.redis', [
            'client' => 'predis',
            'default' => ['host' => '127.0.0.1', 'port' => 6379, 'timeout' => 1],
        ]);

        $this->app['redis']->connection()->set('a_real_app_key', 'do-not-touch');

        $result = $this->app->make(RedisCheck::class)->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
        $this->assertSame('do-not-touch', $this->app['redis']->connection()->get('a_real_app_key'));

        $this->app['redis']->connection()->del('a_real_app_key');
    }

    private function localRedisIsReachable()
    {
        $socket = @fsockopen('127.0.0.1', 6379, $errno, $errstr, 0.5);

        if ($socket === false) {
            return false;
        }

        fclose($socket);

        return true;
    }
}
