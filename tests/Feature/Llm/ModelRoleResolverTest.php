<?php

namespace Tests\Feature\Llm;

use App\Enums\LlmRole;
use App\Enums\ModelScope;
use App\Exceptions\Llm\UnresolvedModelRoleException;
use App\Models\ModelProfile;
use App\Models\Story;
use App\Models\User;
use App\Services\Llm\ModelRoleResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Tests for role -> model resolution (S-5.2.2, ADR 0017 §2).
 *
 * Resolution prefers a per-story override over the global default, ignores
 * inactive profiles, and fails closed when a role is unmapped.
 */
class ModelRoleResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_per_story_override_beats_the_global_default(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $story = Story::factory()->create(['user_id' => $user->id]);

        ModelProfile::factory()->create([
            'scope' => ModelScope::Global,
            'story_id' => null,
            'role' => LlmRole::NarratorProse,
            'model_slug' => 'global/model',
        ]);
        ModelProfile::factory()->create([
            'scope' => ModelScope::Story,
            'story_id' => $story->id,
            'role' => LlmRole::NarratorProse,
            'model_slug' => 'story/model',
        ]);

        $resolver = app(ModelRoleResolver::class);

        $this->assertSame('story/model', $resolver->resolve(LlmRole::NarratorProse, $story)->model_slug);
        $this->assertSame('global/model', $resolver->resolve(LlmRole::NarratorProse)->model_slug);
    }

    public function test_an_unmapped_role_throws(): void
    {
        $this->expectException(UnresolvedModelRoleException::class);

        app(ModelRoleResolver::class)->resolve(LlmRole::Compiler);
    }

    public function test_an_inactive_profile_is_not_resolved(): void
    {
        ModelProfile::factory()->create([
            'scope' => ModelScope::Global,
            'role' => LlmRole::Appraiser,
            'is_active' => false,
        ]);

        $this->expectException(UnresolvedModelRoleException::class);

        app(ModelRoleResolver::class)->resolve(LlmRole::Appraiser);
    }
}
