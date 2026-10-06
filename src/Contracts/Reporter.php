<?php

namespace Anivesh\LaravelProductionGuard\Contracts;

use Anivesh\LaravelProductionGuard\DTO\ProductionReport;

interface Reporter
{
    /**
     * Render a finished report to a displayable/consumable string.
     *
     * @param ProductionReport $report
     * @return string
     */
    public function render(ProductionReport $report);
}
