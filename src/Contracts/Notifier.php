<?php

namespace Anivesh\LaravelProductionGuard\Contracts;

use Anivesh\LaravelProductionGuard\DTO\ProductionReport;

/**
 * Forward-looking abstraction for the future monitoring/notification mode
 * (email, Slack, Telegram, Discord, webhook). Not wired into
 * `production:check` in v1 - notifications stay opt-in and disabled by
 * default via config('production-guard.notifications.enabled').
 */
interface Notifier
{
    /**
     * @param ProductionReport $report
     * @return void
     */
    public function notify(ProductionReport $report);
}
