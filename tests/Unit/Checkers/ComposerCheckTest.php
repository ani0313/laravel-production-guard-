<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\ComposerCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Tests\TestCase;

class ComposerCheckTest extends TestCase
{
    /** @var string[] */
    private $writtenFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->writtenFiles as $file) {
            @unlink($file);
        }

        parent::tearDown();
    }

    private function writeJsonFile($relativePath, array $content)
    {
        $path = $this->app->basePath($relativePath);
        file_put_contents($path, json_encode($content));
        $this->writtenFiles[] = $path;
    }

    public function test_missing_composer_lock_is_a_warning()
    {
        @unlink($this->app->basePath('composer.lock'));

        $result = $this->app->make(ComposerCheck::class)->check();

        $lockFact = $result->getDetails()[1];
        $this->assertSame(CheckStatus::WARNING, $lockFact['status']);
    }

    public function test_present_lock_with_no_abandoned_packages_passes()
    {
        $this->writeJsonFile('composer.lock', [
            'packages' => [['name' => 'vendor/good-package']],
            'packages-dev' => [],
        ]);

        $result = $this->app->make(ComposerCheck::class)->check();

        $this->assertSame(CheckStatus::PASS, $result->getDetails()[1]['status']);
        $this->assertSame(CheckStatus::PASS, $result->getDetails()[2]['status']);
    }

    public function test_abandoned_packages_are_reported_as_a_warning_by_name()
    {
        $this->writeJsonFile('composer.lock', [
            'packages' => [
                ['name' => 'vendor/good-package'],
                ['name' => 'vendor/abandoned-package', 'abandoned' => true],
            ],
            'packages-dev' => [],
        ]);

        $result = $this->app->make(ComposerCheck::class)->check();

        $abandonedFact = $result->getDetails()[2];
        $this->assertSame(CheckStatus::WARNING, $abandonedFact['status']);
        $this->assertStringContainsLike('vendor/abandoned-package', $abandonedFact['message']);
    }

    public function test_the_real_present_vendor_directory_passes()
    {
        // Simulating a *missing* vendor/ would mean deleting this package's
        // own real vendor directory, which is too destructive for a test -
        // this instead documents the normal, realistic case.
        $result = $this->app->make(ComposerCheck::class)->check();

        $this->assertSame(CheckStatus::PASS, $result->getDetails()[0]['status']);
    }

    private function assertStringContainsLike($needle, $haystack)
    {
        $this->assertTrue(strpos($haystack, $needle) !== false, "Failed asserting that '{$haystack}' contains '{$needle}'.");
    }
}
