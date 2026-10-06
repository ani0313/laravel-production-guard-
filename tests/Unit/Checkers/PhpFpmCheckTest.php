<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\PhpFpmCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Services\ProcessService;
use Mockery;
use PHPUnit\Framework\TestCase;

class PhpFpmCheckTest extends TestCase
{
    protected function tearDown(): void
    {
        Mockery::close();
    }

    public function test_unknown_when_ps_is_unavailable()
    {
        $process = Mockery::mock(ProcessService::class);
        $process->shouldReceive('run')->with('ps', ['aux'])->andReturn(['ok' => false, 'exitCode' => null, 'output' => '', 'error' => '']);
        $process->shouldReceive('run')->with('php-fpm', ['-v'])->andReturn(['ok' => false, 'exitCode' => null, 'output' => '', 'error' => '']);

        $result = (new PhpFpmCheck($process))->check();

        $this->assertSame(CheckStatus::UNKNOWN, $result->getStatus());
    }

    public function test_pass_when_a_php_fpm_process_is_detected_with_version()
    {
        $process = Mockery::mock(ProcessService::class);
        $process->shouldReceive('run')->with('ps', ['aux'])->andReturn([
            'ok' => true, 'exitCode' => 0, 'error' => '',
            'output' => "www-data  100  php-fpm: master process\nwww-data  101  php-fpm: pool www\n",
        ]);
        $process->shouldReceive('run')->with('php-fpm', ['-v'])->andReturn([
            'ok' => true, 'exitCode' => 0, 'error' => '',
            'output' => "PHP 8.2.25 (fpm-fcgi) (built: Jan  1 2026)\nCopyright (c)\n",
        ]);

        $result = (new PhpFpmCheck($process))->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
        $this->assertSame('Detected 2 php-fpm process(es).', $result->getDetails()[0]['message']);
        $this->assertSame('PHP 8.2.25 (fpm-fcgi) (built: Jan  1 2026)', $result->getDetails()[1]['message']);
    }
}
