<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\QueueCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Tests\TestCase;

class QueueCheckTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function test_sync_driver_is_a_warning()
    {
        $this->app['config']->set('queue.default', 'sync');

        $result = $this->app->make(QueueCheck::class)->check();

        $this->assertSame(CheckStatus::WARNING, $result->getDetails()[0]['status']);
        $this->assertStringContainsLike('synchronous', $result->getDetails()[0]['message']);
    }

    public function test_null_driver_is_a_warning()
    {
        $this->app['config']->set('queue.default', 'null');

        $result = $this->app->make(QueueCheck::class)->check();

        $this->assertSame(CheckStatus::WARNING, $result->getDetails()[0]['status']);
    }

    public function test_database_driver_checks_the_jobs_table()
    {
        $this->app['config']->set('queue.default', 'database');
        $this->app['config']->set('queue.connections.database.table', 'jobs');
        $this->app['db']->connection()->getSchemaBuilder()->create('jobs', function ($table) {
            $table->bigIncrements('id');
        });

        $result = $this->app->make(QueueCheck::class)->check();

        $driverFact = $result->getDetails()[0];
        $tableFact = $result->getDetails()[1];

        $this->assertSame(CheckStatus::PASS, $driverFact['status']);
        $this->assertSame(CheckStatus::PASS, $tableFact['status']);
    }

    public function test_database_driver_is_critical_when_the_jobs_table_is_missing()
    {
        $this->app['config']->set('queue.default', 'database');

        $result = $this->app->make(QueueCheck::class)->check();

        $this->assertSame(CheckStatus::CRITICAL, $result->getDetails()[1]['status']);
    }

    public function test_beanstalkd_driver_passes_when_the_host_is_reachable()
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        $name = stream_socket_get_name($server, false);
        $port = (int) substr($name, strrpos($name, ':') + 1);

        $this->app['config']->set('queue.default', 'beanstalkd');
        $this->app['config']->set('queue.connections.beanstalkd.host', '127.0.0.1');
        $this->app['config']->set('queue.connections.beanstalkd.port', $port);

        try {
            $result = $this->app->make(QueueCheck::class)->check();
            $this->assertSame(CheckStatus::PASS, $result->getDetails()[1]['status']);
        } finally {
            fclose($server);
        }
    }

    public function test_beanstalkd_driver_is_critical_when_unreachable()
    {
        $this->app['config']->set('queue.default', 'beanstalkd');
        $this->app['config']->set('queue.connections.beanstalkd.host', '127.0.0.1');
        $this->app['config']->set('queue.connections.beanstalkd.port', 1);
        $this->app['config']->set('production-guard.timeouts.beanstalkd', 0.5);

        $result = $this->app->make(QueueCheck::class)->check();

        $this->assertSame(CheckStatus::CRITICAL, $result->getDetails()[1]['status']);
    }

    public function test_sqs_driver_warns_about_missing_configuration()
    {
        $this->app['config']->set('queue.default', 'sqs');
        $this->app['config']->set('queue.connections.sqs', []);

        $result = $this->app->make(QueueCheck::class)->check();

        $this->assertSame(CheckStatus::WARNING, $result->getDetails()[1]['status']);
    }

    public function test_sqs_driver_passes_when_fully_configured()
    {
        $this->app['config']->set('queue.default', 'sqs');
        $this->app['config']->set('queue.connections.sqs', [
            'key' => 'k', 'secret' => 's', 'queue' => 'q', 'region' => 'us-east-1',
        ]);

        $result = $this->app->make(QueueCheck::class)->check();

        $this->assertSame(CheckStatus::PASS, $result->getDetails()[1]['status']);
    }

    public function test_failed_jobs_table_missing_is_not_supported_not_a_false_pass()
    {
        $this->app['config']->set('queue.default', 'sync');

        $result = $this->app->make(QueueCheck::class)->check();

        $lastFact = $this->lastFact($result->getDetails());
        $this->assertSame(CheckStatus::NOT_SUPPORTED, $lastFact['status']);
    }

    public function test_failed_jobs_are_reported_as_a_warning_with_a_recommendation()
    {
        $this->app['config']->set('queue.default', 'sync');
        $this->app['db']->connection()->getSchemaBuilder()->create('failed_jobs', function ($table) {
            $table->bigIncrements('id');
        });
        $this->app['db']->connection()->table('failed_jobs')->insert([['id' => 1], ['id' => 2]]);

        $result = $this->app->make(QueueCheck::class)->check();

        $lastFact = $this->lastFact($result->getDetails());
        $this->assertSame(CheckStatus::WARNING, $lastFact['status']);
        $this->assertSame('2 failed job(s) detected.', $lastFact['message']);
        $this->assertSame('php artisan queue:failed', $lastFact['recommendation']);
    }

    private function lastFact(array $facts)
    {
        return $facts[count($facts) - 1];
    }

    private function assertStringContainsLike($needle, $haystack)
    {
        $this->assertTrue(strpos($haystack, $needle) !== false, "Failed asserting that '{$haystack}' contains '{$needle}'.");
    }
}
