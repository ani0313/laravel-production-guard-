<?php

namespace Anivesh\LaravelProductionGuard\Commands;

use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Reports\CliReporter;
use Anivesh\LaravelProductionGuard\Reports\JsonReporter;
use Anivesh\LaravelProductionGuard\Services\ProductionGuard;
use Anivesh\LaravelProductionGuard\Version;
use Illuminate\Console\Command;
use Throwable;

class ProductionCheckCommand extends Command
{
    /**
     * -v/--verbose and -q/--quiet are already provided by Symfony Console
     * on every Artisan command, so they are intentionally not redeclared
     * here.
     *
     * @var string
     */
    protected $signature = 'production:check
        {--json : Output the report as JSON instead of the CLI report}
        {--only=* : Limit to one or more categories (e.g. --only=database --only=queue); omit or pass "all" for everything}
        {--fail-on-warning : Exit with a non-zero code if any warning is present}
        {--fail-on-critical : Exit with a non-zero code if any critical issue is present}';

    /**
     * @var string
     */
    protected $description = 'Diagnose whether this Laravel application and its server are production-ready (read-only)';

    const EXIT_PASS = 0;
    const EXIT_WARNING = 1;
    const EXIT_CRITICAL = 2;
    const EXIT_ERROR = 3;

    /**
     * @param ProductionGuard $guard
     * @return int
     */
    public function handle(ProductionGuard $guard)
    {
        try {
            $categories = $this->resolveCategories();

            $report = $guard->run($categories, [
                'version' => Version::CURRENT,
                'laravel' => $this->laravel->version(),
                'php' => PHP_VERSION,
                'environment' => $this->laravel->environment(),
            ]);
        } catch (Throwable $e) {
            $this->error('production:check failed to run: ' . $e->getMessage());

            return self::EXIT_ERROR;
        }

        $reporter = $this->option('json') ? new JsonReporter() : new CliReporter();

        $this->line($reporter->render($report));

        return $this->exitCodeFor($report->getStatus());
    }

    /**
     * @return string[]
     */
    private function resolveCategories()
    {
        $only = array_filter((array) $this->option('only'));

        if (empty($only) || in_array('all', $only, true)) {
            return [];
        }

        return $only;
    }

    /**
     * @param string $status
     * @return int
     */
    private function exitCodeFor($status)
    {
        if ($this->option('fail-on-critical') && $status === CheckStatus::CRITICAL) {
            return self::EXIT_CRITICAL;
        }

        if ($this->option('fail-on-warning') && in_array($status, [CheckStatus::WARNING, CheckStatus::CRITICAL], true)) {
            return self::EXIT_WARNING;
        }

        return self::EXIT_PASS;
    }
}
