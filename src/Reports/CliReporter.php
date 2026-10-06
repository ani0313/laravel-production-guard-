<?php

namespace Anivesh\LaravelProductionGuard\Reports;

use Anivesh\LaravelProductionGuard\Contracts\Reporter;
use Anivesh\LaravelProductionGuard\DTO\CheckResult;
use Anivesh\LaravelProductionGuard\DTO\ProductionReport;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;

final class CliReporter implements Reporter
{
    /**
     * @param ProductionReport $report
     * @return string
     */
    public function render(ProductionReport $report)
    {
        $lines = [];
        $lines[] = '<info>Laravel Production Guard</info>';
        $lines[] = '';

        $byCategory = [];
        foreach ($report->getChecks() as $check) {
            $byCategory[$check->getCategory()][] = $check;
        }

        foreach ($byCategory as $category => $checks) {
            $lines[] = '<comment>' . $this->humanizeCategory($category) . '</comment>';
            foreach ($checks as $check) {
                $lines[] = $this->renderCheck($check);
            }
            $lines[] = '';
        }

        $lines[] = sprintf('Health Score: <info>%d/100</info>', $report->getScore());
        $lines[] = sprintf('Status: %s', $this->renderStatusLabel($report->getStatus()));

        return implode("\n", $lines);
    }

    /**
     * A checker built via Checkers\AbstractCheck::aggregate() carries one
     * `details` entry per individual fact it observed (e.g. APP_ENV,
     * APP_DEBUG, APP_KEY) - render one glyph line per fact so the output
     * matches the documented multi-line-per-category CLI format. A checker
     * with no structured details falls back to its single status/message.
     *
     * @param CheckResult $check
     * @return string
     */
    private function renderCheck(CheckResult $check)
    {
        $details = $check->getDetails();
        $facts = $this->isFactList($details) ? $details : [['status' => $check->getStatus(), 'message' => $check->getMessage()]];

        $lines = [];
        foreach ($facts as $fact) {
            $lines[] = sprintf('%s %s', $this->glyphFor($fact['status']), $fact['message']);
        }

        foreach ($check->getRecommendations() as $recommendation) {
            $lines[] = '    -> ' . $recommendation;
        }

        return implode("\n", $lines);
    }

    /**
     * @param array $details
     * @return bool
     */
    private function isFactList(array $details)
    {
        return !empty($details) && isset($details[0]['status'], $details[0]['message']);
    }

    /**
     * @param string $status
     * @return string
     */
    private function glyphFor($status)
    {
        switch ($status) {
            case CheckStatus::PASS:
                return '<info>✓</info>';
            case CheckStatus::WARNING:
                return '<comment>⚠</comment>';
            case CheckStatus::CRITICAL:
                return '<error>✗</error>';
            case CheckStatus::NOT_APPLICABLE:
                return '-';
            default:
                return '?';
        }
    }

    /**
     * @param string $status
     * @return string
     */
    private function renderStatusLabel($status)
    {
        switch ($status) {
            case CheckStatus::PASS:
                return '<info>' . strtoupper($status) . '</info>';
            case CheckStatus::WARNING:
                return '<comment>WARNING</comment>';
            case CheckStatus::CRITICAL:
                return '<error>CRITICAL</error>';
            default:
                return strtoupper($status);
        }
    }

    /**
     * @param string $category
     * @return string
     */
    private function humanizeCategory($category)
    {
        return ucwords(str_replace('_', ' ', $category));
    }
}
