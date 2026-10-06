<?php

namespace Anivesh\LaravelProductionGuard\Tests\Fixtures;

use Anivesh\LaravelProductionGuard\Checkers\AbstractCheck;

class AggregatingCheck extends AbstractCheck
{
    /** @var array */
    private $facts;

    /**
     * @param array $facts
     */
    public function __construct(array $facts)
    {
        $this->facts = $facts;
    }

    public function getName()
    {
        return 'Aggregating Check';
    }

    public function getCategory()
    {
        return 'environment';
    }

    public function check()
    {
        return $this->aggregate($this->facts);
    }
}
