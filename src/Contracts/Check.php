<?php

namespace Anivesh\LaravelProductionGuard\Contracts;

use Anivesh\LaravelProductionGuard\DTO\CheckResult;

interface Check
{
    /**
     * Human-readable name shown in reports, e.g. "Database Connection".
     *
     * @return string
     */
    public function getName();

    /**
     * One of the Anivesh\LaravelProductionGuard\Enums\CheckCategory constants.
     *
     * @return string
     */
    public function getCategory();

    /**
     * Run the check and return its result.
     *
     * Implementations must be read-only: no schema changes, no data
     * mutation, no job dispatch, no service restarts. Implementations
     * should not throw for an expected "could not verify" condition -
     * return a CheckResult with CheckStatus::UNKNOWN/NOT_SUPPORTED instead.
     * An uncaught exception is still safely handled by the registry, which
     * converts it into an UNKNOWN result.
     *
     * @return CheckResult
     */
    public function check();
}
