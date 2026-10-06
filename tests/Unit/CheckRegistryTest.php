<?php

namespace Anivesh\LaravelProductionGuard\Tests\Unit;

use Anivesh\LaravelProductionGuard\Enums\CheckStatus;
use Anivesh\LaravelProductionGuard\Services\CheckRegistry;
use Anivesh\LaravelProductionGuard\Services\UnresolvedCheck;
use Anivesh\LaravelProductionGuard\Tests\Fixtures\StubCheck;
use Anivesh\LaravelProductionGuard\Tests\Fixtures\UnresolvableCheck;
use Anivesh\LaravelProductionGuard\Tests\TestCase;
use InvalidArgumentException;

class CheckRegistryTest extends TestCase
{
    public function test_it_resolves_a_registered_class_string_through_the_container()
    {
        $registry = new CheckRegistry($this->app);
        $registry->register(StubCheck::class);

        $resolved = $registry->resolve();

        $this->assertCount(1, $resolved);
        $this->assertInstanceOf(StubCheck::class, $resolved[0]);
    }

    public function test_it_accepts_an_already_built_instance()
    {
        $registry = new CheckRegistry($this->app);
        $registry->register(new StubCheck());

        $this->assertCount(1, $registry->resolve());
    }

    public function test_it_rejects_an_invalid_registration()
    {
        $registry = new CheckRegistry($this->app);

        $this->expectException(InvalidArgumentException::class);

        $registry->register(42);
    }

    public function test_it_filters_by_category()
    {
        StubCheck::$category = 'security';

        $registry = new CheckRegistry($this->app);
        $registry->register(StubCheck::class);

        $this->assertCount(1, $registry->resolve(['security']));
        $this->assertCount(0, $registry->resolve(['database']));
        $this->assertCount(1, $registry->resolve([]));

        StubCheck::$category = 'environment';
    }

    public function test_register_many_registers_every_entry()
    {
        $registry = new CheckRegistry($this->app);
        $registry->registerMany([StubCheck::class, new StubCheck()]);

        $this->assertCount(2, $registry->resolve());
    }

    public function test_a_check_the_container_cannot_construct_becomes_an_unresolved_check_instead_of_throwing()
    {
        $registry = new CheckRegistry($this->app);
        $registry->register(UnresolvableCheck::class);

        $resolved = $registry->resolve();

        $this->assertCount(1, $resolved);
        $this->assertInstanceOf(UnresolvedCheck::class, $resolved[0]);

        $result = $resolved[0]->check();
        $this->assertSame(CheckStatus::UNKNOWN, $result->getStatus());
    }

    public function test_an_unresolved_check_is_never_hidden_by_a_category_filter()
    {
        $registry = new CheckRegistry($this->app);
        $registry->register(UnresolvableCheck::class);

        $resolved = $registry->resolve(['database']);

        $this->assertCount(1, $resolved);
        $this->assertInstanceOf(UnresolvedCheck::class, $resolved[0]);
    }
}
