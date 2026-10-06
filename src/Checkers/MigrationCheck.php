<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Container\Container;
use Throwable;

class MigrationCheck extends AbstractCheck
{
    /** @var Container */
    private $container;

    /**
     * Laravel binds the migration repository to the string key
     * 'migration.repository', not to its interface, so it's resolved from
     * the container lazily rather than type-hinted directly.
     *
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Migrations';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::MIGRATIONS;
    }

    /**
     * Read-only: compares migration files on disk against the migrations
     * table. Never runs a migration, never modifies the schema.
     *
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        return $this->aggregate([$this->migrationsFact()]);
    }

    /**
     * @return array
     */
    private function migrationsFact()
    {
        try {
            $migrationRepository = $this->container->make('migration.repository');

            if (!$migrationRepository->repositoryExists()) {
                return ['status' => CheckStatus::UNKNOWN, 'message' => 'The migrations table does not exist; migration status cannot be determined.'];
            }

            $ran = $migrationRepository->getRan();
            $files = glob(database_path('migrations/*.php'));
            $names = array_map(function ($file) {
                return basename($file, '.php');
            }, $files ?: []);

            $pending = array_values(array_diff($names, $ran));

            if (empty($pending)) {
                return ['status' => CheckStatus::PASS, 'message' => 'Migrations are up to date.'];
            }

            return [
                'status' => CheckStatus::WARNING,
                'message' => sprintf('%d pending migration(s) detected.', count($pending)),
                'recommendation' => 'php artisan migrate --force',
            ];
        } catch (Throwable $e) {
            return ['status' => CheckStatus::UNKNOWN, 'message' => 'Migration status could not be determined.'];
        }
    }
}
