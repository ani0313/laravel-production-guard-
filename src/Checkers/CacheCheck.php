<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Cache\Factory as CacheFactory;
use Illuminate\Contracts\Config\Repository as Config;
use Throwable;

class CacheCheck extends AbstractCheck
{
    /** @var CacheFactory */
    private $cacheFactory;

    /** @var Config */
    private $config;

    /**
     * @param CacheFactory $cacheFactory
     * @param Config $config
     */
    public function __construct(CacheFactory $cacheFactory, Config $config)
    {
        $this->cacheFactory = $cacheFactory;
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Cache';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::CACHE;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $driver = (string) $this->config->get('cache.default');

        $facts = [$this->driverFact($driver)];

        // The array driver is entirely in-process and non-persistent - a
        // read/write round trip through it proves nothing about production
        // readiness, so it's skipped rather than faked.
        if ($driver !== 'array') {
            $facts[] = $this->roundTripFact($driver);
        }

        return $this->aggregate($facts);
    }

    /**
     * @param string $driver
     * @return array
     */
    private function driverFact($driver)
    {
        if ($driver === 'array') {
            return [
                'status' => CheckStatus::WARNING,
                'message' => 'Array cache is non-persistent and is generally unsuitable for multi-request production workloads.',
                'recommendation' => 'Use a persistent cache driver (file, redis, memcached, database) in production.',
            ];
        }

        return ['status' => CheckStatus::PASS, 'message' => "Cache driver: {$driver}"];
    }

    /**
     * @param string $driver
     * @return array
     */
    private function roundTripFact($driver)
    {
        $key = 'production_guard_test_' . bin2hex(random_bytes(8));

        try {
            $store = $this->cacheFactory->store();
            $start = microtime(true);

            $store->put($key, 'ok', 10);
            $value = $store->get($key);
            $store->forget($key);

            $latencyMs = (microtime(true) - $start) * 1000;

            if ($value !== 'ok') {
                return ['status' => CheckStatus::WARNING, 'message' => "Cache write succeeded but the read-back value was unexpected ({$driver})."];
            }

            return ['status' => CheckStatus::PASS, 'message' => sprintf('Cache read/write test successful (%s), latency %dms.', $driver, $latencyMs)];
        } catch (Throwable $e) {
            return ['status' => CheckStatus::CRITICAL, 'message' => "Cache read/write test failed ({$driver}). See the application logs for details."];
        }
    }
}
