<?php

namespace Anivesh\LaravelProductionGuard\Services;

use Anivesh\LaravelProductionGuard\Contracts\Check;
use Anivesh\LaravelProductionGuard\DTO\CheckResult;
use Throwable;

/**
 * Stands in for a registered check that the container failed to construct
 * (e.g. a custom check with an unresolvable constructor dependency). Its
 * check() never throws - it immediately returns the original failure as an
 * UNKNOWN result - so a broken registration degrades the same way a check
 * that throws during check() does: one bad check never aborts the whole run.
 */
final class UnresolvedCheck implements Check
{
    /** @var string */
    private $identifier;

    /** @var Throwable */
    private $exception;

    /**
     * @param string|Check $identifier Whatever was registered - a class-string or instance.
     * @param Throwable $exception
     */
    public function __construct($identifier, Throwable $exception)
    {
        $this->identifier = is_string($identifier) ? $identifier : get_class($identifier);
        $this->exception = $exception;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->identifier;
    }

    /**
     * Resolution failed before the real category could even be known, so
     * this uses its own pseudo-category rather than guessing - resolve()
     * always includes an UnresolvedCheck regardless of --only filtering,
     * since hiding a broken custom check behind a category filter would be
     * exactly the kind of silent failure this package exists to surface.
     *
     * @return string
     */
    public function getCategory()
    {
        return 'unresolved';
    }

    /**
     * @return CheckResult
     */
    public function check()
    {
        return CheckResult::fromException($this->identifier, $this->getCategory(), $this->exception);
    }
}
