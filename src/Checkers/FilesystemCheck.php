<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Config\Repository as Config;

class FilesystemCheck extends AbstractCheck
{
    /**
     * Cloud disk drivers only get a configuration-presence check - no
     * connection is attempted (never destructive, but still avoids needing
     * real credentials/network for a lightweight diagnostic).
     *
     * @var array driver => required config keys
     */
    const CLOUD_DRIVER_KEYS = [
        's3' => ['key', 'secret', 'bucket', 'region'],
    ];

    /** @var Config */
    private $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Filesystem';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::FILESYSTEM;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $disks = (array) $this->config->get('filesystems.disks', []);

        if (empty($disks)) {
            return $this->aggregate([
                ['status' => CheckStatus::UNKNOWN, 'message' => 'No filesystem disks are configured.'],
            ]);
        }

        $facts = [];
        foreach ($disks as $name => $diskConfig) {
            $facts[] = $this->diskFact($name, (array) $diskConfig);
        }

        return $this->aggregate($facts);
    }

    /**
     * @param string $name
     * @param array $diskConfig
     * @return array
     */
    private function diskFact($name, array $diskConfig)
    {
        $driver = isset($diskConfig['driver']) ? $diskConfig['driver'] : 'unknown';

        if ($driver === 'local') {
            return $this->localDiskFact($name, $diskConfig);
        }

        if (isset(self::CLOUD_DRIVER_KEYS[$driver])) {
            return $this->cloudDiskFact($name, $driver, $diskConfig);
        }

        return ['status' => CheckStatus::PASS, 'message' => "Disk '{$name}' uses driver '{$driver}' (not deeply checked)."];
    }

    /**
     * @param string $name
     * @param array $diskConfig
     * @return array
     */
    private function localDiskFact($name, array $diskConfig)
    {
        $root = isset($diskConfig['root']) ? $diskConfig['root'] : null;

        if (empty($root)) {
            return ['status' => CheckStatus::WARNING, 'message' => "Disk '{$name}' (local) has no root path configured."];
        }

        if (!is_dir($root) || !is_writable($root)) {
            return ['status' => CheckStatus::WARNING, 'message' => "Disk '{$name}' (local) root is not writable ({$root})."];
        }

        return ['status' => CheckStatus::PASS, 'message' => "Disk '{$name}' (local) root is writable."];
    }

    /**
     * @param string $name
     * @param string $driver
     * @param array $diskConfig
     * @return array
     */
    private function cloudDiskFact($name, $driver, array $diskConfig)
    {
        $missing = [];
        foreach (self::CLOUD_DRIVER_KEYS[$driver] as $key) {
            if (empty($diskConfig[$key])) {
                $missing[] = $key;
            }
        }

        if (empty($missing)) {
            return ['status' => CheckStatus::PASS, 'message' => "Disk '{$name}' ({$driver}) configuration is present."];
        }

        return [
            'status' => CheckStatus::WARNING,
            'message' => "Disk '{$name}' ({$driver}) configuration is incomplete: missing " . implode(', ', $missing) . '.',
        ];
    }
}
