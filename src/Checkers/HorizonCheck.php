<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Container\Container;
use Throwable;

class HorizonCheck extends AbstractCheck
{
    /** @var Container */
    private $container;

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Horizon';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::HORIZON;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        if (!class_exists('Laravel\\Horizon\\Horizon')) {
            return $this->aggregate([
                ['status' => CheckStatus::NOT_APPLICABLE, 'message' => 'Laravel Horizon is not installed.'],
            ]);
        }

        $facts = [['status' => CheckStatus::PASS, 'message' => 'Laravel Horizon is installed.']];
        $facts[] = $this->pausedFact();
        $facts[] = $this->masterSupervisorFact();

        return $this->aggregate($facts);
    }

    /**
     * @return array
     */
    private function pausedFact()
    {
        try {
            $paused = call_user_func(['Laravel\\Horizon\\Horizon', 'isPaused']);

            if ($paused) {
                return [
                    'status' => CheckStatus::WARNING,
                    'message' => 'Horizon is paused.',
                    'recommendation' => 'php artisan horizon:continue',
                ];
            }

            return ['status' => CheckStatus::PASS, 'message' => 'Horizon is not paused.'];
        } catch (Throwable $e) {
            return ['status' => CheckStatus::UNKNOWN, 'message' => "Horizon's paused state could not be determined."];
        }
    }

    /**
     * Distinguishes "Horizon is installed" from "a Horizon master
     * supervisor process is actually running" - the latter requires a live
     * master supervisor record in Redis, which only exists while a real
     * `php artisan horizon` process is running.
     *
     * @return array
     */
    private function masterSupervisorFact()
    {
        try {
            $repository = $this->container->make('Laravel\\Horizon\\Contracts\\MasterSupervisorRepository');
            $names = $repository->names();

            if (!empty($names)) {
                return ['status' => CheckStatus::PASS, 'message' => sprintf('%d Horizon master supervisor(s) detected.', count($names))];
            }

            return [
                'status' => CheckStatus::WARNING,
                'message' => 'No active Horizon master supervisor detected - Horizon may not be running.',
                'recommendation' => 'php artisan horizon',
            ];
        } catch (Throwable $e) {
            return ['status' => CheckStatus::UNKNOWN, 'message' => 'Horizon worker/process state could not be determined.'];
        }
    }
}
