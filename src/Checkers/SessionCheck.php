<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Throwable;

class SessionCheck extends AbstractCheck
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
        return 'Session';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::SESSION;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $driver = (string) $this->config->get('session.driver');
        $facts = [$this->driverFact($driver)];

        if ($driver === 'file') {
            $facts[] = $this->fileStorageFact();
        } elseif ($driver === 'database') {
            $facts[] = $this->databaseTableFact();
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
                'message' => 'Array session driver does not persist between requests.',
                'recommendation' => 'Use a persistent session driver (file, database, redis, memcached) in production.',
            ];
        }

        return ['status' => CheckStatus::PASS, 'message' => "Session driver: {$driver}"];
    }

    /**
     * @return array
     */
    private function fileStorageFact()
    {
        $path = storage_path('framework/sessions');

        if (!is_dir($path) || !is_writable($path)) {
            return [
                'status' => CheckStatus::CRITICAL,
                'message' => "storage/framework/sessions is not writable ({$path}).",
            ];
        }

        return ['status' => CheckStatus::PASS, 'message' => 'storage/framework/sessions is writable.'];
    }

    /**
     * @return array
     */
    private function databaseTableFact()
    {
        $table = $this->config->get('session.table', 'sessions');

        try {
            $this->container->make('db')->connection()->table($table)->count();

            return ['status' => CheckStatus::PASS, 'message' => "Session table '{$table}' is reachable."];
        } catch (Throwable $e) {
            return ['status' => CheckStatus::CRITICAL, 'message' => "Session table '{$table}' could not be reached."];
        }
    }
}
