<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Reversibility test for the authoring-realm migrations (S-4.1.1 DoD:
 * "both realms migrate and roll back cleanly").
 *
 * Deliberately does NOT use RefreshDatabase: it drives the migrator directly
 * (fresh -> rollback -> migrate) so the `down()` methods are exercised in
 * dependency order and the schema is left intact for the rest of the suite.
 */
class AuthoringRealmMigrationTest extends TestCase
{
    public function test_authoring_realm_migrations_roll_back_and_re_run_cleanly(): void
    {
        $this->artisan('migrate:fresh')->assertExitCode(0);

        // Rolling back the batch must drop children before parents without
        // tripping a foreign-key constraint.
        $this->artisan('migrate:rollback')->assertExitCode(0);
        $this->assertFalse(Schema::hasTable('stories'));
        $this->assertFalse(Schema::hasTable('character_cards'));

        // Re-running restores the schema for subsequent tests.
        $this->artisan('migrate')->assertExitCode(0);
        $this->assertTrue(Schema::hasTable('stories'));
        $this->assertTrue(Schema::hasTable('character_cards'));
    }
}
