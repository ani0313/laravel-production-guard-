<?php

namespace Anivesh\LaravelProductionGuard\Tests\Fixtures;

use Anivesh\LaravelProductionGuard\Contracts\Check;
use Anivesh\LaravelProductionGuard\DTO\CheckResult;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;

class StubCheck implements Check
{
    /** @var string */
    public static $status = CheckStatus::PASS;

    /** @var float */
    public static $score = 10;

    /** @var float */
    public static $maxScore = 10;

    /** @var string */
    public static $category = 'environment';

    public function getName()
    {
        return 'Stub Check';
    }

    public function getCategory()
    {
        return self::$category;
    }

    public function check()
    {
        return new CheckResult($this->getName(), $this->getCategory(), self::$status, self::$score, self::$maxScore, 'Stub result.');
    }
}
