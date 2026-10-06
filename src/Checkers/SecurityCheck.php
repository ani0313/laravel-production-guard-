<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;

/**
 * APP_DEBUG/APP_ENV/APP_KEY/APP_URL presence are already covered by
 * EnvironmentCheck - repeating them here under "security" too would just
 * duplicate the same CLI lines under two headings. This checker covers the
 * security-relevant facts EnvironmentCheck doesn't: HTTPS enforcement,
 * .env file permissions, debug/dev packages left enabled in production,
 * and (only when explicitly configured) security response headers.
 */
class SecurityCheck extends AbstractCheck
{
    /**
     * Known debug/development packages that should never be active with
     * APP_ENV=production.
     *
     * @var array class => display name
     */
    const DEBUG_PACKAGES = [
        'Laravel\\Telescope\\TelescopeServiceProvider' => 'Laravel Telescope',
        'Barryvdh\\Debugbar\\ServiceProvider' => 'Laravel Debugbar',
    ];

    /** @var Application */
    private $app;

    /** @var Config */
    private $config;

    /**
     * @param Application $app
     * @param Config $config
     */
    public function __construct(Application $app, Config $config)
    {
        $this->app = $app;
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Security';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::SECURITY;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $facts = [];
        $facts[] = $this->httpsFact();
        $facts[] = $this->secureCookieFact();
        $facts[] = $this->envPermissionsFact();

        $debugPackagesFact = $this->debugPackagesFact();
        if ($debugPackagesFact !== null) {
            $facts[] = $debugPackagesFact;
        }

        $headersFact = $this->securityHeadersFact();
        if ($headersFact !== null) {
            $facts[] = $headersFact;
        }

        return $this->aggregate($facts);
    }

    /**
     * @return array
     */
    private function httpsFact()
    {
        $url = (string) $this->config->get('app.url');
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if ($scheme === 'https') {
            return ['status' => CheckStatus::PASS, 'message' => 'APP_URL uses HTTPS.'];
        }

        if ($this->app->environment('production')) {
            return [
                'status' => CheckStatus::WARNING,
                'message' => 'APP_URL does not use HTTPS in production.',
                'recommendation' => 'Serve the application over HTTPS in production.',
            ];
        }

        return ['status' => CheckStatus::PASS, 'message' => 'APP_URL does not use HTTPS (not production).'];
    }

    /**
     * @return array
     */
    private function secureCookieFact()
    {
        if (!$this->app->environment('production')) {
            return ['status' => CheckStatus::PASS, 'message' => 'Secure session cookie is not required outside production.'];
        }

        if ($this->config->get('session.secure')) {
            return ['status' => CheckStatus::PASS, 'message' => 'Session cookies are marked secure.'];
        }

        return [
            'status' => CheckStatus::WARNING,
            'message' => 'Session cookies are not marked secure in production.',
            'recommendation' => 'Set SESSION_SECURE_COOKIE=true in production.',
        ];
    }

    /**
     * @return array
     */
    private function envPermissionsFact()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return ['status' => CheckStatus::NOT_SUPPORTED, 'message' => '.env permission checks are not supported on Windows.'];
        }

        $path = $this->app->basePath('.env');

        if (!is_file($path)) {
            return ['status' => CheckStatus::NOT_SUPPORTED, 'message' => '.env file not found; permissions could not be checked.'];
        }

        $permissions = @fileperms($path);

        if ($permissions === false) {
            return ['status' => CheckStatus::UNKNOWN, 'message' => '.env permissions could not be read.'];
        }

        if ($permissions & 0004) {
            return [
                'status' => CheckStatus::WARNING,
                'message' => '.env is readable by other users on this system.',
                'recommendation' => 'Restrict .env to the owning user only (e.g. chmod 600 .env).',
            ];
        }

        return ['status' => CheckStatus::PASS, 'message' => '.env is not readable by other users.'];
    }

    /**
     * @return array|null
     */
    private function debugPackagesFact()
    {
        if (!$this->app->environment('production')) {
            return null;
        }

        $found = [];
        foreach (self::DEBUG_PACKAGES as $class => $name) {
            if (class_exists($class)) {
                $found[] = $name;
            }
        }

        if (empty($found)) {
            return ['status' => CheckStatus::PASS, 'message' => 'No known debug/development packages detected.'];
        }

        return [
            'status' => CheckStatus::WARNING,
            'message' => 'Debug/development package(s) detected in production: ' . implode(', ', $found) . '.',
            'recommendation' => 'Ensure debug/development packages are not registered in production.',
        ];
    }

    /**
     * Disabled by default (config('production-guard.external.enabled')) -
     * this package never contacts a third-party/your-own host unless a URL
     * is explicitly configured.
     *
     * @return array|null
     */
    private function securityHeadersFact()
    {
        if (!$this->config->get('production-guard.external.enabled')) {
            return null;
        }

        $urls = (array) $this->config->get('production-guard.external.urls', []);
        if (empty($urls)) {
            return null;
        }

        $url = $urls[0];
        $timeout = (float) $this->config->get('production-guard.timeouts.http', 5);

        $context = stream_context_create(['http' => ['timeout' => $timeout, 'method' => 'GET']]);
        $headers = @get_headers($url, 1, $context);

        if ($headers === false) {
            return ['status' => CheckStatus::UNKNOWN, 'message' => "Could not fetch headers from {$url}."];
        }

        $headers = array_change_key_case($headers, CASE_LOWER);
        $expected = ['x-frame-options', 'x-content-type-options', 'strict-transport-security'];
        $missing = array_values(array_filter($expected, function ($header) use ($headers) {
            return !isset($headers[$header]);
        }));

        if (empty($missing)) {
            return ['status' => CheckStatus::PASS, 'message' => "All checked security headers are present on {$url}."];
        }

        return [
            'status' => CheckStatus::WARNING,
            'message' => "Missing security header(s) on {$url}: " . implode(', ', $missing) . '.',
        ];
    }
}
