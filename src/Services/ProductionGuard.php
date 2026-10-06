<?php

namespace Anivesh\LaravelProductionGuard\Services;

use Anivesh\LaravelProductionGuard\DTO\CheckResult;
use Anivesh\LaravelProductionGuard\DTO\ProductionReport;
use Throwable;

final class ProductionGuard
{
    /** @var CheckRegistry */
    private $registry;

    /** @var ScoreCalculator */
    private $calculator;

    /**
     * @param CheckRegistry $registry
     * @param ScoreCalculator $calculator
     */
    public function __construct(CheckRegistry $registry, ScoreCalculator $calculator)
    {
        $this->registry = $registry;
        $this->calculator = $calculator;
    }

    /**
     * Register a custom check. Accepts a class-string or a Check instance.
     *
     * @param string|\Anivesh\LaravelProductionGuard\Contracts\Check $check
     * @return void
     */
    public function registerCheck($check)
    {
        $this->registry->register($check);
    }

    /**
     * Run every registered check (optionally filtered to a set of
     * categories) and build the final report. A checker that throws never
     * aborts the run - its result is recorded as UNKNOWN instead.
     *
     * @param string[] $categories Empty array means "run everything".
     * @param array $meta ['version' => string, 'laravel' => string, 'php' => string, 'environment' => string]
     * @return ProductionReport
     */
    public function run(array $categories, array $meta)
    {
        $results = [];

        foreach ($this->registry->resolve($categories) as $check) {
            try {
                $results[] = $check->check();
            } catch (Throwable $e) {
                $results[] = CheckResult::fromException($check->getName(), $check->getCategory(), $e);
            }
        }

        $score = $this->calculator->calculate($results);
        $status = $this->calculator->deriveStatus($results);

        return new ProductionReport(
            isset($meta['version']) ? $meta['version'] : 'unknown',
            isset($meta['laravel']) ? $meta['laravel'] : 'unknown',
            isset($meta['php']) ? $meta['php'] : PHP_VERSION,
            isset($meta['environment']) ? $meta['environment'] : 'unknown',
            $score,
            $status,
            $results
        );
    }
}
