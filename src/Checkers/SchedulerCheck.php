<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Console\Scheduling\Schedule;
use Throwable;

class SchedulerCheck extends AbstractCheck
{
    /** @var Schedule */
    private $schedule;

    /**
     * Resolving Schedule::class triggers the app's own Kernel::schedule()
     * registration (via the container singleton Laravel's Foundation
     * Kernel binds once the app is booted) - this only reads the
     * resulting event list, it never calls Event::run().
     *
     * @param Schedule $schedule
     */
    public function __construct(Schedule $schedule)
    {
        $this->schedule = $schedule;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Scheduler';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::SCHEDULER;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        return $this->aggregate([
            $this->configuredTasksFact(),
            $this->executionStatusFact(),
        ]);
    }

    /**
     * @return array
     */
    private function configuredTasksFact()
    {
        try {
            $count = count($this->schedule->events());
        } catch (Throwable $e) {
            return ['status' => CheckStatus::UNKNOWN, 'message' => 'Scheduled tasks could not be enumerated.'];
        }

        if ($count === 0) {
            return ['status' => CheckStatus::WARNING, 'message' => 'No scheduled tasks are registered.'];
        }

        return ['status' => CheckStatus::PASS, 'message' => "{$count} scheduled task(s) registered."];
    }

    /**
     * Deliberately always UNKNOWN: whether the scheduler is actually
     * executing every minute depends on the system crontab of whichever
     * user runs it, which this package has no safe, generic way to read
     * (and reading the current process's own crontab would be misleading,
     * since it may differ from the deployment's cron user).
     *
     * @return array
     */
    private function executionStatusFact()
    {
        return [
            'status' => CheckStatus::UNKNOWN,
            'message' => 'Scheduler execution status cannot be verified.',
            'recommendation' => "Ensure the Laravel scheduler runs every minute: '* * * * * php artisan schedule:run'.",
        ];
    }
}
