<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\MailCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;

class MailCheckTest extends TestCase
{
    public function test_no_driver_configured_is_a_warning()
    {
        $result = (new MailCheck(new Repository(['mail' => []])))->check();

        $this->assertSame(CheckStatus::WARNING, $result->getStatus());
    }

    public function test_non_smtp_driver_only_checks_the_driver_itself()
    {
        $result = (new MailCheck(new Repository(['mail' => ['driver' => 'log']])))->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
        $this->assertCount(1, $result->getDetails());
    }

    public function test_fully_configured_smtp_passes_and_never_leaks_the_password()
    {
        $config = new Repository(['mail' => [
            'driver' => 'smtp',
            'host' => 'smtp.example.com',
            'port' => 587,
            'encryption' => 'tls',
            'username' => 'user@example.com',
            'password' => 'super-secret-value',
        ]]);

        $result = (new MailCheck($config))->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
        $this->assertNotContains('super-secret-value', json_encode($result->toArray()));
    }

    public function test_smtp_missing_encryption_is_a_warning()
    {
        $config = new Repository(['mail' => [
            'driver' => 'smtp', 'host' => 'smtp.example.com', 'port' => 587, 'username' => 'u',
        ]]);

        $result = (new MailCheck($config))->check();

        $this->assertSame(CheckStatus::WARNING, $result->getStatus());
    }

    public function test_laravel_9_style_mailers_config_is_also_read()
    {
        $config = new Repository(['mail' => [
            'default' => 'smtp',
            'mailers' => ['smtp' => ['host' => 'smtp.example.com', 'port' => 587, 'encryption' => 'tls', 'username' => 'u']],
        ]]);

        $result = (new MailCheck($config))->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
    }
}
