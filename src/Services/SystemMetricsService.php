<?php

namespace Anivesh\LaravelProductionGuard\Services;

use Anivesh\LaravelProductionGuard\Contracts\SystemMetrics;

/**
 * Linux-first (/proc), per the package's own scope: "Support Linux
 * primarily... If unsupported: UNKNOWN. Never crash." Every method returns
 * null rather than guessing on an unsupported OS. File reading is kept
 * separate from parsing so the parsing logic itself (the part worth
 * getting right) is unit-testable on any host OS via fixture strings.
 */
class SystemMetricsService implements SystemMetrics
{
    /** @var float Seconds between the two /proc/stat samples used for CPU usage. */
    private $sampleWindow;

    /**
     * @param float $sampleWindow
     */
    public function __construct($sampleWindow = 0.1)
    {
        $this->sampleWindow = $sampleWindow;
    }

    /**
     * @return float|null
     */
    public function cpuUsagePercent()
    {
        $first = $this->readProcStatCpuLine();
        if ($first === null) {
            return null;
        }

        usleep((int) ($this->sampleWindow * 1000000));

        $second = $this->readProcStatCpuLine();
        if ($second === null) {
            return null;
        }

        return self::calculateCpuPercent($first, $second);
    }

    /**
     * @return int|null
     */
    public function cpuCores()
    {
        $content = @file_get_contents('/proc/cpuinfo');

        return $content === false ? null : self::countProcessors($content);
    }

    /**
     * @return float[]|null
     */
    public function loadAverage()
    {
        $load = @sys_getloadavg();

        return $load === false ? null : $load;
    }

    /**
     * @return int|null
     */
    public function memoryTotalBytes()
    {
        $meminfo = $this->readMeminfo();

        return $meminfo !== null && isset($meminfo['MemTotal']) ? $meminfo['MemTotal'] : null;
    }

    /**
     * @return int|null
     */
    public function memoryUsedBytes()
    {
        $meminfo = $this->readMeminfo();

        if ($meminfo === null || !isset($meminfo['MemTotal'], $meminfo['MemAvailable'])) {
            return null;
        }

        return $meminfo['MemTotal'] - $meminfo['MemAvailable'];
    }

    /**
     * @return int|null
     */
    public function swapTotalBytes()
    {
        $meminfo = $this->readMeminfo();

        return $meminfo !== null && isset($meminfo['SwapTotal']) ? $meminfo['SwapTotal'] : null;
    }

    /**
     * @return int|null
     */
    public function swapUsedBytes()
    {
        $meminfo = $this->readMeminfo();

        if ($meminfo === null || !isset($meminfo['SwapTotal'], $meminfo['SwapFree'])) {
            return null;
        }

        return $meminfo['SwapTotal'] - $meminfo['SwapFree'];
    }

    /**
     * @param string $path
     * @return int|null
     */
    public function diskTotalBytes($path)
    {
        $value = @disk_total_space($path);

        return $value === false ? null : (int) $value;
    }

    /**
     * @param string $path
     * @return int|null
     */
    public function diskFreeBytes($path)
    {
        $value = @disk_free_space($path);

        return $value === false ? null : (int) $value;
    }

    /**
     * @return array|null
     */
    private function readProcStatCpuLine()
    {
        $content = @file_get_contents('/proc/stat');

        return $content === false ? null : self::parseProcStatCpuLine($content);
    }

    /**
     * @return array|null
     */
    private function readMeminfo()
    {
        $content = @file_get_contents('/proc/meminfo');

        return $content === false ? null : self::parseMeminfo($content);
    }

    /**
     * Parses the first line of /proc/stat, e.g.
     * "cpu  123 0 456 7890 12 0 3 0".
     *
     * @param string $content
     * @return array|null
     */
    public static function parseProcStatCpuLine($content)
    {
        $firstLine = strtok($content, "\n");
        $parts = preg_split('/\s+/', trim($firstLine));

        if (empty($parts) || $parts[0] !== 'cpu') {
            return null;
        }

        return [
            'user' => (int) self::value($parts, 1),
            'nice' => (int) self::value($parts, 2),
            'system' => (int) self::value($parts, 3),
            'idle' => (int) self::value($parts, 4),
            'iowait' => (int) self::value($parts, 5),
            'irq' => (int) self::value($parts, 6),
            'softirq' => (int) self::value($parts, 7),
            'steal' => (int) self::value($parts, 8),
        ];
    }

    /**
     * @param array $first
     * @param array $second
     * @return float
     */
    public static function calculateCpuPercent(array $first, array $second)
    {
        $prevIdle = $first['idle'] + $first['iowait'];
        $idle = $second['idle'] + $second['iowait'];

        $prevNonIdle = $first['user'] + $first['nice'] + $first['system'] + $first['irq'] + $first['softirq'] + $first['steal'];
        $nonIdle = $second['user'] + $second['nice'] + $second['system'] + $second['irq'] + $second['softirq'] + $second['steal'];

        $prevTotal = $prevIdle + $prevNonIdle;
        $total = $idle + $nonIdle;

        $totalDelta = $total - $prevTotal;
        $idleDelta = $idle - $prevIdle;

        if ($totalDelta <= 0) {
            return 0.0;
        }

        return (($totalDelta - $idleDelta) / $totalDelta) * 100;
    }

    /**
     * @param string $content
     * @return int
     */
    public static function countProcessors($content)
    {
        return preg_match_all('/^processor\s*:/m', $content);
    }

    /**
     * Parses /proc/meminfo into bytes, keyed by field name (e.g.
     * "MemTotal", "MemAvailable", "SwapTotal", "SwapFree").
     *
     * @param string $content
     * @return array
     */
    public static function parseMeminfo($content)
    {
        $result = [];

        foreach (explode("\n", $content) as $line) {
            if (preg_match('/^(\w+):\s+(\d+)\s*kB/', $line, $matches)) {
                $result[$matches[1]] = ((int) $matches[2]) * 1024;
            }
        }

        return $result;
    }

    /**
     * @param array $parts
     * @param int $index
     * @return string
     */
    private static function value(array $parts, $index)
    {
        return isset($parts[$index]) ? $parts[$index] : 0;
    }
}
