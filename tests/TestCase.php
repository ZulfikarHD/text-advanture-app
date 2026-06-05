<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use Laravel\Fortify\Features;
use RuntimeException;

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

        $this->guardTestDatabase();

        $this->app['migrator']->path(base_path('tests/Fixtures/migrations'));
    }

    /**
     * Fail closed unless the resolved database is a test database.
     *
     * `RefreshDatabase` wipes whatever connection it runs against. This runs
     * before any migration (refreshApplication() precedes the trait boot), so a
     * misconfigured `DB_DATABASE=novel_engine` aborts the whole suite instead of
     * destroying the dev/prod database. In-memory SQLite is also allowed for
     * ad-hoc runs; everything else must contain "test".
     */
    protected function guardTestDatabase(): void
    {
        $database = (string) DB::connection()->getDatabaseName();

        if ($database === ':memory:' || str_contains(strtolower($database), 'test')) {
            return;
        }

        throw new RuntimeException(
            "Refusing to run tests against database [{$database}]: it is not a test database. ".
            'Tests reset the schema and would destroy this data. Configure DB_DATABASE to a name containing "test".'
        );
    }

    protected function skipUnlessFortifyHas(string $feature, ?string $message = null): void
    {
        if (! Features::enabled($feature)) {
            $this->markTestSkipped($message ?? "Fortify feature [{$feature}] is not enabled.");
        }
    }
}
