<?php

namespace Anivesh\LaravelProductionGuard\Reports;

use Anivesh\LaravelProductionGuard\Contracts\Reporter;
use Anivesh\LaravelProductionGuard\DTO\ProductionReport;

final class JsonReporter implements Reporter
{
    /**
     * @param ProductionReport $report
     * @return string
     */
    public function render(ProductionReport $report)
    {
        return json_encode(
            $report->toArray(),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );
    }
}
