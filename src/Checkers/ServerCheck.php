<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Contracts\SystemMetrics;
use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Config\Repository as Config;

class ServerCheck extends AbstractCheck
{
    /** @var SystemMetrics */
    private $metrics;

    /** @var Config */
    private $config;

    /**
     * @param SystemMetrics $metrics
     * @param Config $config
     */
    public function __construct(SystemMetrics $metrics, Config $config)
    {
        $this->metrics = $metrics;
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Server';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::SERVER;
    }

    /**
     * Disk usage is StorageCheck's job (Phase 2) - this covers CPU, load,
     * memory, and swap only, so the two checkers don't double-report the
     * same fact.
     *
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $facts = [];
        $facts[] = $this->cpuFact();
        $facts[] = $this->coresFact();
        $facts[] = $this->loadFact();
        $facts[] = $this->memoryFact();
        $facts[] = $this->swapFact();

        return $this->aggregate($facts);
    }

    /**
     * @return array
     */
    private function cpuFact()
    {
        $percent = $this->metrics->cpuUsagePercent();

        if ($percent === null) {
            return ['status' => CheckStatus::UNKNOWN, 'message' => 'CPU usage could not be determined on this system.'];
        }

        return $this->thresholded(
            $percent,
            (float) $this->config->get('production-guard.thresholds.cpu_warning', 80),
            (float) $this->config->get('production-guard.thresholds.cpu_critical', 95),
            sprintf('CPU usage: %d%%', $percent)
        );
    }

    /**
     * @return array
     */
    private function coresFact()
    {
        $cores = $this->metrics->cpuCores();

        if ($cores === null) {
            return ['status' => CheckStatus::UNKNOWN, 'message' => 'CPU core count could not be determined on this system.'];
        }

        return ['status' => CheckStatus::PASS, 'message' => "CPU cores: {$cores}"];
    }

    /**
     * @return array
     */
    private function loadFact()
    {
        $load = $this->metrics->loadAverage();

        if ($load === null) {
            return ['status' => CheckStatus::UNKNOWN, 'message' => 'Load average could not be determined on this system.'];
        }

        $oneMinute = $load[0];

        return $this->thresholded(
            $oneMinute,
            (float) $this->config->get('production-guard.thresholds.load_warning', 2),
            (float) $this->config->get('production-guard.thresholds.load_critical', 4),
            sprintf('Load average: %.2f, %.2f, %.2f', $load[0], $load[1], $load[2])
        );
    }

    /**
     * @return array
     */
    private function memoryFact()
    {
        $total = $this->metrics->memoryTotalBytes();
        $used = $this->metrics->memoryUsedBytes();

        if ($total === null || $used === null || $total <= 0) {
            return ['status' => CheckStatus::UNKNOWN, 'message' => 'Memory usage could not be determined on this system.'];
        }

        $percent = ($used / $total) * 100;

        return $this->thresholded(
            $percent,
            (float) $this->config->get('production-guard.thresholds.memory_warning', 80),
            (float) $this->config->get('production-guard.thresholds.memory_critical', 90),
            sprintf('RAM usage: %d%% (%s used of %s)', $percent, $this->formatBytes($used), $this->formatBytes($total))
        );
    }

    /**
     * @return array
     */
    private function swapFact()
    {
        $total = $this->metrics->swapTotalBytes();
        $used = $this->metrics->swapUsedBytes();

        if ($total === null || $used === null) {
            return ['status' => CheckStatus::UNKNOWN, 'message' => 'Swap usage could not be determined on this system.'];
        }

        if ($total <= 0) {
            return ['status' => CheckStatus::PASS, 'message' => 'Swap is disabled on this system.'];
        }

        $percent = ($used / $total) * 100;

        return $this->thresholded(
            $percent,
            (float) $this->config->get('production-guard.thresholds.swap_warning', 50),
            (float) $this->config->get('production-guard.thresholds.swap_critical', 80),
            sprintf('Swap usage: %d%% (%s used of %s)', $percent, $this->formatBytes($used), $this->formatBytes($total))
        );
    }

    /**
     * @param float $value
     * @param float $warning
     * @param float $critical
     * @param string $message
     * @return array
     */
    private function thresholded($value, $warning, $critical, $message)
    {
        if ($value >= $critical) {
            return ['status' => CheckStatus::CRITICAL, 'message' => $message];
        }

        if ($value >= $warning) {
            return ['status' => CheckStatus::WARNING, 'message' => $message];
        }

        return ['status' => CheckStatus::PASS, 'message' => $message];
    }
}
