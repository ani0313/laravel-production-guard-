<?php

namespace Anivesh\LaravelProductionGuard\Tests;

use Anivesh\LaravelProductionGuard\Facades\ProductionGuard as ProductionGuardFacade;
use Anivesh\LaravelProductionGuard\LaravelProductionGuardServiceProvider;
use Orchestra\Testbench\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            LaravelProductionGuardServiceProvider::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     * @return array
     */
    protected function getPackageAliases($app)
    {
        return [
            'ProductionGuard' => ProductionGuardFacade::class,
        ];
    }
}
