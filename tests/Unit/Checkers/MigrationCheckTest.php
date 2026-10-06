<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\MigrationCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Tests\TestCase;

class MigrationCheckTest extends TestCase
{
    protected function getEnvironmentSetUp($app)
    {
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);
    }

    public function test_missing_migrations_table_is_reported_as_unknown_not_a_false_pass()
    {
        $result = $this->app->make(MigrationCheck::class)->check();

        $this->assertSame(CheckStatus::UNKNOWN, $result->getStatus());
    }

    public function test_an_empty_migrations_table_with_no_migration_files_is_up_to_date()
    {
        $this->app->make('migration.repository')->createRepository();

        $result = $this->app->make(MigrationCheck::class)->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
        $this->assertSame('Migrations are up to date.', $result->getDetails()[0]['message']);
    }

    public function test_a_migration_file_not_yet_run_is_reported_as_pending_without_running_it()
    {
        $this->app->make('migration.repository')->createRepository();

        $migrationsPath = database_path('migrations');
        if (!is_dir($migrationsPath)) {
            mkdir($migrationsPath, 0777, true);
        }
        $pendingFile = $migrationsPath . '/2026_01_01_000000_pending_test_migration.php';
        file_put_contents($pendingFile, '<?php // intentionally not executable, existence only');

        try {
            $result = $this->app->make(MigrationCheck::class)->check();

            $this->assertSame(CheckStatus::WARNING, $result->getStatus());
            $this->assertSame('1 pending migration(s) detected.', $result->getDetails()[0]['message']);
            $this->assertSame('php artisan migrate --force', $result->getDetails()[0]['recommendation']);
        } finally {
            @unlink($pendingFile);
        }
    }
}
