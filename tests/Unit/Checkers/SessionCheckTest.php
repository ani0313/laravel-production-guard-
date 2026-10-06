<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\SessionCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Tests\TestCase;

class SessionCheckTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '',
        ]);
    }

    public function test_array_driver_is_a_warning()
    {
        $this->app['config']->set('session.driver', 'array');

        $result = $this->app->make(SessionCheck::class)->check();

        $this->assertSame(CheckStatus::WARNING, $result->getStatus());
    }

    public function test_file_driver_checks_storage_framework_sessions()
    {
        $this->app['config']->set('session.driver', 'file');

        $result = $this->app->make(SessionCheck::class)->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
        $this->assertCount(2, $result->getDetails());
    }

    public function test_database_driver_is_critical_when_the_sessions_table_is_missing()
    {
        $this->app['config']->set('session.driver', 'database');

        $result = $this->app->make(SessionCheck::class)->check();

        $this->assertSame(CheckStatus::CRITICAL, $result->getDetails()[1]['status']);
    }

    public function test_database_driver_passes_when_the_sessions_table_exists()
    {
        $this->app['config']->set('session.driver', 'database');
        $this->app['db']->connection()->getSchemaBuilder()->create('sessions', function ($table) {
            $table->string('id')->primary();
        });

        $result = $this->app->make(SessionCheck::class)->check();

        $this->assertSame(CheckStatus::PASS, $result->getDetails()[1]['status']);
    }
}
