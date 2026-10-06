<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Services\ProcessService;
use Illuminate\Contracts\Config\Repository as Config;

class QueueWorkerCheck extends AbstractCheck
{
    /** @var ProcessService */
    private $process;

    /** @var Config */
    private $config;

    /**
     * @param ProcessService $process
     * @param Config $config
     */
    public function __construct(ProcessService $process, Config $config)
    {
        $this->process = $process;
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Queue Worker';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::QUEUE_WORKER;
    }

    /**
     * Deliberately never claims a worker IS running just because the
     * queue connection is healthy (that's QueueCheck's job) - this reports
     * UNKNOWN rather than a false PASS whenever a worker process can't
     * actually be observed.
     *
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $driver = (string) $this->config->get('queue.default');

        if (in_array($driver, ['sync', 'null', ''], true)) {
            return $this->aggregate([
                ['status' => CheckStatus::NOT_APPLICABLE, 'message' => "No queue worker is needed for the '{$driver}' driver."],
            ]);
        }

        $viaProcessList = $this->detectViaProcessList();
        if ($viaProcessList !== null) {
            return $this->aggregate([$viaProcessList]);
        }

        $viaSupervisor = $this->detectViaSupervisor();
        if ($viaSupervisor !== null) {
            return $this->aggregate([$viaSupervisor]);
        }

        return $this->aggregate([
            ['status' => CheckStatus::UNKNOWN, 'message' => 'Queue worker status could not be verified.'],
        ]);
    }

    /**
     * @return array|null
     */
    private function detectViaProcessList()
    {
        $result = $this->process->run('ps', ['aux']);

        if (!$result['ok']) {
            return null;
        }

        $matches = 0;
        foreach (explode("\n", $result['output']) as $line) {
            if (strpos($line, 'queue:work') !== false || strpos($line, 'artisan horizon') !== false) {
                $matches++;
            }
        }

        if ($matches > 0) {
            return ['status' => CheckStatus::PASS, 'message' => sprintf('Detected %d running queue worker process(es).', $matches)];
        }

        return null;
    }

    /**
     * @return array|null
     */
    private function detectViaSupervisor()
    {
        $result = $this->process->run('supervisorctl', ['status']);

        if (!$result['ok']) {
            return null;
        }

        foreach (explode("\n", $result['output']) as $line) {
            $line = trim($line);
            $isQueueProgram = stripos($line, 'queue') !== false || stripos($line, 'horizon') !== false;

            if ($isQueueProgram && stripos($line, 'RUNNING') !== false) {
                return ['status' => CheckStatus::PASS, 'message' => 'Supervisor reports a queue worker program as RUNNING.'];
            }
        }

        return null;
    }
}
