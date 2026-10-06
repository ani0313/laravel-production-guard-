<?php

namespace Anivesh\LaravelProductionGuard;

use Anivesh\LaravelProductionGuard\Checkers\ApplicationCheck;
use Anivesh\LaravelProductionGuard\Checkers\BeanstalkdCheck;
use Anivesh\LaravelProductionGuard\Checkers\CacheCheck;
use Anivesh\LaravelProductionGuard\Checkers\ComposerCheck;
use Anivesh\LaravelProductionGuard\Checkers\DatabaseCheck;
use Anivesh\LaravelProductionGuard\Checkers\EnvironmentCheck;
use Anivesh\LaravelProductionGuard\Checkers\FilesystemCheck;
use Anivesh\LaravelProductionGuard\Checkers\HorizonCheck;
use Anivesh\LaravelProductionGuard\Checkers\LoggingCheck;
use Anivesh\LaravelProductionGuard\Checkers\MailCheck;
use Anivesh\LaravelProductionGuard\Checkers\MigrationCheck;
use Anivesh\LaravelProductionGuard\Checkers\NginxCheck;
use Anivesh\LaravelProductionGuard\Checkers\OpcacheCheck;
use Anivesh\LaravelProductionGuard\Checkers\PhpCheck;
use Anivesh\LaravelProductionGuard\Checkers\PhpFpmCheck;
use Anivesh\LaravelProductionGuard\Checkers\QueueCheck;
use Anivesh\LaravelProductionGuard\Checkers\QueueWorkerCheck;
use Anivesh\LaravelProductionGuard\Checkers\RedisCheck;
use Anivesh\LaravelProductionGuard\Checkers\SchedulerCheck;
use Anivesh\LaravelProductionGuard\Checkers\SecurityCheck;
use Anivesh\LaravelProductionGuard\Checkers\ServerCheck;
use Anivesh\LaravelProductionGuard\Checkers\SessionCheck;
use Anivesh\LaravelProductionGuard\Checkers\StorageCheck;
use Anivesh\LaravelProductionGuard\Checkers\SupervisorCheck;
use Anivesh\LaravelProductionGuard\Commands\ProductionCheckCommand;
use Anivesh\LaravelProductionGuard\Contracts\SystemMetrics;
use Anivesh\LaravelProductionGuard\Services\CheckRegistry;
use Anivesh\LaravelProductionGuard\Services\ProcessService;
use Anivesh\LaravelProductionGuard\Services\ProductionGuard;
use Anivesh\LaravelProductionGuard\Services\ScoreCalculator;
use Anivesh\LaravelProductionGuard\Services\SystemMetricsService;
use Illuminate\Support\ServiceProvider;

class LaravelProductionGuardServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/production-guard.php', 'production-guard');

        $this->app->singleton(CheckRegistry::class, function ($app) {
            $registry = new CheckRegistry($app);
            $registry->registerMany($this->defaultChecks($app['config']->get('production-guard.checks', [])));

            return $registry;
        });

        $this->app->singleton(ScoreCalculator::class, function ($app) {
            return new ScoreCalculator((array) $app['config']->get('production-guard.weights', []));
        });

        $this->app->singleton(ProcessService::class);
        $this->app->singleton(SystemMetrics::class, SystemMetricsService::class);

        $this->app->singleton(ProductionGuard::class, function ($app) {
            return new ProductionGuard($app->make(CheckRegistry::class), $app->make(ScoreCalculator::class));
        });

        $this->app->alias(ProductionGuard::class, 'production-guard');
    }

    /**
     * @return void
     */
    public function boot()
    {
        $this->publishes([
            __DIR__ . '/../config/production-guard.php' => $this->configPath('production-guard.php'),
        ], 'production-guard-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                ProductionCheckCommand::class,
            ]);
        }
    }

    /**
     * Checkers registered out of the box, keyed by the config('production-guard.checks')
     * category toggle that gates them. A category set to false in config is
     * skipped entirely. Populated incrementally as each checker is
     * implemented (Phase 2+ - more will be added here).
     *
     * @param array $enabled config('production-guard.checks')
     * @return array
     */
    protected function defaultChecks(array $enabled)
    {
        $checks = [
            'environment' => EnvironmentCheck::class,
            'application' => ApplicationCheck::class,
            'php' => PhpCheck::class,
            'database' => DatabaseCheck::class,
            'cache' => CacheCheck::class,
            'storage' => StorageCheck::class,
            'queue' => QueueCheck::class,
            'queue_worker' => QueueWorkerCheck::class,
            'redis' => RedisCheck::class,
            'beanstalkd' => BeanstalkdCheck::class,
            'horizon' => HorizonCheck::class,
            'supervisor' => SupervisorCheck::class,
            'scheduler' => SchedulerCheck::class,
            'server' => ServerCheck::class,
            'opcache' => OpcacheCheck::class,
            'php_fpm' => PhpFpmCheck::class,
            'nginx' => NginxCheck::class,
            'security' => SecurityCheck::class,
            'composer' => ComposerCheck::class,
            'migrations' => MigrationCheck::class,
            'logging' => LoggingCheck::class,
            'mail' => MailCheck::class,
            'session' => SessionCheck::class,
            'filesystem' => FilesystemCheck::class,
        ];

        return array_values(array_filter($checks, function ($category) use ($enabled) {
            return !isset($enabled[$category]) || $enabled[$category];
        }, ARRAY_FILTER_USE_KEY));
    }

    /**
     * config_path() doesn't exist on every supported Laravel version in the
     * same form, so resolve it through the container instead of the helper.
     *
     * @param string $path
     * @return string
     */
    private function configPath($path)
    {
        return $this->app->basePath('config' . DIRECTORY_SEPARATOR . $path);
    }
}
