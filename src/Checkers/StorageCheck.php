<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use Throwable;

class StorageCheck extends AbstractCheck
{
    /** @var Application */
    private $app;

    /** @var Config */
    private $config;

    /**
     * @param Application $app
     * @param Config $config
     */
    public function __construct(Application $app, Config $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Storage';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::STORAGE;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $facts = [];
        $facts[] = $this->writableFact(storage_path(), 'storage');
        $facts[] = $this->writableFact($this->app->bootstrapPath('cache'), 'bootstrap/cache');
        $facts[] = $this->temporaryFileFact();
        $facts[] = $this->diskUsageFact();

        return $this->aggregate($facts);
    }

    /**
     * @param string $path
     * @param string $label
     * @return array
     */
    private function writableFact($path, $label)
    {
        if (is_writable($path)) {
            return ['status' => CheckStatus::PASS, 'message' => "{$label} is writable."];
        }

        return [
            'status' => CheckStatus::CRITICAL,
            'message' => "{$label} is not writable ({$path}).",
            'recommendation' => "Ensure the web server user can write to {$path}.",
        ];
    }

    /**
     * @return array
     */
    private function temporaryFileFact()
    {
        $path = storage_path('framework/production_guard_test_' . bin2hex(random_bytes(8)) . '.tmp');

        try {
            file_put_contents($path, 'ok');
            $readBack = file_get_contents($path);
            unlink($path);

            if ($readBack !== 'ok') {
                return ['status' => CheckStatus::WARNING, 'message' => 'A temporary file was written but read back unexpected content.'];
            }

            return ['status' => CheckStatus::PASS, 'message' => 'Temporary file create/read/delete succeeded in storage/framework.'];
        } catch (Throwable $e) {
            return ['status' => CheckStatus::CRITICAL, 'message' => 'Could not create/delete a temporary file in storage/framework.'];
        }
    }

    /**
     * @return array
     */
    private function diskUsageFact()
    {
        $path = storage_path();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);

        if ($total === false || $free === false || $total <= 0) {
            return ['status' => CheckStatus::NOT_SUPPORTED, 'message' => 'Disk usage could not be determined on this system.'];
        }

        $usedPercent = (($total - $free) / $total) * 100;
        $warning = (float) $this->config->get('production-guard.thresholds.disk_warning', 80);
        $critical = (float) $this->config->get('production-guard.thresholds.disk_critical', 90);

        $message = sprintf('Disk usage: %d%% (%s free of %s)', $usedPercent, $this->formatBytes($free), $this->formatBytes($total));

        if ($usedPercent >= $critical) {
            return ['status' => CheckStatus::CRITICAL, 'message' => $message, 'recommendation' => 'Free additional disk space.'];
        }

        if ($usedPercent >= $warning) {
            return ['status' => CheckStatus::WARNING, 'message' => $message, 'recommendation' => 'Free additional disk space.'];
        }

        return ['status' => CheckStatus::PASS, 'message' => $message];
    }
}
