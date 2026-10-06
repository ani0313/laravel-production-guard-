<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Throwable;

class DatabaseCheck extends AbstractCheck
{
    /** @var Container */
    private $container;

    /** @var Config */
    private $config;

    /**
     * Laravel binds both the connection resolver ('db') and the migration
     * repository ('migration.repository') to string keys, not to their
     * interfaces, so they're resolved from the container lazily rather
     * than type-hinted directly in the constructor.
     *
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
        return 'Database';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::DATABASE;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $connection = $this->container->make('db')->connection();
        $driver = $connection->getDriverName();

        $facts = [];
        list($connected, $latencyMs) = $this->connectionFact($connection, $driver);
        $facts[] = $connected;

        if ($latencyMs !== null) {
            $facts[] = $this->latencyFact($latencyMs);
            $facts[] = $this->versionFact($connection, $driver);
        }

        return $this->aggregate($facts);
    }

    /**
     * @param \Illuminate\Database\Connection $connection
     * @param string $driver
     * @return array{0: array, 1: float|null}
     */
    private function connectionFact($connection, $driver)
    {
        $timeout = (float) $this->config->get('production-guard.timeouts.database', 3);
        $start = microtime(true);

        try {
            $connection->select('select 1');
            $latencyMs = (microtime(true) - $start) * 1000;

            $fact = ['status' => CheckStatus::PASS, 'message' => "Database connection successful ({$driver})."];

            if ($latencyMs > ($timeout * 1000)) {
                $fact = [
                    'status' => CheckStatus::WARNING,
                    'message' => sprintf('Database responded, but took %dms - longer than the configured %ds timeout.', $latencyMs, $timeout),
                ];
            }

            return [$fact, $latencyMs];
        } catch (Throwable $e) {
            return [[
                'status' => CheckStatus::CRITICAL,
                'message' => 'Database connection failed. See the application logs for details.',
            ], null];
        }
    }

    /**
     * @param float $latencyMs
     * @return array
     */
    private function latencyFact($latencyMs)
    {
        $warningMs = (float) $this->config->get('production-guard.thresholds.database_latency_warning_ms', 500);

        if ($latencyMs > $warningMs) {
            return [
                'status' => CheckStatus::WARNING,
                'message' => sprintf('Query latency is %dms (above the %dms warning threshold).', $latencyMs, $warningMs),
            ];
        }

        return ['status' => CheckStatus::PASS, 'message' => sprintf('Query latency: %dms', $latencyMs)];
    }

    /**
     * @param \Illuminate\Database\Connection $connection
     * @param string $driver
     * @return array
     */
    private function versionFact($connection, $driver)
    {
        $queries = [
            'mysql' => 'select version() as version',
            'pgsql' => 'select version() as version',
            'sqlite' => 'select sqlite_version() as version',
            'sqlsrv' => 'select @@VERSION as version',
        ];

        if (!isset($queries[$driver])) {
            return ['status' => CheckStatus::NOT_SUPPORTED, 'message' => "Database version detection is not supported for driver '{$driver}'."];
        }

        try {
            $row = $connection->select($queries[$driver]);
            $version = isset($row[0]->version) ? $row[0]->version : null;

            if ($version === null) {
                return ['status' => CheckStatus::UNKNOWN, 'message' => 'Could not determine the database version.'];
            }

            return ['status' => CheckStatus::PASS, 'message' => "Database version: {$version}"];
        } catch (Throwable $e) {
            return ['status' => CheckStatus::UNKNOWN, 'message' => 'Could not determine the database version.'];
        }
    }

}
