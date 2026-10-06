<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Contracts\Check;
use Anivesh\LaravelProductionGuard\DTO\CheckResult;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;

/**
 * A checker observes several individual facts (e.g. APP_ENV, APP_DEBUG,
 * APP_KEY for EnvironmentCheck) but the Check contract returns exactly one
 * CheckResult. aggregate() combines a list of facts into that one result:
 * the overall status is the worst fact present, the score is earned only
 * from facts that are actually PASS/WARNING/CRITICAL (an UNKNOWN/
 * NOT_SUPPORTED/NOT_APPLICABLE fact never moves the score), and each fact
 * is kept in `details` so reporters can render one line per fact.
 */
abstract class AbstractCheck implements Check
{
    /**
     * @param array $facts Each entry: ['status' => CheckStatus::*, 'message' => string, 'recommendation' => string|null]
     * @param float $pointsPerFact
     * @return CheckResult
     */
    protected function aggregate(array $facts, $pointsPerFact = 10)
    {
        $score = 0.0;
        $maxScore = 0.0;
        $worstStatus = CheckStatus::PASS;
        $worstSeverity = -1;
        $recommendations = [];
        $problemMessages = [];

        foreach ($facts as $fact) {
            $status = $fact['status'];

            if (in_array($status, CheckStatus::scorable(), true)) {
                $maxScore += $pointsPerFact;

                if ($status === CheckStatus::PASS) {
                    $score += $pointsPerFact;
                } elseif ($status === CheckStatus::WARNING) {
                    $score += $pointsPerFact * 0.5;
                }
            }

            if ($status !== CheckStatus::PASS && $status !== CheckStatus::NOT_APPLICABLE) {
                $problemMessages[] = $fact['message'];
            }

            if (!empty($fact['recommendation'])) {
                $recommendations[] = $fact['recommendation'];
            }

            $severity = CheckStatus::severity($status);
            if ($severity > $worstSeverity) {
                $worstSeverity = $severity;
                $worstStatus = $status;
            }
        }

        $message = empty($problemMessages)
            ? sprintf('All %d check(s) passed.', count($facts))
            : implode(' ', $problemMessages);

        return new CheckResult($this->getName(), $this->getCategory(), $worstStatus, $score, $maxScore, $message, $facts, $recommendations);
    }

    /**
     * @param float $bytes
     * @return string
     */
    protected function formatBytes($bytes)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $index = 0;

        while ($bytes >= 1024 && $index < count($units) - 1) {
            $bytes /= 1024;
            $index++;
        }

        return round($bytes, 1) . ' ' . $units[$index];
    }
}
