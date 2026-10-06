<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Foundation\Application;

class ApplicationCheck extends AbstractCheck
{
    /** @var Application */
    private $app;

    /**
     * @param Application $app
     */
    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Laravel Application';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::APPLICATION;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $facts = [];
        $facts[] = ['status' => CheckStatus::PASS, 'message' => 'Laravel ' . $this->app->version()];
        $facts[] = $this->maintenanceModeFact();
        $facts[] = $this->fileExistsFact(
            $this->bootstrapCachePath('config.php'),
            'Configuration is cached.',
            'Configuration is not cached.',
            'php artisan config:cache'
        );
        $facts[] = $this->anyFileExistsFact(
            [$this->bootstrapCachePath('routes.php'), $this->bootstrapCachePath('routes-v7.php')],
            'Routes are cached.',
            'Routes are not cached.',
            'php artisan route:cache'
        );
        $facts[] = $this->viewCacheFact();
        $facts[] = $this->eventCacheFact();
        $facts[] = $this->coreDirectoriesFact();

        return $this->aggregate($facts);
    }

    /**
     * @return array
     */
    private function maintenanceModeFact()
    {
        if ($this->app->isDownForMaintenance()) {
            return [
                'status' => CheckStatus::CRITICAL,
                'message' => 'Application is in maintenance mode.',
                'recommendation' => 'Run php artisan up once the deployment is ready to serve traffic.',
            ];
        }

        return ['status' => CheckStatus::PASS, 'message' => 'Application is not in maintenance mode.'];
    }

    /**
     * @param string $path
     * @param string $passMessage
     * @param string $warningMessage
     * @param string $recommendation
     * @return array
     */
    private function fileExistsFact($path, $passMessage, $warningMessage, $recommendation)
    {
        return $this->anyFileExistsFact([$path], $passMessage, $warningMessage, $recommendation);
    }

    /**
     * PASS if any of the given candidate paths exists - different Laravel
     * versions use different cache file names for the same feature (e.g.
     * routes.php vs routes-v7.php).
     *
     * @param string[] $paths
     * @param string $passMessage
     * @param string $warningMessage
     * @param string $recommendation
     * @return array
     */
    private function anyFileExistsFact(array $paths, $passMessage, $warningMessage, $recommendation)
    {
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return ['status' => CheckStatus::PASS, 'message' => $passMessage];
            }
        }

        return ['status' => CheckStatus::WARNING, 'message' => $warningMessage, 'recommendation' => $recommendation];
    }

    /**
     * There is no single on/off flag for view caching - Laravel compiles
     * views lazily into storage/framework/views. A non-empty directory is
     * an honest (if imperfect) signal that views have been warmed; it is
     * reported as exactly that, not as a guaranteed "all views cached".
     *
     * @return array
     */
    private function viewCacheFact()
    {
        $viewCachePath = storage_path('framework/views');

        if (!is_dir($viewCachePath)) {
            return [
                'status' => CheckStatus::UNKNOWN,
                'message' => 'storage/framework/views does not exist; view cache status cannot be determined.',
            ];
        }

        $compiled = glob($viewCachePath . '/*.php');

        if (!empty($compiled)) {
            return ['status' => CheckStatus::PASS, 'message' => 'storage/framework/views contains compiled view templates.'];
        }

        return [
            'status' => CheckStatus::WARNING,
            'message' => 'No compiled templates found in storage/framework/views.',
            'recommendation' => 'php artisan view:cache',
        ];
    }

    /**
     * event:cache/event:clear were only introduced once Laravel added event
     * auto-discovery (9.x) - report NOT_SUPPORTED on anything older rather
     * than guessing.
     *
     * @return array
     */
    private function eventCacheFact()
    {
        if (version_compare($this->app->version(), '9.0.0', '<')) {
            return ['status' => CheckStatus::NOT_SUPPORTED, 'message' => 'Event cache is not available before Laravel 9.'];
        }

        return $this->fileExistsFact(
            $this->bootstrapCachePath('events.php'),
            'Events are cached.',
            'Events are not cached.',
            'php artisan event:cache'
        );
    }

    /**
     * @return array
     */
    private function coreDirectoriesFact()
    {
        $missing = [];

        foreach ([storage_path(), $this->app->bootstrapPath('cache')] as $path) {
            if (!is_dir($path)) {
                $missing[] = $path;
            }
        }

        if (empty($missing)) {
            return ['status' => CheckStatus::PASS, 'message' => 'Core framework directories exist.'];
        }

        return [
            'status' => CheckStatus::CRITICAL,
            'message' => 'Missing core framework directories: ' . implode(', ', $missing),
        ];
    }

    /**
     * @param string $filename
     * @return string
     */
    private function bootstrapCachePath($filename)
    {
        return $this->app->bootstrapPath('cache/' . $filename);
    }
}
