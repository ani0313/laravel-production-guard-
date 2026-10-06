<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Config\Repository as Config;

class EnvironmentCheck extends AbstractCheck
{
    /** @var Config */
    private $config;

    /**
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        $this->config = $config;
    }

    /**
     * @return string
     */
    public function getName()
    {
        return 'Environment Configuration';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::ENVIRONMENT;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $environment = (string) $this->config->get('app.env');
        $facts = [$this->environmentFact($environment)];
        $facts[] = $this->debugFact((bool) $this->config->get('app.debug'), $environment);
        $facts[] = $this->keyFact($this->config->get('app.key'));
        $facts[] = $this->urlFact($this->config->get('app.url'));

        return $this->aggregate($facts);
    }

    /**
     * @param string $environment
     * @return array
     */
    private function environmentFact($environment)
    {
        if ($environment === 'production') {
            return ['status' => CheckStatus::PASS, 'message' => 'APP_ENV = production'];
        }

        return [
            'status' => CheckStatus::WARNING,
            'message' => "APP_ENV = {$environment} (not production)",
            'recommendation' => 'Set APP_ENV=production for the live deployment.',
        ];
    }

    /**
     * @param bool $debug
     * @param string $environment
     * @return array
     */
    private function debugFact($debug, $environment)
    {
        if (!$debug) {
            return ['status' => CheckStatus::PASS, 'message' => 'APP_DEBUG = false'];
        }

        if ($environment === 'production') {
            return [
                'status' => CheckStatus::CRITICAL,
                'message' => 'APP_DEBUG is enabled in production - stack traces and configuration may be exposed to visitors.',
                'recommendation' => 'Set APP_DEBUG=false in production.',
            ];
        }

        return [
            'status' => CheckStatus::WARNING,
            'message' => 'APP_DEBUG is enabled.',
            'recommendation' => 'Disable APP_DEBUG outside local development.',
        ];
    }

    /**
     * @param string|null $key
     * @return array
     */
    private function keyFact($key)
    {
        if (empty($key)) {
            return [
                'status' => CheckStatus::CRITICAL,
                'message' => 'APP_KEY is not configured.',
                'recommendation' => 'Run php artisan key:generate.',
            ];
        }

        return ['status' => CheckStatus::PASS, 'message' => 'APP_KEY configured'];
    }

    /**
     * @param string|null $url
     * @return array
     */
    private function urlFact($url)
    {
        if (empty($url)) {
            return [
                'status' => CheckStatus::WARNING,
                'message' => 'APP_URL is not configured.',
                'recommendation' => "Set APP_URL to the application's real public URL.",
            ];
        }

        $host = parse_url($url, PHP_URL_HOST);

        if ($host === 'localhost' || $host === '127.0.0.1') {
            return [
                'status' => CheckStatus::WARNING,
                'message' => "APP_URL is still set to a localhost placeholder ({$url}).",
                'recommendation' => "Set APP_URL to the application's real public URL.",
            ];
        }

        return ['status' => CheckStatus::PASS, 'message' => "APP_URL configured ({$url})"];
    }
}
