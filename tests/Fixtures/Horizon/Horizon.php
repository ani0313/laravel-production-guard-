<?php

namespace Laravel\Horizon;

/**
 * Test-only stand-in for Laravel\Horizon\Horizon so HorizonCheck's
 * "Horizon is installed" path can be exercised without requiring the real
 * (large, Redis-dependent) laravel/horizon package as a dev dependency.
 * Loaded only from within @runInSeparateProcess tests.
 */
class Horizon
{
    /** @var bool */
    public static $paused = false;

    /**
     * @return bool
     */
    public static function isPaused()
    {
        return self::$paused;
    }
}
