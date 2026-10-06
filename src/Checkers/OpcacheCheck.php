<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;

class OpcacheCheck extends AbstractCheck
{
    /**
     * @return string
     */
    public function getName()
    {
        return 'OPcache';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::OPCACHE;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        if (!$this->iniEnabled()) {
            return $this->aggregate([
                [
                    'status' => CheckStatus::WARNING,
                    'message' => 'OPcache is disabled.',
                    'recommendation' => 'Enable opcache.enable in php.ini for production.',
                ],
            ]);
        }

        $status = $this->liveStatus();

        // `opcache.enable` reflects configuration; the live status call can
        // still fail purely because this process is CLI (which has its own,
        // often-different opcache.enable_cli setting) - that is NOT the
        // same thing as OPcache actually being off for the web-facing SAPI,
        // so it's reported as unknown rather than a false "disabled".
        if ($status === null || empty($status['opcache_enabled'])) {
            return $this->aggregate([
                [
                    'status' => CheckStatus::UNKNOWN,
                    'message' => 'OPcache is enabled per configuration, but its live status could not be read from this process.',
                ],
            ]);
        }

        $facts = [['status' => CheckStatus::PASS, 'message' => 'OPcache is enabled.']];

        $memoryFact = $this->memoryFact($status);
        if ($memoryFact !== null) {
            $facts[] = $memoryFact;
        }

        $hitRateFact = $this->hitRateFact($status);
        if ($hitRateFact !== null) {
            $facts[] = $hitRateFact;
        }

        return $this->aggregate($facts);
    }

    /**
     * Test seam: overridden by an anonymous subclass in tests to avoid
     * depending on this process's real php.ini.
     *
     * @return bool
     */
    protected function iniEnabled()
    {
        return ini_get('opcache.enable') == '1';
    }

    /**
     * Test seam: overridden by an anonymous subclass in tests, since this
     * process's live OPcache status can't be controlled from a test.
     *
     * @return array|null
     */
    protected function liveStatus()
    {
        if (!function_exists('opcache_get_status')) {
            return null;
        }

        $status = @opcache_get_status(false);

        return $status === false ? null : $status;
    }

    /**
     * @param array $status
     * @return array|null
     */
    private function memoryFact(array $status)
    {
        if (!isset($status['memory_usage']['used_memory'], $status['memory_usage']['free_memory'])) {
            return null;
        }

        $used = $status['memory_usage']['used_memory'];
        $free = $status['memory_usage']['free_memory'];
        $total = $used + $free;

        if ($total <= 0) {
            return null;
        }

        $percent = ($used / $total) * 100;

        return ['status' => CheckStatus::PASS, 'message' => sprintf('OPcache memory usage: %d%% (%s used of %s)', $percent, $this->formatBytes($used), $this->formatBytes($total))];
    }

    /**
     * @param array $status
     * @return array|null
     */
    private function hitRateFact(array $status)
    {
        if (!isset($status['opcache_statistics']['opcache_hit_rate'])) {
            return null;
        }

        $rate = $status['opcache_statistics']['opcache_hit_rate'];

        return ['status' => CheckStatus::PASS, 'message' => sprintf('OPcache hit rate: %.1f%%', $rate)];
    }
}
