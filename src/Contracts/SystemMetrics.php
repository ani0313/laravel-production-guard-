<?php

namespace Anivesh\LaravelProductionGuard\Contracts;

/**
 * Abstraction over OS-level metrics (CPU/RAM/disk/load/swap), implemented by
 * Anivesh\LaravelProductionGuard\Services\SystemMetricsService (added in
 * ServerCheck's phase). Every method must return null - never throw and
 * never guess - when a metric can't be safely determined on the current OS.
 */
interface SystemMetrics
{
    /**
     * @return float|null Percentage 0-100
     */
    public function cpuUsagePercent();

    /**
     * @return int|null
     */
    public function cpuCores();

    /**
     * @return float[]|null [1min, 5min, 15min]
     */
    public function loadAverage();

    /**
     * @return int|null Bytes
     */
    public function memoryTotalBytes();

    /**
     * @return int|null Bytes
     */
    public function memoryUsedBytes();

    /**
     * @return int|null Bytes
     */
    public function swapTotalBytes();

    /**
     * @return int|null Bytes
     */
    public function swapUsedBytes();

    /**
     * @param string $path
     * @return int|null Bytes
     */
    public function diskTotalBytes($path);

    /**
     * @param string $path
     * @return int|null Bytes
     */
    public function diskFreeBytes($path);
}
