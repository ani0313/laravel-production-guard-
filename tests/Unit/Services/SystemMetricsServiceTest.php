<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Services;

use Anivesh\LaravelProductionGuard\Services\SystemMetricsService;
use PHPUnit\Framework\TestCase;

class SystemMetricsServiceTest extends TestCase
{
    public function test_parse_proc_stat_cpu_line_extracts_the_cpu_fields()
    {
        $parsed = SystemMetricsService::parseProcStatCpuLine("cpu  100 0 50 850 0 0 0 0\ncpu0 ...\n");

        $this->assertSame([
            'user' => 100, 'nice' => 0, 'system' => 50, 'idle' => 850,
            'iowait' => 0, 'irq' => 0, 'softirq' => 0, 'steal' => 0,
        ], $parsed);
    }

    public function test_parse_proc_stat_cpu_line_returns_null_for_unexpected_content()
    {
        $this->assertNull(SystemMetricsService::parseProcStatCpuLine("not the right format\n"));
    }

    public function test_calculate_cpu_percent_from_two_samples()
    {
        $first = ['user' => 100, 'nice' => 0, 'system' => 50, 'idle' => 850, 'iowait' => 0, 'irq' => 0, 'softirq' => 0, 'steal' => 0];
        // 100 more busy ticks (user), 0 more idle ticks -> busy the whole delta.
        $second = ['user' => 200, 'nice' => 0, 'system' => 50, 'idle' => 850, 'iowait' => 0, 'irq' => 0, 'softirq' => 0, 'steal' => 0];

        $this->assertEquals(100.0, SystemMetricsService::calculateCpuPercent($first, $second), '', 0.01);
    }

    public function test_calculate_cpu_percent_is_zero_when_fully_idle()
    {
        $first = ['user' => 100, 'nice' => 0, 'system' => 50, 'idle' => 850, 'iowait' => 0, 'irq' => 0, 'softirq' => 0, 'steal' => 0];
        $second = ['user' => 100, 'nice' => 0, 'system' => 50, 'idle' => 950, 'iowait' => 0, 'irq' => 0, 'softirq' => 0, 'steal' => 0];

        $this->assertEquals(0.0, SystemMetricsService::calculateCpuPercent($first, $second), '', 0.01);
    }

    public function test_count_processors_counts_processor_lines()
    {
        $content = "processor\t: 0\nvendor_id\t: x\nprocessor\t: 1\nprocessor\t: 2\n";

        $this->assertSame(3, SystemMetricsService::countProcessors($content));
    }

    public function test_parse_meminfo_converts_kb_to_bytes()
    {
        $content = "MemTotal:       16384000 kB\nMemAvailable:    8192000 kB\nSwapTotal:       2048000 kB\nSwapFree:        2048000 kB\n";

        $parsed = SystemMetricsService::parseMeminfo($content);

        $this->assertSame(16384000 * 1024, $parsed['MemTotal']);
        $this->assertSame(8192000 * 1024, $parsed['MemAvailable']);
        $this->assertSame(2048000 * 1024, $parsed['SwapTotal']);
        $this->assertSame(2048000 * 1024, $parsed['SwapFree']);
    }

    public function test_load_average_uses_the_real_sys_getloadavg_and_never_throws()
    {
        $service = new SystemMetricsService();

        $load = $service->loadAverage();

        if ($load !== null) {
            $this->assertCount(3, $load);
        } else {
            $this->assertNull($load);
        }
    }

    public function test_disk_metrics_work_cross_platform_via_php_builtins()
    {
        $service = new SystemMetricsService();

        $this->assertInternalType('integer', $service->diskTotalBytes(sys_get_temp_dir()));
        $this->assertInternalType('integer', $service->diskFreeBytes(sys_get_temp_dir()));
    }

    public function test_proc_based_metrics_never_crash_when_unsupported()
    {
        $service = new SystemMetricsService();

        // On a non-Linux host (or any host without /proc) these must
        // degrade to null rather than error - this assertion documents
        // that contract without hard-depending on which OS runs the suite.
        $cores = $service->cpuCores();
        $this->assertTrue($cores === null || is_int($cores));

        $memory = $service->memoryTotalBytes();
        $this->assertTrue($memory === null || is_int($memory));
    }
}
