<?php

namespace Anivesh\LaravelProductionGuard\Services;

use Anivesh\LaravelProductionGuard\Contracts\Check;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Throwable;

final class CheckRegistry
{
    /** @var Container */
    private $container;

    /**
     * @var array Each entry is a class-string or a Check instance.
     */
    private $checks = [];

    /**
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
    }

    /**
     * Register a check. Accepts a class-string (resolved through the
     * container when the registry runs, so constructor DI works) or an
     * already-built Check instance.
     *
     * @param string|Check $check
     * @return void
     */
    public function register($check)
    {
        if (!is_string($check) && !($check instanceof Check)) {
            throw new InvalidArgumentException('A registered check must be a class-string or a Check instance.');
        }

        $this->checks[] = $check;
    }

    /**
     * Register several checks at once.
     *
     * @param array $checks
     * @return void
     */
    public function registerMany(array $checks)
    {
        foreach ($checks as $check) {
            $this->register($check);
        }
    }

    /**
     * Resolve every registered check into a Check instance, optionally
     * filtered to a set of categories. A check that the container fails to
     * construct (e.g. a custom check with an unresolvable dependency)
     * becomes an UnresolvedCheck instead of throwing - and is always
     * included regardless of the category filter, since hiding a broken
     * registration behind --only would be a silent failure.
     *
     * @param string[] $categories Empty array means "no filter".
     * @return Check[]
     */
    public function resolve(array $categories = [])
    {
        $resolved = [];

        foreach ($this->checks as $check) {
            try {
                $instance = is_string($check) ? $this->container->make($check) : $check;
            } catch (Throwable $e) {
                $resolved[] = new UnresolvedCheck($check, $e);
                continue;
            }

            if (!($instance instanceof Check)) {
                continue;
            }

            if (empty($categories) || in_array($instance->getCategory(), $categories, true)) {
                $resolved[] = $instance;
            }
        }

        return $resolved;
    }

    /**
     * @return array
     */
    public function all()
    {
        return $this->checks;
    }
}
