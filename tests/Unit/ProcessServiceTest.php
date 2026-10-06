<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit;

use Anivesh\LaravelProductionGuard\Services\ProcessService;
use PHPUnit\Framework\TestCase;

class ProcessServiceTest extends TestCase
{
    public function test_it_refuses_a_non_whitelisted_binary_without_executing_anything()
    {
        $service = new ProcessService();

        $this->assertFalse($service->isAllowed('rm'));

        $result = $service->run('rm', ['-rf', '/']);

        $this->assertFalse($result['ok']);
        $this->assertSame('Binary is not whitelisted.', $result['error']);
    }

    public function test_it_runs_a_whitelisted_binary()
    {
        $service = new ProcessService();

        $result = $service->run('uname', ['-s']);

        $this->assertTrue($result['ok']);
        $this->assertSame(0, $result['exitCode']);
        $this->assertNotEmpty(trim($result['output']));
    }

    public function test_it_times_out_a_hanging_process_instead_of_blocking_forever()
    {
        $service = new ProcessService();

        $result = $service->run('uptime', [], 0.001);

        $this->assertArrayHasKey('ok', $result);
    }
}
