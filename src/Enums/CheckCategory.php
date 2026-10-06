<?php

namespace Anivesh\LaravelProductionGuard\Enums;

/**
 * Plain const-based category set (not a native PHP enum) so this class stays
 * PHP 7.1-compatible for the 1.x release line (Laravel 5.4-5.8).
 */
final class CheckCategory
{
    const ENVIRONMENT = 'environment';
    const APPLICATION = 'application';
    const PHP = 'php';
    const DATABASE = 'database';
    const CACHE = 'cache';
    const QUEUE = 'queue';
    const QUEUE_WORKER = 'queue_worker';
    const SCHEDULER = 'scheduler';
    const STORAGE = 'storage';
    const SERVER = 'server';
    const SECURITY = 'security';
    const REDIS = 'redis';
    const BEANSTALKD = 'beanstalkd';
    const HORIZON = 'horizon';
    const SUPERVISOR = 'supervisor';
    const OPCACHE = 'opcache';
    const COMPOSER = 'composer';
    const MIGRATIONS = 'migrations';
    const LOGGING = 'logging';
    const MAIL = 'mail';
    const SESSION = 'session';
    const FILESYSTEM = 'filesystem';
    const NETWORK = 'network';
    const PHP_FPM = 'php_fpm';
    const NGINX = 'nginx';

    /**
     * @return string[]
     */
    public static function all()
    {
        return [
            self::ENVIRONMENT,
            self::APPLICATION,
            self::PHP,
            self::DATABASE,
            self::CACHE,
            self::QUEUE,
            self::QUEUE_WORKER,
            self::SCHEDULER,
            self::STORAGE,
            self::SERVER,
            self::SECURITY,
            self::REDIS,
            self::BEANSTALKD,
            self::HORIZON,
            self::SUPERVISOR,
            self::OPCACHE,
            self::COMPOSER,
            self::MIGRATIONS,
            self::LOGGING,
            self::MAIL,
            self::SESSION,
            self::FILESYSTEM,
            self::NETWORK,
            self::PHP_FPM,
            self::NGINX,
        ];
    }
}
