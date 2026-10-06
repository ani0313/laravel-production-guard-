<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Services\ProcessService;

class PhpFpmCheck extends AbstractCheck
{
    /** @var ProcessService */
    private $process;

    /**
     * @param ProcessService $process
     */
    public function __construct(ProcessService $process)
    {
        $this->process = $process;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'PHP-FPM';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::PHP_FPM;
    }

    /**
     * This command (production:check) always runs under the CLI SAPI, so
     * PHP_SAPI can never tell us whether a separate php-fpm pool is
     * serving the actual web requests - process inspection is the only
     * generic way to find out.
     *
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $facts = [$this->processFact()];

        $versionFact = $this->versionFact();
        if ($versionFact !== null) {
            $facts[] = $versionFact;
        }

        return $this->aggregate($facts);
    }

    /**
     * @return array
     */
    private function processFact()
    {
        $result = $this->process->run('ps', ['aux']);

        if (!$result['ok']) {
            return ['status' => CheckStatus::UNKNOWN, 'message' => 'PHP-FPM process status could not be determined.'];
        }

        $matches = 0;
        foreach (explode("\n", $result['output']) as $line) {
            if (stripos($line, 'php-fpm') !== false) {
                $matches++;
            }
        }

        if ($matches > 0) {
            return ['status' => CheckStatus::PASS, 'message' => sprintf('Detected %d php-fpm process(es).', $matches)];
        }

        return ['status' => CheckStatus::UNKNOWN, 'message' => 'No php-fpm process was detected (it may not be in use, or process inspection is unavailable).'];
    }

    /**
     * @return array|null
     */
    private function versionFact()
    {
        $result = $this->process->run('php-fpm', ['-v']);

        if (!$result['ok']) {
            return null;
        }

        $firstLine = strtok(trim($result['output']) ?: trim($result['error']), "\n");

        if (empty($firstLine)) {
            return null;
        }

        return ['status' => CheckStatus::PASS, 'message' => $firstLine];
    }
}
