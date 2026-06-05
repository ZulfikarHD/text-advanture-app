<?php

namespace Tests\Feature\Database;

use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Reversibility test for the full two-realm schema (S-4.2.1 DoD:
 * "both realms migrate and roll back cleanly").
 *
 * Like {@see AuthoringRealmMigrationTest}, drives the migrator directly
 * (fresh -> rollback -> migrate) - NOT RefreshDatabase - so every save-realm,
 * global-library, deferred-FK, and provider `down()` runs in dependency order
 * without tripping a constraint, and leaves the schema intact for the suite.
 */
class SaveRealmMigrationTest extends TestCase
{
    public function test_full_schema_migrates_and_rolls_back_cleanly(): void
    {
        $this->artisan('migrate:fresh')->assertExitCode(0);

        $this->artisan('migrate:rollback')->assertExitCode(0);
        $this->assertFalse(Schema::hasTable('play_sessions'));
        $this->assertFalse(Schema::hasTable('llm_calls'));
        $this->assertFalse(Schema::hasTable('provider_credentials'));
        $this->assertFalse(Schema::hasTable('register_archetypes'));

        $this->artisan('migrate')->assertExitCode(0);
        $this->assertTrue(Schema::hasTable('play_sessions'));
        $this->assertTrue(Schema::hasTable('llm_calls'));
        $this->assertTrue(Schema::hasTable('provider_credentials'));
        $this->assertTrue(Schema::hasTable('register_archetypes'));
    }
}
