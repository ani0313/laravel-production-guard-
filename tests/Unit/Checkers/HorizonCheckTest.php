<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\HorizonCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Tests\Fixtures\FakeMasterSupervisorRepository;
use Illuminate\Container\Container;
use PHPUnit\Framework\TestCase;

class HorizonCheckTest extends TestCase
{
    /**
     * Declared first deliberately: it must run before the fixture Horizon
     * class below is ever defined (class_exists() can't be "undone" once
     * a class is loaded into the process).
     */
    public function test_not_applicable_when_horizon_is_not_installed()
    {
        $this->assertFalse(class_exists('Laravel\\Horizon\\Horizon'));

        $result = (new HorizonCheck(new Container()))->check();

        $this->assertSame(CheckStatus::NOT_APPLICABLE, $result->getStatus());
    }

    public function test_reports_pass_when_a_master_supervisor_is_active()
    {
        $this->loadFakeHorizon();
        \Laravel\Horizon\Horizon::$paused = false;
        FakeMasterSupervisorRepository::$names = ['supervisor-1'];

        $result = (new HorizonCheck($this->containerWithFakeRepository()))->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
    }

    public function test_reports_warning_when_no_master_supervisor_is_active()
    {
        $this->loadFakeHorizon();
        \Laravel\Horizon\Horizon::$paused = false;
        FakeMasterSupervisorRepository::$names = [];

        $result = (new HorizonCheck($this->containerWithFakeRepository()))->check();

        $this->assertSame(CheckStatus::WARNING, $result->getStatus());
    }

    public function test_reports_warning_when_paused()
    {
        $this->loadFakeHorizon();
        \Laravel\Horizon\Horizon::$paused = true;
        FakeMasterSupervisorRepository::$names = ['supervisor-1'];

        $result = (new HorizonCheck($this->containerWithFakeRepository()))->check();

        $this->assertSame(CheckStatus::WARNING, $result->getStatus());
        $pausedFact = $result->getDetails()[1];
        $this->assertSame('Horizon is paused.', $pausedFact['message']);
    }

    private function loadFakeHorizon()
    {
        if (!class_exists('Laravel\\Horizon\\Horizon')) {
            require_once __DIR__ . '/../../Fixtures/Horizon/Horizon.php';
        }
    }

    private function containerWithFakeRepository()
    {
        $container = new Container();
        $container->bind('Laravel\\Horizon\\Contracts\\MasterSupervisorRepository', function () {
            return new FakeMasterSupervisorRepository();
        });

        return $container;
    }
}
