<?php

namespace Anivesh\LaravelProductionGuard\Tests\Fixtures;

use Anivesh\LaravelProductionGuard\Contracts\Check;
use Anivesh\LaravelProductionGuard\DTO\CheckResult;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;

/**
 * A required scalar constructor parameter with no default can never be
 * auto-resolved by the container - simulates a misconfigured custom check.
 */
class UnresolvableCheck implements Check
{
    public function __construct($requiredScalarWithNoDefault)
    {
    }

    public function getName()
    {
        return 'Unresolvable Check';
    }

    public function getCategory()
    {
        return 'environment';
    }

    public function check()
    {
        return new CheckResult($this->getName(), $this->getCategory(), CheckStatus::PASS, 1, 1, 'unreachable');
    }
}
