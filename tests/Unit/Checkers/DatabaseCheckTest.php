<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\DatabaseCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Tests\TestCase;

class DatabaseCheckTest extends TestCase
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

    public function test_it_connects_and_reports_the_sqlite_version()
    {
        $result = $this->app->make(DatabaseCheck::class)->check();

        $this->assertNotSame(CheckStatus::CRITICAL, $result->getStatus());

        $driverFact = $result->getDetails()[0];
        $this->assertSame(CheckStatus::PASS, $driverFact['status']);
        $this->assertStringStartsWith('Database connection successful (sqlite)', $driverFact['message']);

        $versionFact = $result->getDetails()[2];
        $this->assertSame(CheckStatus::PASS, $versionFact['status']);
        $this->assertStringStartsWith('Database version:', $versionFact['message']);
    }
}
