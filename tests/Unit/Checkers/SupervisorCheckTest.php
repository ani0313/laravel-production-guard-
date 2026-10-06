<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\SupervisorCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Services\ProcessService;
use Mockery;
use PHPUnit\Framework\TestCase;

class SupervisorCheckTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_unknown_when_supervisorctl_is_unavailable()
    {
        $process = Mockery::mock(ProcessService::class);
        $process->shouldReceive('run')->with('supervisorctl', ['status'])
            ->andReturn(['ok' => false, 'exitCode' => null, 'output' => '', 'error' => 'not found']);

        $result = (new SupervisorCheck($process))->check();

        $this->assertSame(CheckStatus::UNKNOWN, $result->getStatus());
    }

    public function test_pass_reports_the_number_of_tracked_programs()
    {
        $process = Mockery::mock(ProcessService::class);
        $process->shouldReceive('run')->with('supervisorctl', ['status'])->andReturn([
            'ok' => true,
            'exitCode' => 0,
            'output' => "queue-worker:queue-worker_00   RUNNING   pid 123, uptime 1:00:00\nhorizon   RUNNING   pid 456, uptime 1:00:00\n",
            'error' => '',
        ]);

        $result = (new SupervisorCheck($process))->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
        $this->assertSame('Supervisor is running (2 program(s) tracked).', $result->getDetails()[0]['message']);
    }
}
