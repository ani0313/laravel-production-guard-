<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Services\ProcessService;

class SupervisorCheck extends AbstractCheck
{
    /** @var ProcessService */
    private $process;

    /**
     * @param ProcessService $process
     */
    public function __construct(ProcessService $process)
    {
        $this->process = $process;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Supervisor';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::SUPERVISOR;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $result = $this->process->run('supervisorctl', ['status']);

        if (!$result['ok']) {
            return $this->aggregate([
                ['status' => CheckStatus::UNKNOWN, 'message' => 'Supervisor status could not be determined (supervisorctl not available or not permitted).'],
            ]);
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $result['output']))));

        return $this->aggregate([
            ['status' => CheckStatus::PASS, 'message' => sprintf('Supervisor is running (%d program(s) tracked).', count($lines))],
        ]);
    }
}
