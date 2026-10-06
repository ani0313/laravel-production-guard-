<?php

namespace Anivesh\LaravelProductionGuard\Checkers;

use Anivesh\LaravelProductionGuard\Enums\CheckCategory;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;

class PhpCheck extends AbstractCheck
{
    /**
     * Needed by virtually every Laravel application - missing one of
     * these is a hard failure, not a maybe.
     *
     * @var string[]
     */
    const REQUIRED_EXTENSIONS = ['pdo', 'openssl', 'mbstring', 'tokenizer', 'xml', 'ctype', 'json', 'fileinfo'];

    /**
     * Commonly needed but genuinely feature-dependent (e.g. bcmath for
     * precise math, intl for localization, gd/imagick for images) - a
     * generic package cannot know whether a given app actually needs them,
     * so a missing one is a warning, never a critical.
     *
     * @var string[]
     */
    const COMMON_EXTENSIONS = ['bcmath', 'intl', 'gd', 'imagick'];

    /**
     * @return string
     */
    public function getName()
    {
        return 'PHP Runtime';
    }

    /**
     * @return string
     */
    public function getCategory()
    {
        return CheckCategory::PHP;
    }

    /**
     * @return \Anivesh\LaravelProductionGuard\DTO\CheckResult
     */
    public function check()
    {
        $facts = [];
        $facts[] = ['status' => CheckStatus::PASS, 'message' => 'PHP ' . PHP_VERSION];

        foreach (self::REQUIRED_EXTENSIONS as $extension) {
            $facts[] = $this->extensionFact($extension, CheckStatus::CRITICAL);
        }

        foreach (self::COMMON_EXTENSIONS as $extension) {
            $facts[] = $this->extensionFact($extension, CheckStatus::WARNING);
        }

        foreach (['memory_limit', 'max_execution_time', 'upload_max_filesize', 'post_max_size'] as $directive) {
            $facts[] = $this->iniFact($directive);
        }

        return $this->aggregate($facts);
    }

    /**
     * @param string $extension
     * @param string $statusWhenMissing
     * @return array
     */
    private function extensionFact($extension, $statusWhenMissing)
    {
        if (extension_loaded($extension)) {
            return ['status' => CheckStatus::PASS, 'message' => "ext-{$extension} is loaded."];
        }

        return [
            'status' => $statusWhenMissing,
            'message' => "ext-{$extension} is not loaded.",
            'recommendation' => "Install/enable the PHP {$extension} extension.",
        ];
    }

    /**
     * Reports the current value only - what's "right" for memory_limit,
     * max_execution_time etc. varies per application, so no threshold is
     * assumed here.
     *
     * @param string $directive
     * @return array
     */
    private function iniFact($directive)
    {
        $value = ini_get($directive);

        if ($value === false) {
            return ['status' => CheckStatus::WARNING, 'message' => "Could not read the {$directive} ini setting."];
        }

        return ['status' => CheckStatus::PASS, 'message' => "{$directive} = {$value}"];
    }
}
