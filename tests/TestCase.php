<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Laravel\Fortify\Features;

abstract class TestCase extends BaseTestCase
{
    /**
     * Refresh the application and register test-only migration paths.
     *
     * The fixture migrations (e.g. the owned-resource model used to prove the
     * account-isolation invariants in OwnershipIsolationTest) are registered
     * before traits boot, so they participate in `migrate:fresh` regardless of
     * test order. They produce throwaway tables only in the test database.
     */
    protected function refreshApplication(): void
    {
        parent::refreshApplication();

        $this->app['migrator']->path(base_path('tests/Fixtures/migrations'));
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
