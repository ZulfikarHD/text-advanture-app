<?php

namespace Tests\Feature\Database;

use App\Enums\LlmRole;
use App\Enums\ModelScope;
use App\Models\ModelProfile;
use App\Models\Story;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Structural tests for the global shared libraries (S-4.1.2).
 *
 * Asserts the five app-wide library tables exist and - the load-bearing
 * invariant - that they carry no `story_id` (they are shared across stories),
 * with the sole exception of `model_profiles.story_id` which is nullable so a
 * story can override a global default.
 */
class GlobalLibrariesSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_all_global_library_tables_exist(): void
    {
        $tables = [
            'register_archetypes',
            'universal_priors',
            'character_archetypes',
            'prompt_blocks',
            'model_profiles',
        ];

        foreach ($tables as $table) {
            $this->assertTrue(Schema::hasTable($table), "Missing global library table: {$table}");
        }
    }

    public function test_pure_library_tables_carry_no_story_scope(): void
    {
        foreach (['register_archetypes', 'universal_priors', 'character_archetypes', 'prompt_blocks'] as $table) {
            $this->assertFalse(
                Schema::hasColumn($table, 'story_id'),
                "Library table {$table} must be app-wide (no story_id)."
            );
        }
    }

    public function test_model_profiles_has_a_nullable_story_override(): void
    {
        $this->assertTrue(Schema::hasColumn('model_profiles', 'story_id'));

        $profile = ModelProfile::factory()->create([
            'scope' => ModelScope::Global,
            'story_id' => null,
        ]);

        $this->assertNull($profile->story_id);
    }

    public function test_story_scoped_model_profiles_are_unique_per_role(): void
    {
        // The DB unique index enforces story-scoped overrides; global rows
        // (null story_id) are kept singular at the application layer because
        // MariaDB treats NULLs in a unique index as distinct.
        $story = Story::factory()->create();

        ModelProfile::factory()->create([
            'scope' => ModelScope::Story,
            'story_id' => $story->id,
            'role' => LlmRole::NarratorProse,
        ]);

        $this->expectException(QueryException::class);

        ModelProfile::factory()->create([
            'scope' => ModelScope::Story,
            'story_id' => $story->id,
            'role' => LlmRole::NarratorProse,
        ]);
    }
}
