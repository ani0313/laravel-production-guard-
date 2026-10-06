<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Config\Repository as Config;

class BeanstalkdCheck extends AbstractCheck
{
    /** @var Config */
    private $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Beanstalkd';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::BEANSTALKD;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        if ((string) $this->config->get('queue.default') !== 'beanstalkd') {
            return $this->aggregate([
                ['status' => CheckStatus::NOT_APPLICABLE, 'message' => 'Beanstalkd is not the configured queue driver.'],
            ]);
        }

        $host = $this->config->get('queue.connections.beanstalkd.host');
        $port = (int) $this->config->get('queue.connections.beanstalkd.port', 11300);
        $timeout = (float) $this->config->get('production-guard.timeouts.beanstalkd', 3);

        if (empty($host)) {
            return $this->aggregate([
                ['status' => CheckStatus::WARNING, 'message' => 'Beanstalkd has no host configured.'],
            ]);
        }

        return $this->aggregate([$this->connectivityFact($host, $port, $timeout)]);
    }

    /**
     * Connects at the TCP level only - no telnet/nc/socat, no pda/pheanstalk
     * dependency required.
     *
     * @param string $host
     * @param int $port
     * @param float $timeout
     * @return array
     */
    private function connectivityFact($host, $port, $timeout)
    {
        $start = microtime(true);
        $socket = @fsockopen($host, $port, $errno, $errstr, $timeout);

        if ($socket === false) {
            return ['status' => CheckStatus::CRITICAL, 'message' => "Beanstalkd ({$host}:{$port}) could not be reached: {$errstr}"];
        }

        $latencyMs = (microtime(true) - $start) * 1000;
        fclose($socket);

        return ['status' => CheckStatus::PASS, 'message' => sprintf('Beanstalkd (%s:%d) is reachable, latency %dms.', $host, $port, $latencyMs)];
    }
}
