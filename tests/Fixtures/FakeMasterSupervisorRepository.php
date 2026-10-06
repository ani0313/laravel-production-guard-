<?php

namespace Anivesh\LaravelProductionGuard\Tests\Fixtures;

class FakeMasterSupervisorRepository
{
    /** @var array */
    public static $names = [];

    /**
     * @return array
     */
    public function names()
    {
        return self::$names;
    }
}
