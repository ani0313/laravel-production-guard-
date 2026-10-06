<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\EnvironmentCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;

class EnvironmentCheckTest extends TestCase
{
    private function check(array $app)
    {
        return new EnvironmentCheck(new Repository(['app' => $app]));
    }

    public function test_a_healthy_production_configuration_passes()
    {
        $result = $this->check([
            'env' => 'production',
            'debug' => false,
            'key' => 'base64:somekey',
            'url' => 'https://app.example.com',
        ])->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
    }

    public function test_debug_enabled_in_production_is_critical()
    {
        $result = $this->check([
            'env' => 'production',
            'debug' => true,
            'key' => 'base64:somekey',
            'url' => 'https://app.example.com',
        ])->check();

        $this->assertSame(CheckStatus::CRITICAL, $result->getStatus());
    }

    public function test_debug_enabled_outside_production_is_only_a_warning()
    {
        $result = $this->check([
            'env' => 'local',
            'debug' => true,
            'key' => 'base64:somekey',
            'url' => 'http://localhost',
        ])->check();

        $this->assertSame(CheckStatus::WARNING, $result->getStatus());

        foreach ($result->getDetails() as $fact) {
            $this->assertNotSame(CheckStatus::CRITICAL, $fact['status']);
        }
    }

    public function test_missing_app_key_is_critical_and_never_echoes_a_key_value()
    {
        $result = $this->check([
            'env' => 'production',
            'debug' => false,
            'key' => null,
            'url' => 'https://app.example.com',
        ])->check();

        $this->assertSame(CheckStatus::CRITICAL, $result->getStatus());

        foreach ($result->getDetails() as $fact) {
            $this->assertNotContains('base64:', $fact['message']);
        }
    }

    public function test_localhost_app_url_is_a_warning()
    {
        $result = $this->check([
            'env' => 'production',
            'debug' => false,
            'key' => 'base64:somekey',
            'url' => 'http://localhost',
        ])->check();

        $this->assertSame(CheckStatus::WARNING, $result->getStatus());
    }
}
