<?php

namespace Anivesh\LaravelProductionGuard\Tests\Fixtures;

use Anivesh\LaravelProductionGuard\Contracts\Check;
use RuntimeException;

class ThrowingCheck implements Check
{
    public function getName()
    {
        return 'Throwing Check';
    }

    public function getCategory()
    {
        return 'environment';
    }

    public function check()
    {
        throw new RuntimeException('Simulated checker failure.');
    }
}
