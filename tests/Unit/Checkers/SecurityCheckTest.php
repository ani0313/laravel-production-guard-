<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\SecurityCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Tests\TestCase;

class SecurityCheckTest extends TestCase
{
    public function test_https_app_url_passes()
    {
        $this->app['config']->set('app.url', 'https://app.example.com');

        $result = $this->app->make(SecurityCheck::class)->check();

        $this->assertSame(CheckStatus::PASS, $result->getDetails()[0]['status']);
    }

    public function test_http_app_url_in_production_is_a_warning()
    {
        $this->app['config']->set('app.url', 'http://app.example.com');
        $this->app['env'] = 'production';

        $result = $this->app->make(SecurityCheck::class)->check();

        $this->assertSame(CheckStatus::WARNING, $result->getDetails()[0]['status']);
    }

    public function test_http_app_url_outside_production_is_fine()
    {
        $this->app['config']->set('app.url', 'http://localhost');
        $this->app['env'] = 'local';

        $result = $this->app->make(SecurityCheck::class)->check();

        $this->assertSame(CheckStatus::PASS, $result->getDetails()[0]['status']);
    }

    public function test_insecure_session_cookie_in_production_is_a_warning()
    {
        $this->app['env'] = 'production';
        $this->app['config']->set('session.secure', false);

        $result = $this->app->make(SecurityCheck::class)->check();

        $this->assertSame(CheckStatus::WARNING, $result->getDetails()[1]['status']);
    }

    public function test_debug_packages_are_only_checked_in_production()
    {
        $this->app['env'] = 'local';

        $result = $this->app->make(SecurityCheck::class)->check();

        // No debug-packages fact at all outside production (facts: https, cookie, env permissions = 3).
        $this->assertCount(3, $result->getDetails());
    }

    public function test_security_headers_are_skipped_when_external_checks_are_disabled()
    {
        $this->app['config']->set('production-guard.external.enabled', false);

        $result = $this->app->make(SecurityCheck::class)->check();

        foreach ($result->getDetails() as $fact) {
            $this->assertStringContainsNone('security header', $fact['message']);
        }
    }

    private function assertStringContainsNone($needle, $haystack)
    {
        $this->assertFalse(strpos($haystack, $needle) !== false);
    }
}
