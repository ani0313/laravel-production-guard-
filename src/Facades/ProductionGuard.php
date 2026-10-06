<?php

namespace Anivesh\LaravelProductionGuard\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static void registerCheck(string|\Anivesh\LaravelProductionGuard\Contracts\Check $check)
 * @method static \Anivesh\LaravelProductionGuard\DTO\ProductionReport run(array $categories, array $meta)
 *
 * @see \Anivesh\LaravelProductionGuard\Services\ProductionGuard
 */
class ProductionGuard extends Facade
{
    /**
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'production-guard';
    }
}
