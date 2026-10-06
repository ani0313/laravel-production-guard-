<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enabled Checks
    |--------------------------------------------------------------------------
    |
    | Toggle individual checker categories on/off. A category set to false
    | here is skipped entirely (not even run as UNKNOWN) and does not
    | affect the score.
    */
    'checks' => [
        'environment' => true,
        'application' => true,
        'php' => true,
        'database' => true,
        'cache' => true,
        'queue' => true,
        'queue_worker' => true,
        'scheduler' => true,
        'storage' => true,
        'server' => true,
        'security' => true,
        'redis' => true,
        'beanstalkd' => true,
        'horizon' => true,
        'supervisor' => true,
        'opcache' => true,
        'composer' => true,
        'migrations' => true,
        'logging' => true,
        'mail' => true,
        'session' => true,
        'filesystem' => true,
        'network' => false,
        'php_fpm' => true,
        'nginx' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Score Weights
    |--------------------------------------------------------------------------
    |
    | How much each category contributes to the overall 0-100 health score.
    | A category with no scorable results in a given run (e.g. an optional
    | feature that isn't installed) is excluded from the weighted average
    | entirely rather than penalized.
    */
    'weights' => [
        'environment' => 10,
        'application' => 10,
        'php' => 5,
        'database' => 15,
        'cache' => 10,
        'queue' => 10,
        'queue_worker' => 5,
        'scheduler' => 5,
        'storage' => 5,
        'server' => 10,
        'security' => 15,
        'redis' => 0,
        'beanstalkd' => 0,
        'horizon' => 0,
        'supervisor' => 0,
        'opcache' => 0,
        'composer' => 0,
        'migrations' => 0,
        'logging' => 0,
        'mail' => 0,
        'session' => 0,
        'filesystem' => 0,
        'network' => 0,
        'php_fpm' => 0,
        'nginx' => 0,
    ],

    /*
    |--------------------------------------------------------------------------
    | Thresholds
    |--------------------------------------------------------------------------
    */
    'thresholds' => [
        'cpu_warning' => 80,
        'cpu_critical' => 95,
        'memory_warning' => 80,
        'memory_critical' => 90,
        'disk_warning' => 80,
        'disk_critical' => 90,
        'load_warning' => 2,
        'load_critical' => 4,
        'swap_warning' => 50,
        'swap_critical' => 80,
        'database_latency_warning_ms' => 500,
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeouts (seconds)
    |--------------------------------------------------------------------------
    |
    | Every external/system check is bounded by one of these so a broken
    | dependency (e.g. an unreachable Redis host) can never hang the command.
    */
    'timeouts' => [
        'database' => 3,
        'cache' => 3,
        'redis' => 3,
        'beanstalkd' => 3,
        'http' => 5,
        'process' => 3,
    ],

    /*
    |--------------------------------------------------------------------------
    | External Connectivity
    |--------------------------------------------------------------------------
    |
    | Disabled by default. The package never contacts a third-party host
    | unless you explicitly list one here.
    */
    'external' => [
        'enabled' => false,
        'urls' => [],
    ],

    /*
    |--------------------------------------------------------------------------
    | Logging
    |--------------------------------------------------------------------------
    |
    | Disabled by default - LoggingCheck never writes a log entry of its
    | own unless explicitly opted into here.
    */
    'logging' => [
        'test_write_enabled' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Reserved for the future monitoring mode (production:monitor). Disabled
    | by default - this package never sends anything anywhere on its own.
    */
    'notifications' => [
        'enabled' => false,
    ],

];
