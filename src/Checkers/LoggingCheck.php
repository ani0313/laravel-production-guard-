<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Throwable;

class LoggingCheck extends AbstractCheck
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
        return 'Logging';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::LOGGING;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $facts = [];
        $facts[] = $this->channelFact();
        $facts[] = $this->logDirectoryFact();

        $testWriteFact = $this->testWriteFact();
        if ($testWriteFact !== null) {
            $facts[] = $testWriteFact;
        }

        return $this->aggregate($facts);
    }

    /**
     * @return array
     */
    private function channelFact()
    {
        $channel = $this->config->get('logging.default') ?: $this->config->get('log.default');

        if (empty($channel)) {
            return ['status' => CheckStatus::WARNING, 'message' => 'No default log channel is configured.'];
        }

        return ['status' => CheckStatus::PASS, 'message' => "Log channel: {$channel}"];
    }

    /**
     * @return array
     */
    private function logDirectoryFact()
    {
        $path = storage_path('logs');

        if (!is_dir($path) || !is_writable($path)) {
            return [
                'status' => CheckStatus::CRITICAL,
                'message' => "storage/logs is not writable ({$path}).",
                'recommendation' => 'Ensure the web server user can write to storage/logs.',
            ];
        }

        return ['status' => CheckStatus::PASS, 'message' => 'storage/logs is writable.'];
    }

    /**
     * Disabled by default - the package never writes a log entry of its
     * own unless explicitly opted into.
     *
     * @return array|null
     */
    private function testWriteFact()
    {
        if (!$this->config->get('production-guard.logging.test_write_enabled', false)) {
            return null;
        }

        try {
            $this->container->make('log')->info('production_guard_test_' . bin2hex(random_bytes(8)));

            return ['status' => CheckStatus::PASS, 'message' => 'Test log write succeeded.'];
        } catch (Throwable $e) {
            return ['status' => CheckStatus::WARNING, 'message' => 'Test log write failed.'];
        }
    }
}
