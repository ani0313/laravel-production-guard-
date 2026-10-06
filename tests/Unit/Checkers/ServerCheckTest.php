<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\ServerCheck;
use Anivesh\LaravelProductionGuard\Contracts\SystemMetrics;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Config\Repository;
use Mockery;
use PHPUnit\Framework\TestCase;

class ServerCheckTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    private function config()
    {
        return new Repository(['production-guard' => ['thresholds' => [
            'cpu_warning' => 80, 'cpu_critical' => 95,
            'memory_warning' => 80, 'memory_critical' => 90,
            'load_warning' => 2, 'load_critical' => 4,
            'swap_warning' => 50, 'swap_critical' => 80,
        ]]]);
    }

    private function metrics(array $overrides = [])
    {
        $defaults = [
            'cpuUsagePercent' => 10.0,
            'cpuCores' => 4,
            'loadAverage' => [0.5, 0.4, 0.3],
            'memoryTotalBytes' => 1000,
            'memoryUsedBytes' => 100,
            'swapTotalBytes' => 1000,
            'swapUsedBytes' => 0,
        ];
        $values = array_merge($defaults, $overrides);

        $metrics = Mockery::mock(SystemMetrics::class);
        foreach ($values as $method => $value) {
            $metrics->shouldReceive($method)->andReturn($value);
        }

        return $metrics;
    }

    public function test_a_healthy_server_passes()
    {
        $result = (new ServerCheck($this->metrics(), $this->config()))->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
    }

    public function test_high_cpu_is_critical()
    {
        $result = (new ServerCheck($this->metrics(['cpuUsagePercent' => 99.0]), $this->config()))->check();

        $this->assertSame(CheckStatus::CRITICAL, $result->getStatus());
    }

    public function test_high_memory_usage_is_a_warning()
    {
        $result = (new ServerCheck($this->metrics(['memoryTotalBytes' => 1000, 'memoryUsedBytes' => 850]), $this->config()))->check();

        $this->assertSame(CheckStatus::WARNING, $result->getStatus());
    }

    public function test_disabled_swap_passes_rather_than_dividing_by_zero()
    {
        $result = (new ServerCheck($this->metrics(['swapTotalBytes' => 0, 'swapUsedBytes' => 0]), $this->config()))->check();

        $swapFact = $result->getDetails()[4];
        $this->assertSame(CheckStatus::PASS, $swapFact['status']);
        $this->assertSame('Swap is disabled on this system.', $swapFact['message']);
    }

    public function test_unknown_metrics_never_crash_and_report_unknown_not_a_false_pass()
    {
        $result = (new ServerCheck($this->metrics([
            'cpuUsagePercent' => null, 'cpuCores' => null, 'loadAverage' => null,
            'memoryTotalBytes' => null, 'memoryUsedBytes' => null,
            'swapTotalBytes' => null, 'swapUsedBytes' => null,
        ]), $this->config()))->check();

        $this->assertSame(CheckStatus::UNKNOWN, $result->getStatus());
        foreach ($result->getDetails() as $fact) {
            $this->assertSame(CheckStatus::UNKNOWN, $fact['status']);
        }
    }
}
