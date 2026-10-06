<?php

namespace Anivesh\LaravelProductionGuard\DTO;

final class ProductionReport
{
    const PACKAGE_NAME = 'laravel-production-guard';

    /** @var string */
    private $packageVersion;

    /** @var string */
    private $laravelVersion;

    /** @var string */
    private $phpVersion;

    /** @var string */
    private $environment;

    /** @var int */
    private $score;

    /** @var string */
    private $status;

    /** @var CheckResult[] */
    private $checks;

    /**
     * @param string $packageVersion
     * @param string $laravelVersion
     * @param string $phpVersion
     * @param string $environment
     * @param int $score
     * @param string $status
     * @param CheckResult[] $checks
     */
    public function __construct($packageVersion, $laravelVersion, $phpVersion, $environment, $score, $status, array $checks)
    {
        $this->packageVersion = $packageVersion;
        $this->laravelVersion = $laravelVersion;
        $this->phpVersion = $phpVersion;
        $this->environment = $environment;
        $this->score = $score;
        $this->status = $status;
        $this->checks = $checks;
    }

    /**
     * @return string
     */
    public function getStatus()
    {
        return $this->status;
    }

    /**
     * @return int
     */
    public function getScore()
    {
        return $this->score;
    }

    /**
     * @return CheckResult[]
     */
    public function getChecks()
    {
        return $this->checks;
    }

    /**
     * @return array
     */
    public function toArray()
    {
        $checks = [];
        foreach ($this->checks as $check) {
            $checks[] = $check->toArray();
        }

        return [
            'package' => self::PACKAGE_NAME,
            'version' => $this->packageVersion,
            'laravel' => $this->laravelVersion,
            'php' => $this->phpVersion,
            'environment' => $this->environment,
            'score' => $this->score,
            'status' => $this->status,
            'checks' => $checks,
        ];
    }
}
