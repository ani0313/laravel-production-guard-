<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Throwable;

class QueueCheck extends AbstractCheck
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
        return 'Queue';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::QUEUE;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $driver = (string) $this->config->get('queue.default');

        $facts = [$this->driverFact($driver)];

        $connectivityFact = $this->connectivityFact($driver);
        if ($connectivityFact !== null) {
            $facts[] = $connectivityFact;
        }

        $facts[] = $this->failedJobsFact();

        return $this->aggregate($facts);
    }

    /**
     * @param string $driver
     * @return array
     */
    private function driverFact($driver)
    {
        if ($driver === 'sync') {
            return [
                'status' => CheckStatus::WARNING,
                'message' => 'Queue driver is synchronous. Jobs execute during the request lifecycle.',
                'recommendation' => 'Use a real queue driver (database, redis, beanstalkd, sqs) in production.',
            ];
        }

        if ($driver === 'null' || empty($driver)) {
            return [
                'status' => CheckStatus::WARNING,
                'message' => "Queue driver is '{$driver}' - dispatched jobs are discarded.",
                'recommendation' => 'Configure a real queue driver.',
            ];
        }

        return ['status' => CheckStatus::PASS, 'message' => "Queue driver: {$driver}"];
    }

    /**
     * @param string $driver
     * @return array|null null when the driver has no connectivity to check (sync/null)
     */
    private function connectivityFact($driver)
    {
        switch ($driver) {
            case 'database':
                return $this->databaseConnectivityFact();
            case 'redis':
                return $this->redisConnectivityFact();
            case 'beanstalkd':
                return $this->beanstalkdConnectivityFact();
            case 'sqs':
                return $this->sqsConfigurationFact();
            default:
                return null;
        }
    }

    /**
     * @return array
     */
    private function databaseConnectivityFact()
    {
        $table = $this->config->get('queue.connections.database.table', 'jobs');

        try {
            $this->container->make('db')->connection()->table($table)->count();

            return ['status' => CheckStatus::PASS, 'message' => "Queue table '{$table}' is reachable."];
        } catch (Throwable $e) {
            return ['status' => CheckStatus::CRITICAL, 'message' => "Queue table '{$table}' could not be reached."];
        }
    }

    /**
     * @return array
     */
    private function redisConnectivityFact()
    {
        $connectionName = $this->config->get('queue.connections.redis.connection', 'default');

        try {
            $this->container->make('redis')->connection($connectionName)->ping();

            return ['status' => CheckStatus::PASS, 'message' => "Redis queue connection '{$connectionName}' is reachable."];
        } catch (Throwable $e) {
            return ['status' => CheckStatus::CRITICAL, 'message' => "Redis queue connection '{$connectionName}' could not be reached."];
        }
    }

    /**
     * @return array
     */
    private function beanstalkdConnectivityFact()
    {
        $host = $this->config->get('queue.connections.beanstalkd.host');
        $port = (int) $this->config->get('queue.connections.beanstalkd.port', 11300);
        $timeout = (float) $this->config->get('production-guard.timeouts.beanstalkd', 3);

        if (empty($host)) {
            return ['status' => CheckStatus::WARNING, 'message' => 'Beanstalkd queue connection has no host configured.'];
        }

        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if ($socket === false) {
            return ['status' => CheckStatus::CRITICAL, 'message' => "Beanstalkd queue connection ({$host}:{$port}) could not be reached."];
        }

        fclose($socket);

        return ['status' => CheckStatus::PASS, 'message' => "Beanstalkd queue connection ({$host}:{$port}) is reachable."];
    }

    /**
     * SQS needs AWS credentials and a live API call to truly verify
     * connectivity - too heavy for a lightweight generic check, so only
     * configuration presence is verified, per the package's own scope.
     *
     * @return array
     */
    private function sqsConfigurationFact()
    {
        $required = ['key', 'secret', 'queue', 'region'];
        $missing = [];

        foreach ($required as $key) {
            if (empty($this->config->get("queue.connections.sqs.{$key}"))) {
                $missing[] = $key;
            }
        }

        if (empty($missing)) {
            return ['status' => CheckStatus::PASS, 'message' => 'SQS queue configuration is present.'];
        }

        return [
            'status' => CheckStatus::WARNING,
            'message' => 'SQS queue configuration is incomplete: missing ' . implode(', ', $missing) . '.',
        ];
    }

    /**
     * @return array
     */
    private function failedJobsFact()
    {
        $table = $this->config->get('queue.failed.table', 'failed_jobs');

        try {
            $count = $this->container->make('db')->connection()->table($table)->count();

            if ($count > 0) {
                return [
                    'status' => CheckStatus::WARNING,
                    'message' => "{$count} failed job(s) detected.",
                    'recommendation' => 'php artisan queue:failed',
                ];
            }

            return ['status' => CheckStatus::PASS, 'message' => 'Failed jobs: 0'];
        } catch (Throwable $e) {
            return ['status' => CheckStatus::NOT_SUPPORTED, 'message' => "Failed jobs table '{$table}' could not be read."];
        }
    }
}
