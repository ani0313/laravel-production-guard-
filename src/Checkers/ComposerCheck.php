<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Foundation\Application;

class ComposerCheck extends AbstractCheck
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
        return 'Composer';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::COMPOSER;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $facts = [];
        $facts[] = $this->vendorFact();

        $lock = $this->readLock();
        $facts[] = $this->lockFact($lock);

        if ($lock !== null) {
            $facts[] = $this->abandonedPackagesFact($lock);
        }

        $phpConstraintFact = $this->phpConstraintFact();
        if ($phpConstraintFact !== null) {
            $facts[] = $phpConstraintFact;
        }

        return $this->aggregate($facts);
    }

    /**
     * @return array
     */
    private function vendorFact()
    {
        if (is_dir($this->app->basePath('vendor'))) {
            return ['status' => CheckStatus::PASS, 'message' => 'vendor/ directory is present.'];
        }

        return ['status' => CheckStatus::CRITICAL, 'message' => 'vendor/ directory is missing - dependencies are not installed.'];
    }

    /**
     * @return array|null Decoded composer.lock, or null if missing/invalid.
     */
    private function readLock()
    {
        $path = $this->app->basePath('composer.lock');

        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) @file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    /**
     * @param array|null $lock
     * @return array
     */
    private function lockFact($lock)
    {
        if ($lock === null) {
            return [
                'status' => CheckStatus::WARNING,
                'message' => 'composer.lock was not found or could not be read.',
                'recommendation' => 'Commit composer.lock to ensure consistent, reproducible installs.',
            ];
        }

        return ['status' => CheckStatus::PASS, 'message' => 'composer.lock is present.'];
    }

    /**
     * @param array $lock
     * @return array
     */
    private function abandonedPackagesFact(array $lock)
    {
        $abandoned = [];

        foreach (array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []) as $package) {
            if (!empty($package['abandoned'])) {
                $abandoned[] = $package['name'];
            }
        }

        if (empty($abandoned)) {
            return ['status' => CheckStatus::PASS, 'message' => 'No abandoned packages detected in composer.lock.'];
        }

        return [
            'status' => CheckStatus::WARNING,
            'message' => 'Abandoned package(s) detected: ' . implode(', ', $abandoned) . '.',
            'recommendation' => 'Consider replacing abandoned packages with maintained alternatives.',
        ];
    }

    /**
     * Informational only - evaluating a full composer version constraint
     * correctly (e.g. "^7.1|^7.2") is genuinely non-trivial to hand-roll,
     * and a wrong computed verdict would be worse than just surfacing the
     * two values side by side.
     *
     * @return array|null
     */
    private function phpConstraintFact()
    {
        $path = $this->app->basePath('composer.json');

        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) @file_get_contents($path), true);
        $constraint = $decoded['require']['php'] ?? null;

        if (empty($constraint)) {
            return null;
        }

        return ['status' => CheckStatus::PASS, 'message' => "composer.json requires PHP {$constraint}; running " . PHP_VERSION . '.'];
    }
}
