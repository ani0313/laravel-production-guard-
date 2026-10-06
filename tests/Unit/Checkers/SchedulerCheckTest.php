<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\SchedulerCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Tests\TestCase;
use Illuminate\Console\Scheduling\Schedule;

class SchedulerCheckTest extends TestCase
{
    public function test_no_scheduled_tasks_is_a_warning()
    {
        $result = $this->app->make(SchedulerCheck::class)->check();

        $this->assertSame(CheckStatus::WARNING, $result->getDetails()[0]['status']);
        $this->assertSame('No scheduled tasks are registered.', $result->getDetails()[0]['message']);
    }

    public function test_registered_tasks_pass_and_are_counted()
    {
        $this->app->make(Schedule::class)->command('inspire')->daily();
        $this->app->make(Schedule::class)->command('inspire')->hourly();

        $result = $this->app->make(SchedulerCheck::class)->check();

        $this->assertSame(CheckStatus::PASS, $result->getDetails()[0]['status']);
        $this->assertSame('2 scheduled task(s) registered.', $result->getDetails()[0]['message']);
    }

    public function test_execution_status_is_always_unknown_with_a_concrete_recommendation()
    {
        $result = $this->app->make(SchedulerCheck::class)->check();

        $executionFact = $result->getDetails()[1];
        $this->assertSame(CheckStatus::UNKNOWN, $executionFact['status']);
        $this->assertSame("Ensure the Laravel scheduler runs every minute: '* * * * * php artisan schedule:run'.", $executionFact['recommendation']);
    }

    public function test_overall_status_is_never_a_false_pass_because_execution_is_always_unknown()
    {
        $this->app->make(Schedule::class)->command('inspire')->daily();

        $result = $this->app->make(SchedulerCheck::class)->check();

        $this->assertSame(CheckStatus::UNKNOWN, $result->getStatus());
    }
}
