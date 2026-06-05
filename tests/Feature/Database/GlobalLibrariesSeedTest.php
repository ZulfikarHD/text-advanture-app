<?php

namespace Tests\Feature\Database;

use App\Enums\LlmRole;
use App\Enums\ModelScope;
use App\Models\CharacterArchetype;
use App\Models\ModelProfile;
use App\Models\PromptBlock;
use App\Models\RegisterArchetype;
use App\Models\UniversalPrior;
use Database\Seeders\GlobalLibrarySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Seed-content tests for the five global libraries (S-6.1.1 - S-6.1.4).
 *
 * Asserts the seeders produce the ADR-pinned rows, are idempotent on re-run, and
 * honour the load-bearing invariants: priors carry their ADR axis directions,
 * register dimensions stay within the canonical vocabulary, prompt-block
 * leak_rules name only existing guards, and every engine role has a global
 * model profile so the resolver can never miss.
 */
class GlobalLibrariesSeedTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The fixed canonical register dimension vocabulary (ADR 0006).
     *
     * @var list<string>
     */
    private const CANONICAL_DIMENSIONS = [
        'disclosure', 'proximity', 'flow', 'deflection', 'sincerity',
        'composure', 'reads_target', 'tells', 'speech',
    ];

    /**
     * The only leak guards a prompt block may name (ADR 0020).
     *
     * @var list<string>
     */
    private const ALLOWED_LEAK_GUARDS = [
        'awareness_fold', 'knowledge_boundary', 'hedged_attribution',
        'own_perspective_only', 'omniscient_authoring', 'none',
    ];

    public function test_seeder_creates_the_expected_library_rows(): void
    {
        $this->seed(GlobalLibrarySeeder::class);

        $this->assertSame(4, UniversalPrior::count());
        $this->assertSame(4, RegisterArchetype::count());
        $this->assertSame(1, CharacterArchetype::count());
        $this->assertSame(16, PromptBlock::count());
        $this->assertSame(8, ModelProfile::count());
    }

    public function test_seeder_is_idempotent_on_rerun(): void
    {
        $this->seed(GlobalLibrarySeeder::class);
        $this->seed(GlobalLibrarySeeder::class);

        $this->assertSame(4, UniversalPrior::count());
        $this->assertSame(16, PromptBlock::count());
        $this->assertSame(8, ModelProfile::count());
    }

    public function test_universal_priors_carry_their_adr_axis_directions(): void
    {
        $this->seed(GlobalLibrarySeeder::class);

        $this->assertSame(['affection' => 'down'], UniversalPrior::where('slug', 'insult')->value('axes'));
        $this->assertSame(['affection' => 'up'], UniversalPrior::where('slug', 'kindness')->value('axes'));
        $this->assertSame(['fear' => 'up'], UniversalPrior::where('slug', 'threat')->value('axes'));
        $this->assertSame(['trust' => 'down'], UniversalPrior::where('slug', 'broken_promise')->value('axes'));
    }

    public function test_register_archetype_dimensions_use_only_canonical_dimensions(): void
    {
        $this->seed(GlobalLibrarySeeder::class);

        foreach (RegisterArchetype::all() as $archetype) {
            $unknown = array_diff(array_keys($archetype->dimensions), self::CANONICAL_DIMENSIONS);

            $this->assertEmpty(
                $unknown,
                "Register archetype [{$archetype->slug}] uses non-canonical dimensions: ".implode(', ', $unknown),
            );
        }
    }

    public function test_koakuma_character_archetype_is_seeded_from_its_adr(): void
    {
        $this->seed(GlobalLibrarySeeder::class);

        $koakuma = CharacterArchetype::where('slug', 'koakuma')->firstOrFail();

        $this->assertSame(['affection', 'trust', 'romantic', 'fear'], $koakuma->suggested_live_axes);
        $this->assertContains('one_way_mirror', array_column($koakuma->default_registers, 'archetype'));
    }

    public function test_prompt_block_leak_rules_use_only_known_guards(): void
    {
        $this->seed(GlobalLibrarySeeder::class);

        foreach (PromptBlock::all() as $block) {
            $unknown = array_diff($block->leak_rules, self::ALLOWED_LEAK_GUARDS);

            $this->assertEmpty(
                $unknown,
                "Prompt block [{$block->key}] names unknown leak guards: ".implode(', ', $unknown),
            );
        }
    }

    public function test_npc_lorebook_is_knowledge_bounded_but_narrator_lorebook_is_not(): void
    {
        $this->seed(GlobalLibrarySeeder::class);

        $this->assertContains('knowledge_boundary', PromptBlock::where('key', 'LOREBOOK')->value('leak_rules'));
        $this->assertSame(['none'], PromptBlock::where('key', 'LOREBOOK_NARRATOR')->value('leak_rules'));
    }

    public function test_every_engine_role_has_a_global_model_profile(): void
    {
        $this->seed(GlobalLibrarySeeder::class);

        foreach (LlmRole::cases() as $role) {
            $this->assertTrue(
                ModelProfile::where('scope', ModelScope::Global)
                    ->whereNull('story_id')
                    ->where('role', $role)
                    ->exists(),
                "Missing global model profile for role [{$role->value}].",
            );
        }
    }
}
