<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\ApplicationCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Tests\TestCase;

class ApplicationCheckTest extends TestCase
{
    public function test_it_reports_the_laravel_version()
    {
        $result = $this->app->make(ApplicationCheck::class)->check();

        $this->assertSame('Laravel ' . $this->app->version(), $result->getDetails()[0]['message']);
    }

    public function test_maintenance_mode_is_critical_when_the_down_file_is_present()
    {
        $downFile = storage_path('framework/down');
        file_put_contents($downFile, json_encode(['time' => time()]));

        try {
            $result = $this->app->make(ApplicationCheck::class)->check();
            $this->assertSame(CheckStatus::CRITICAL, $result->getStatus());
        } finally {
            @unlink($downFile);
        }
    }

    public function test_it_does_not_crash_when_nothing_is_cached()
    {
        $result = $this->app->make(ApplicationCheck::class)->check();

        $this->assertNotNull($result->getStatus());
        $this->assertNotEmpty($result->getDetails());
    }
}
