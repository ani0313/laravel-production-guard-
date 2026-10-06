<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\QueueWorkerCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Services\ProcessService;
use Illuminate\Config\Repository;
use Mockery;
use PHPUnit\Framework\TestCase;

class QueueWorkerCheckTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    private function config($driver)
    {
        return new Repository(['queue' => ['default' => $driver]]);
    }

    public function test_not_applicable_for_the_sync_driver()
    {
        $process = Mockery::mock(ProcessService::class);

        $result = (new QueueWorkerCheck($process, $this->config('sync')))->check();

        $this->assertSame(CheckStatus::NOT_APPLICABLE, $result->getStatus());
    }

    public function test_pass_when_a_worker_process_is_found_via_ps()
    {
        $process = Mockery::mock(ProcessService::class);
        $process->shouldReceive('run')->with('ps', ['aux'])->andReturn([
            'ok' => true, 'exitCode' => 0, 'error' => '',
            'output' => "forge  1234  0.0  0.1  php artisan queue:work redis --sleep=3 --tries=3\n",
        ]);

        $result = (new QueueWorkerCheck($process, $this->config('redis')))->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
        $this->assertSame('Detected 1 running queue worker process(es).', $result->getDetails()[0]['message']);
    }

    public function test_falls_back_to_supervisor_when_ps_finds_nothing()
    {
        $process = Mockery::mock(ProcessService::class);
        $process->shouldReceive('run')->with('ps', ['aux'])->andReturn([
            'ok' => true, 'exitCode' => 0, 'error' => '', 'output' => "forge  1  nginx\n",
        ]);
        $process->shouldReceive('run')->with('supervisorctl', ['status'])->andReturn([
            'ok' => true, 'exitCode' => 0, 'error' => '',
            'output' => "queue-worker:queue-worker_00   RUNNING   pid 123\n",
        ]);

        $result = (new QueueWorkerCheck($process, $this->config('redis')))->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
        $this->assertSame('Supervisor reports a queue worker program as RUNNING.', $result->getDetails()[0]['message']);
    }

    public function test_unknown_when_neither_mechanism_confirms_a_worker()
    {
        $process = Mockery::mock(ProcessService::class);
        $process->shouldReceive('run')->with('ps', ['aux'])->andReturn([
            'ok' => true, 'exitCode' => 0, 'error' => '', 'output' => "forge  1  nginx\n",
        ]);
        $process->shouldReceive('run')->with('supervisorctl', ['status'])->andReturn([
            'ok' => false, 'exitCode' => null, 'error' => 'not found', 'output' => '',
        ]);

        $result = (new QueueWorkerCheck($process, $this->config('redis')))->check();

        $this->assertSame(CheckStatus::UNKNOWN, $result->getStatus());
        $this->assertSame('Queue worker status could not be verified.', $result->getMessage());
    }

    public function test_never_claims_running_just_because_a_supervisor_program_exists_in_a_non_running_state()
    {
        $process = Mockery::mock(ProcessService::class);
        $process->shouldReceive('run')->with('ps', ['aux'])->andReturn([
            'ok' => true, 'exitCode' => 0, 'error' => '', 'output' => "forge  1  nginx\n",
        ]);
        $process->shouldReceive('run')->with('supervisorctl', ['status'])->andReturn([
            'ok' => true, 'exitCode' => 0, 'error' => '',
            'output' => "queue-worker:queue-worker_00   FATAL   Exited too quickly\n",
        ]);

        $result = (new QueueWorkerCheck($process, $this->config('redis')))->check();

        $this->assertSame(CheckStatus::UNKNOWN, $result->getStatus());
    }
}
