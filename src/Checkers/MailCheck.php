<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Contracts\Config\Repository as Config;

class MailCheck extends AbstractCheck
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
        return 'Mail';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::MAIL;
    }

    /**
     * Never sends a real email (see --test-mail, a possible future
     * explicit opt-in) and never reports the actual value of any
     * credential - only whether one is configured.
     *
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $driver = $this->mailer();
        $facts = [$this->driverFact($driver)];

        if ($driver === 'smtp') {
            $facts[] = $this->hostAndPortFact();
            $facts[] = $this->encryptionFact();
            $facts[] = $this->usernameFact();
        }

        return $this->aggregate($facts);
    }

    /**
     * Laravel 9+ renamed mail.driver to mail.default/mail.mailers.*.transport.
     *
     * @return string|null
     */
    private function mailer()
    {
        return $this->config->get('mail.default') ?: $this->config->get('mail.driver');
    }

    /**
     * @param string|null $driver
     * @return array
     */
    private function driverFact($driver)
    {
        if (empty($driver)) {
            return ['status' => CheckStatus::WARNING, 'message' => 'No mail driver is configured.'];
        }

        return ['status' => CheckStatus::PASS, 'message' => "Mail driver: {$driver}"];
    }

    /**
     * @return array
     */
    private function hostAndPortFact()
    {
        $host = $this->config->get('mail.host') ?: $this->config->get('mail.mailers.smtp.host');
        $port = $this->config->get('mail.port') ?: $this->config->get('mail.mailers.smtp.port');

        if (empty($host) || empty($port)) {
            return ['status' => CheckStatus::WARNING, 'message' => 'SMTP host/port is not fully configured.'];
        }

        return ['status' => CheckStatus::PASS, 'message' => "SMTP host/port configured ({$host}:{$port})."];
    }

    /**
     * @return array
     */
    private function encryptionFact()
    {
        $encryption = $this->config->get('mail.encryption') ?: $this->config->get('mail.mailers.smtp.encryption');

        if (empty($encryption)) {
            return [
                'status' => CheckStatus::WARNING,
                'message' => 'No encryption is configured for SMTP mail.',
                'recommendation' => 'Configure TLS/SSL encryption for SMTP in production.',
            ];
        }

        return ['status' => CheckStatus::PASS, 'message' => "Mail encryption: {$encryption}"];
    }

    /**
     * @return array
     */
    private function usernameFact()
    {
        $username = $this->config->get('mail.username') ?: $this->config->get('mail.mailers.smtp.username');

        if (empty($username)) {
            return ['status' => CheckStatus::WARNING, 'message' => 'SMTP username is not configured.'];
        }

        return ['status' => CheckStatus::PASS, 'message' => 'SMTP username is configured.'];
    }
}
