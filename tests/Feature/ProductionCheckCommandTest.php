<?php

namespace Anivesh\LaravelProductionGuard\Tests\Feature;

use Anivesh\LaravelProductionGuard\Commands\ProductionCheckCommand;
use Anivesh\LaravelProductionGuard\Services\CheckRegistry;
use Anivesh\LaravelProductionGuard\Tests\Fixtures\StubCheck;
use Anivesh\LaravelProductionGuard\Tests\Fixtures\ThrowingCheck;
use Anivesh\LaravelProductionGuard\Tests\Fixtures\UnresolvableCheck;
use Anivesh\LaravelProductionGuard\Tests\TestCase;
use Illuminate\Support\Facades\Artisan;

class ProductionCheckCommandTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        StubCheck::$status = 'passed';
        StubCheck::$score = 10;
        StubCheck::$maxScore = 10;
        StubCheck::$category = 'environment';

        // These tests exercise command-level mechanics (exit codes, JSON
        // shape, filtering, custom registration), not checker content, so
        // the real default checkers (Phase 2+) are disabled here to keep
        // the fixtures fully isolated and not grow brittle as more
        // checkers are added in later phases. Checker content itself is
        // covered by tests/Unit/Checkers/*.
        $this->app['config']->set(
            'production-guard.checks',
            array_fill_keys(array_keys($this->app['config']->get('production-guard.checks')), false)
        );
    }

    public function test_default_run_with_no_registered_checks_exits_zero()
    {
        $exitCode = Artisan::call('production:check', []);

        $this->assertSame(ProductionCheckCommand::EXIT_PASS, $exitCode);
        $this->assertContains('Health Score: 0/100', Artisan::output());
    }

    public function test_json_option_outputs_valid_json_with_the_documented_keys()
    {
        $this->app->make(CheckRegistry::class)->register(StubCheck::class);

        Artisan::call('production:check', ['--json' => true]);

        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame('laravel-production-guard', $decoded['package']);
        $this->assertArrayHasKey('score', $decoded);
        $this->assertArrayHasKey('status', $decoded);
        $this->assertCount(1, $decoded['checks']);
    }

    public function test_only_option_filters_by_category()
    {
        $registry = $this->app->make(CheckRegistry::class);
        $registry->register(StubCheck::class);

        Artisan::call('production:check', ['--json' => true, '--only' => ['database']]);

        $decoded = json_decode(Artisan::output(), true);

        $this->assertCount(0, $decoded['checks']);
    }

    public function test_a_throwing_checker_is_reported_as_unknown_instead_of_crashing_the_command()
    {
        $this->app->make(CheckRegistry::class)->register(ThrowingCheck::class);

        $exitCode = Artisan::call('production:check', ['--json' => true]);

        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame(ProductionCheckCommand::EXIT_PASS, $exitCode);
        $this->assertSame('unknown', $decoded['checks'][0]['status']);
    }

    public function test_fail_on_warning_escalates_the_exit_code_only_when_passed()
    {
        StubCheck::$status = 'warning';
        StubCheck::$score = 0;
        $this->app->make(CheckRegistry::class)->register(StubCheck::class);

        $this->assertSame(ProductionCheckCommand::EXIT_PASS, Artisan::call('production:check', []));
        $this->assertSame(ProductionCheckCommand::EXIT_WARNING, Artisan::call('production:check', ['--fail-on-warning' => true]));
    }

    public function test_fail_on_critical_escalates_the_exit_code_only_when_passed()
    {
        StubCheck::$status = 'critical';
        StubCheck::$score = 0;
        $this->app->make(CheckRegistry::class)->register(StubCheck::class);

        $this->assertSame(ProductionCheckCommand::EXIT_PASS, Artisan::call('production:check', []));
        $this->assertSame(ProductionCheckCommand::EXIT_CRITICAL, Artisan::call('production:check', ['--fail-on-critical' => true]));
    }

    public function test_a_custom_check_the_container_cannot_construct_is_reported_as_unknown_instead_of_crashing_the_command()
    {
        $this->app->make(CheckRegistry::class)->register(UnresolvableCheck::class);

        $exitCode = Artisan::call('production:check', ['--json' => true]);

        $decoded = json_decode(Artisan::output(), true);

        $this->assertSame(ProductionCheckCommand::EXIT_PASS, $exitCode);
        $this->assertSame('unknown', $decoded['checks'][0]['status']);
    }

    public function test_registering_a_custom_check_through_the_facade_makes_it_run()
    {
        \Anivesh\LaravelProductionGuard\Facades\ProductionGuard::registerCheck(StubCheck::class);

        Artisan::call('production:check', ['--json' => true]);

        $decoded = json_decode(Artisan::output(), true);

        $this->assertCount(1, $decoded['checks']);
        $this->assertSame('Stub Check', $decoded['checks'][0]['name']);
    }
}
