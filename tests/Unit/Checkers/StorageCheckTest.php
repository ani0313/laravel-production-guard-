<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\StorageCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Tests\TestCase;

class StorageCheckTest extends TestCase
{
    public function test_a_healthy_writable_storage_passes()
    {
        $result = $this->app->make(StorageCheck::class)->check();

        $this->assertNotSame(CheckStatus::CRITICAL, $result->getStatus());
    }

    public function test_it_cleans_up_its_own_temporary_file()
    {
        $before = glob(storage_path('framework/production_guard_test_*.tmp'));
        $this->assertEmpty($before);

        $this->app->make(StorageCheck::class)->check();

        $after = glob(storage_path('framework/production_guard_test_*.tmp'));
        $this->assertEmpty($after);
    }

    public function test_disk_usage_thresholds_are_read_from_config()
    {
        $this->app['config']->set('production-guard.thresholds.disk_warning', -1);
        $this->app['config']->set('production-guard.thresholds.disk_critical', 1000);

        $result = $this->app->make(StorageCheck::class)->check();

        $diskFact = $this->lastFact($result->getDetails());
        $this->assertSame(CheckStatus::WARNING, $diskFact['status']);
    }

    private function lastFact(array $facts)
    {
        return $facts[count($facts) - 1];
    }
}
