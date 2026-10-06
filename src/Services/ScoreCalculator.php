<?php

namespace Anivesh\LaravelProductionGuard\Services;

use Anivesh\LaravelProductionGuard\DTO\CheckResult;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;

final class ScoreCalculator
{
    /** @var array category => weight */
    private $weights;

    /**
     * @param array $weights category => weight, e.g. config('production-guard.weights')
     */
    public function __construct(array $weights)
    {
        $this->weights = $weights;
    }

    /**
     * Weighted 0-100 score across categories. A category only contributes
     * if it has at least one PASS/WARNING/CRITICAL result - categories that
     * are entirely UNKNOWN/NOT_SUPPORTED/NOT_APPLICABLE are excluded from
     * both the numerator and the denominator so they never drag the score
     * down (or inflate it) for something that genuinely couldn't be
     * verified or doesn't apply.
     *
     * @param CheckResult[] $results
     * @return int 0-100
     */
    public function calculate(array $results)
    {
        $byCategory = [];
        foreach ($results as $result) {
            if (!$result->isScorable()) {
                continue;
            }
            $byCategory[$result->getCategory()][] = $result;
        }

        if (empty($byCategory)) {
            return 0;
        }

        $weightedSum = 0.0;
        $weightTotal = 0.0;

        foreach ($byCategory as $category => $categoryResults) {
            $earned = 0.0;
            $possible = 0.0;
            foreach ($categoryResults as $result) {
                $earned += $result->getScore();
                $possible += $result->getMaxScore();
            }

            if ($possible <= 0) {
                continue;
            }

            $categoryScore = ($earned / $possible) * 100;
            $weight = isset($this->weights[$category]) ? (float) $this->weights[$category] : 0.0;

            $weightedSum += $categoryScore * $weight;
            $weightTotal += $weight;
        }

        if ($weightTotal <= 0) {
            return 0;
        }

        return (int) round($weightedSum / $weightTotal);
    }

    /**
     * Derive the overall status from the worst result present, never from
     * the numeric score alone - a high score with one CRITICAL result must
     * still report CRITICAL, and a run with no verifiable results must
     * never falsely report PASS.
     *
     * @param CheckResult[] $results
     * @return string one of the CheckStatus constants
     */
    public function deriveStatus(array $results)
    {
        if (empty($results)) {
            return CheckStatus::UNKNOWN;
        }

        $hasCritical = false;
        $hasWarning = false;
        $hasUnknown = false;

        foreach ($results as $result) {
            switch ($result->getStatus()) {
                case CheckStatus::CRITICAL:
                    $hasCritical = true;
                    break;
                case CheckStatus::WARNING:
                    $hasWarning = true;
                    break;
                case CheckStatus::UNKNOWN:
                case CheckStatus::NOT_SUPPORTED:
                    $hasUnknown = true;
                    break;
            }
        }

        if ($hasCritical) {
            return CheckStatus::CRITICAL;
        }

        if ($hasWarning) {
            return CheckStatus::WARNING;
        }

        if ($hasUnknown) {
            return CheckStatus::UNKNOWN;
        }

        return CheckStatus::PASS;
    }
}
