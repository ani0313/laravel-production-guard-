<?php

namespace Anivesh\LaravelProductionGuard\Enums;

/**
 * Plain const-based status set (not a native PHP enum) so this class stays
 * PHP 7.1-compatible for the 1.x release line (Laravel 5.4-5.8).
 */
final class CheckStatus
{
    const PASS = 'passed';
    const WARNING = 'warning';
    const CRITICAL = 'critical';
    const UNKNOWN = 'unknown';
    const NOT_SUPPORTED = 'not_supported';
    const NOT_APPLICABLE = 'not_applicable';

    /**
     * Statuses that count toward the health score. UNKNOWN / NOT_SUPPORTED /
     * NOT_APPLICABLE are excluded from both the numerator and denominator so
     * an unverifiable or inapplicable check never moves the score in either
     * direction.
     *
     * @return string[]
     */
    public static function scorable()
    {
        return [self::PASS, self::WARNING, self::CRITICAL];
    }

    /**
     * @return string[]
     */
    public static function all()
    {
        return [
            self::PASS,
            self::WARNING,
            self::CRITICAL,
            self::UNKNOWN,
            self::NOT_SUPPORTED,
            self::NOT_APPLICABLE,
        ];
    }

    /**
     * Relative severity used to derive the worst status across a set of
     * results. Higher is worse.
     *
     * @param string $status
     * @return int
     */
    public static function severity($status)
    {
        $order = [
            self::PASS => 0,
            self::UNKNOWN => 1,
            self::NOT_SUPPORTED => 1,
            self::NOT_APPLICABLE => 0,
            self::WARNING => 2,
            self::CRITICAL => 3,
        ];

        return isset($order[$status]) ? $order[$status] : 0;
    }
}
