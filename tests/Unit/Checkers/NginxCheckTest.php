<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\NginxCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Services\ProcessService;
use Mockery;
use PHPUnit\Framework\TestCase;

class NginxCheckTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_unknown_when_no_nginx_process_is_detected()
    {
        $process = Mockery::mock(ProcessService::class);
        $process->shouldReceive('run')->with('ps', ['aux'])->andReturn([
            'ok' => true, 'exitCode' => 0, 'error' => '', 'output' => "forge  1  some-other-process\n",
        ]);
        $process->shouldReceive('run')->with('nginx', ['-v'])->andReturn(['ok' => false, 'exitCode' => null, 'output' => '', 'error' => '']);

        $result = (new NginxCheck($process))->check();

        $this->assertSame(CheckStatus::UNKNOWN, $result->getStatus());
    }

    public function test_pass_when_nginx_is_detected_with_version_from_stderr()
    {
        $process = Mockery::mock(ProcessService::class);
        $process->shouldReceive('run')->with('ps', ['aux'])->andReturn([
            'ok' => true, 'exitCode' => 0, 'error' => '',
            'output' => "root  1  nginx: master process\nwww-data  2  nginx: worker process\n",
        ]);
        $process->shouldReceive('run')->with('nginx', ['-v'])->andReturn([
            'ok' => true, 'exitCode' => 0, 'output' => '',
            'error' => "nginx version: nginx/1.24.0\n",
        ]);

        $result = (new NginxCheck($process))->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
        $this->assertSame('Detected 2 nginx process(es).', $result->getDetails()[0]['message']);
        $this->assertSame('nginx version: nginx/1.24.0', $result->getDetails()[1]['message']);
    }
}
