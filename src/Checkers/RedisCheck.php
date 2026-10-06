<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Throwable;

class RedisCheck extends AbstractCheck
{
    /** @var Container */
    private $container;

    /** @var Config */
    private $config;

    /**
     * @param Container $container
     * @param Config $config
     */
    public function __construct(Container $container, Config $config)
    {
        $this->container = $container;
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Redis';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::REDIS;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        if (!$this->isConfigured()) {
            return $this->aggregate([
                ['status' => CheckStatus::NOT_APPLICABLE, 'message' => 'Redis is not configured.'],
            ]);
        }

        try {
            $connection = $this->container->make('redis')->connection();
        } catch (Throwable $e) {
            return $this->aggregate([
                ['status' => CheckStatus::CRITICAL, 'message' => 'Redis connection could not be established.'],
            ]);
        }

        $facts = [$this->pingFact($connection)];
        $facts[] = $this->roundTripFact($connection);

        $memoryFact = $this->memoryFact($connection);
        if ($memoryFact !== null) {
            $facts[] = $memoryFact;
        }

        return $this->aggregate($facts);
    }

    /**
     * @return bool
     */
    private function isConfigured()
    {
        return !empty($this->config->get('database.redis.default')) || !empty($this->config->get('database.redis.client'));
    }

    /**
     * @param \Illuminate\Redis\Connections\Connection $connection
     * @return array
     */
    private function pingFact($connection)
    {
        try {
            $start = microtime(true);
            $connection->ping();
            $latencyMs = (microtime(true) - $start) * 1000;

            return ['status' => CheckStatus::PASS, 'message' => sprintf('Redis connection successful, ping %dms.', $latencyMs)];
        } catch (Throwable $e) {
            return ['status' => CheckStatus::CRITICAL, 'message' => 'Redis did not respond to PING.'];
        }
    }

    /**
     * @param \Illuminate\Redis\Connections\Connection $connection
     * @return array
     */
    private function roundTripFact($connection)
    {
        $key = 'production_guard_test_' . bin2hex(random_bytes(8));

        try {
            $connection->set($key, 'ok');
            $value = $connection->get($key);
            $connection->del($key);

            if ($value !== 'ok') {
                return ['status' => CheckStatus::WARNING, 'message' => 'Redis write succeeded but the read-back value was unexpected.'];
            }

            return ['status' => CheckStatus::PASS, 'message' => 'Redis read/write test successful.'];
        } catch (Throwable $e) {
            return ['status' => CheckStatus::CRITICAL, 'message' => 'Redis read/write test failed.'];
        }
    }

    /**
     * Best-effort only: memory usage is reported when it can be extracted
     * from INFO, and silently skipped (not UNKNOWN) otherwise - it's
     * genuinely optional diagnostic detail, not a pass/fail fact.
     *
     * @param \Illuminate\Redis\Connections\Connection $connection
     * @return array|null
     */
    private function memoryFact($connection)
    {
        try {
            $info = $connection->info();
        } catch (Throwable $e) {
            return null;
        }

        $used = null;

        if (isset($info['used_memory_human'])) {
            $used = $info['used_memory_human'];
        } elseif (isset($info['Memory']['used_memory_human'])) {
            $used = $info['Memory']['used_memory_human'];
        }

        if ($used === null) {
            return null;
        }

        return ['status' => CheckStatus::PASS, 'message' => "Redis memory used: {$used}"];
    }
}
