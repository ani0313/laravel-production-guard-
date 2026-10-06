<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Services\ProcessService;

class NginxCheck extends AbstractCheck
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
        return 'Nginx';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::NGINX;
    }

    /**
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
            return ['status' => CheckStatus::UNKNOWN, 'message' => 'Nginx process status could not be determined.'];
        }

        $matches = 0;
        foreach (explode("\n", $result['output']) as $line) {
            if (stripos($line, 'nginx') !== false) {
                $matches++;
            }
        }

        if ($matches > 0) {
            return ['status' => CheckStatus::PASS, 'message' => sprintf('Detected %d nginx process(es).', $matches)];
        }

        return ['status' => CheckStatus::UNKNOWN, 'message' => 'No nginx process was detected (it may not be in use, or process inspection is unavailable).'];
    }

    /**
     * nginx -v conventionally writes its version banner to stderr.
     *
     * @return array|null
     */
    private function versionFact()
    {
        $result = $this->process->run('nginx', ['-v']);

        if (!$result['ok']) {
            return null;
        }

        $firstLine = strtok(trim($result['error']) ?: trim($result['output']), "\n");

        if (empty($firstLine)) {
            return null;
        }

        return ['status' => CheckStatus::PASS, 'message' => $firstLine];
    }
}
