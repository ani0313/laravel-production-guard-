<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\BeanstalkdCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Tests\TestCase;

class BeanstalkdCheckTest extends TestCase
{
    public function test_not_applicable_when_beanstalkd_is_not_the_queue_driver()
    {
        $this->app['config']->set('queue.default', 'sync');

        $result = $this->app->make(BeanstalkdCheck::class)->check();

        $this->assertSame(CheckStatus::NOT_APPLICABLE, $result->getStatus());
    }

    public function test_warns_when_no_host_is_configured()
    {
        $this->app['config']->set('queue.default', 'beanstalkd');
        $this->app['config']->set('queue.connections.beanstalkd.host', null);

        $result = $this->app->make(BeanstalkdCheck::class)->check();

        $this->assertSame(CheckStatus::WARNING, $result->getStatus());
    }

    public function test_passes_when_the_host_is_reachable()
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($server, false);
        $port = (int) substr($name, strrpos($name, ':') + 1);

        $this->app['config']->set('queue.default', 'beanstalkd');
        $this->app['config']->set('queue.connections.beanstalkd.host', '127.0.0.1');
        $this->app['config']->set('queue.connections.beanstalkd.port', $port);

        try {
            $result = $this->app->make(BeanstalkdCheck::class)->check();
            $this->assertSame(CheckStatus::PASS, $result->getStatus());
        } finally {
            fclose($server);
        }
    }

    public function test_critical_when_unreachable()
    {
        $this->app['config']->set('queue.default', 'beanstalkd');
        $this->app['config']->set('queue.connections.beanstalkd.host', '127.0.0.1');
        $this->app['config']->set('queue.connections.beanstalkd.port', 1);
        $this->app['config']->set('production-guard.timeouts.beanstalkd', 0.5);

        $result = $this->app->make(BeanstalkdCheck::class)->check();

        $this->assertSame(CheckStatus::CRITICAL, $result->getStatus());
    }
}
