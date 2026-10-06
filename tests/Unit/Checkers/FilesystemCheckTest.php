<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit\Checkers;

use Anivesh\LaravelProductionGuard\Checkers\FilesystemCheck;
use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Illuminate\Config\Repository;
use PHPUnit\Framework\TestCase;

class FilesystemCheckTest extends TestCase
{
    public function test_no_disks_configured_is_unknown()
    {
        $result = (new FilesystemCheck(new Repository(['filesystems' => ['disks' => []]])))->check();

        $this->assertSame(CheckStatus::UNKNOWN, $result->getStatus());
    }

    public function test_a_writable_local_disk_passes()
    {
        $config = new Repository(['filesystems' => ['disks' => [
            'local' => ['driver' => 'local', 'root' => sys_get_temp_dir()],
        ]]]);

        $result = (new FilesystemCheck($config))->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
    }

    public function test_a_local_disk_with_a_missing_root_is_a_warning()
    {
        $config = new Repository(['filesystems' => ['disks' => [
            'broken' => ['driver' => 'local', 'root' => '/this/path/does/not/exist'],
        ]]]);

        $result = (new FilesystemCheck($config))->check();

        $this->assertSame(CheckStatus::WARNING, $result->getStatus());
    }

    public function test_s3_disk_warns_about_missing_configuration_without_connecting()
    {
        $config = new Repository(['filesystems' => ['disks' => [
            's3' => ['driver' => 's3'],
        ]]]);

        $result = (new FilesystemCheck($config))->check();

        $this->assertSame(CheckStatus::WARNING, $result->getStatus());
        $this->assertStringContainsLike('missing', $result->getDetails()[0]['message']);
    }

    public function test_s3_disk_passes_when_fully_configured()
    {
        $config = new Repository(['filesystems' => ['disks' => [
            's3' => ['driver' => 's3', 'key' => 'k', 'secret' => 's', 'bucket' => 'b', 'region' => 'us-east-1'],
        ]]]);

        $result = (new FilesystemCheck($config))->check();

        $this->assertSame(CheckStatus::PASS, $result->getStatus());
    }

    private function assertStringContainsLike($needle, $haystack)
    {
        $this->assertTrue(strpos($haystack, $needle) !== false, "Failed asserting that '{$haystack}' contains '{$needle}'.");
    }
}
