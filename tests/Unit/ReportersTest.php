<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit;

use Anivesh\LaravelProductionGuard\DTO\CheckResult;
use Anivesh\LaravelProductionGuard\DTO\ProductionReport;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Reports\CliReporter;
use Anivesh\LaravelProductionGuard\Reports\JsonReporter;
use PHPUnit\Framework\TestCase;

class ReportersTest extends TestCase
{
    private function sampleReport()
    {
        return new ProductionReport('1.0.0-dev', '5.4.36', '7.1.33', 'production', 91, CheckStatus::WARNING, [
            new CheckResult('Database Connection', 'database', CheckStatus::PASS, 10, 10, 'Database connection successful'),
            new CheckResult('Worker Status', 'queue_worker', CheckStatus::WARNING, 0, 5, 'Queue worker could not be verified', [], ['Confirm a queue:work process is running under Supervisor.']),
        ]);
    }

    public function test_json_reporter_produces_the_documented_shape()
    {
        $decoded = json_decode((new JsonReporter())->render($this->sampleReport()), true);

        $this->assertSame('laravel-production-guard', $decoded['package']);
        $this->assertSame(91, $decoded['score']);
        $this->assertSame('warning', $decoded['status']);
        $this->assertCount(2, $decoded['checks']);
        $this->assertSame('database', $decoded['checks'][0]['category']);
    }

    public function test_json_reporter_never_leaks_app_key_shaped_values()
    {
        $json = (new JsonReporter())->render($this->sampleReport());

        $this->assertNotContains('APP_KEY', $json);
        $this->assertNotContains('base64:', $json);
    }

    public function test_cli_reporter_includes_score_status_and_recommendation()
    {
        $output = (new CliReporter())->render($this->sampleReport());

        $this->assertContains('Health Score: <info>91/100</info>', $output);
        $this->assertContains('Queue worker could not be verified', $output);
        $this->assertContains('Confirm a queue:work process is running under Supervisor.', $output);
    }
}
