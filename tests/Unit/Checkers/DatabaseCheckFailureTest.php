<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\DatabaseCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Connection;
use Mockery;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DatabaseCheckFailureTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    /**
     * Mimics Laravel's DatabaseManager far enough for DatabaseCheck's
     * container->make('db')->connection() lookup.
     *
     * @param Connection $connection
     * @return object
     */
    private function containerWithDbBoundTo(Connection $connection)
    {
        $container = new Container();
        $container->bind('db', function () use ($connection) {
            return new class($connection) {
                private $connection;

                public function __construct($connection)
                {
                    $this->connection = $connection;
                }

                public function connection()
                {
                    return $this->connection;
                }
            };
        });

        return $container;
    }

    public function test_a_connection_exception_is_reported_as_critical_without_leaking_the_driver_exception_message()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('select')->with('select 1')->andThrow(new RuntimeException('mysql://root:supersecret@10.0.1.5/hf failed'));
        $connection->shouldReceive('getDriverName')->andReturn('mysql');

        $config = new Repository(['production-guard' => ['timeouts' => ['database' => 3]]]);

        $result = (new DatabaseCheck($this->containerWithDbBoundTo($connection), $config))->check();

        $this->assertSame(CheckStatus::CRITICAL, $result->getStatus());
        $this->assertNotContains('supersecret', $result->getMessage());
        $this->assertNotContains('supersecret', json_encode($result->toArray()));
    }

    public function test_a_slow_connection_is_a_warning_not_a_critical()
    {
        $connection = Mockery::mock(Connection::class);
        $connection->shouldReceive('select')->withAnyArgs()->andReturnUsing(function ($query) {
            if ($query === 'select 1') {
                usleep(5000);
            }

            return [];
        });
        $connection->shouldReceive('getDriverName')->andReturn('mysql');

        $config = new Repository(['production-guard' => ['timeouts' => ['database' => 0.001]]]);

        $result = (new DatabaseCheck($this->containerWithDbBoundTo($connection), $config))->check();

        $this->assertSame(CheckStatus::WARNING, $result->getStatus());
    }
}
