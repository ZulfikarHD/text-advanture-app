<?php

namespace Tests\Feature\Narrator;

use App\Enums\LlmCallStatus;
use App\Enums\LlmRole;
use App\Enums\StateNode;
use App\Models\Beat;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\CharacterCard;
use App\Models\PlaySession;
use App\Models\Scene;
use App\Models\Story;
use App\Models\User;
use App\Services\ProviderCredentialService;
use Database\Seeders\ModelProfileSeeder;
use Database\Seeders\PromptBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use Tests\TestCase;

/**
 * Feature tests for the narrator prose call — the handoff producer (S-4.2.1 /
 * S-4.2.2, ADR 0016 §4).
 *
 * Covers: a validated structured payload advances the loop spine by its handoff
 * (player_moment / beat_complete) and logs an Ok call; the opening turn enters
 * the loop before routing; and the S-4.2.2 safety guarantee — malformed output
 * (here an out-of-vocabulary handoff) is retried to the bound, recorded as a
 * failed call, surfaced to the player as an error toast, and never advances the
 * loop. Narrating off-turn is rejected, and the endpoint is owner-scoped + auth
 * gated like every other save write.
 */
class NarratorTurnTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = 'sk-or-v1-narrator-turn-0001';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PromptBlockSeeder::class);
        $this->seed(ModelProfileSeeder::class);
        Http::preventStrayRequests();
    }

    public function test_a_valid_prose_call_advances_the_loop_to_player_moment(): void
    {
        $this->fakeProseCall('player_moment');
        [$user, $story, $save] = $this->narratableSession();
        $this->actAsOwnerWithKey($user);

        $this->post(route('stories.saves.narrate', [$story, $save]));

        $this->assertSame(StateNode::PlayerMoment, $save->fresh()->state_node);
    }

    public function test_a_beat_complete_handoff_advances_the_loop_to_beat_complete(): void
    {
        $this->fakeProseCall('beat_complete');
        [$user, $story, $save] = $this->narratableSession();
        $this->actAsOwnerWithKey($user);

        $this->post(route('stories.saves.narrate', [$story, $save]));

        $this->assertSame(StateNode::BeatComplete, $save->fresh()->state_node);
    }

    public function test_the_opening_turn_enters_the_loop_before_routing(): void
    {
        $this->fakeProseCall('player_moment');
        [$user, $story, $save] = $this->narratableSession(StateNode::SessionStart);
        $this->actAsOwnerWithKey($user);

        $this->post(route('stories.saves.narrate', [$story, $save]));

        $this->assertSame(StateNode::PlayerMoment, $save->fresh()->state_node);
    }

    public function test_a_valid_prose_call_logs_an_ok_call_for_the_save(): void
    {
        $this->fakeProseCall('player_moment');
        [$user, $story, $save] = $this->narratableSession();
        $this->actAsOwnerWithKey($user);

        $this->post(route('stories.saves.narrate', [$story, $save]));

        $this->assertDatabaseHas('llm_calls', [
            'session_id' => $save->id,
            'role' => LlmRole::NarratorProse->value,
            'status' => LlmCallStatus::Ok->value,
        ]);
    }

    public function test_a_valid_prose_call_surfaces_a_success_toast(): void
    {
        $this->fakeProseCall('player_moment');
        [$user, $story, $save] = $this->narratableSession();
        $this->actAsOwnerWithKey($user);

        $response = $this->post(route('stories.saves.narrate', [$story, $save]));

        $response->assertInertiaFlash('toast.type', 'success');
    }

    public function test_a_valid_prose_call_records_a_narration_event(): void
    {
        $this->fakeProseCall('player_moment');
        [$user, $story, $save] = $this->narratableSession();
        $this->actAsOwnerWithKey($user);

        $this->post(route('stories.saves.narrate', [$story, $save]));

        $this->assertDatabaseHas('events', [
            'session_id' => $save->id,
            'beat_id' => $save->current_beat_id,
            'type' => 'narration',
            'content' => 'The classroom hums with tension as Luna looks up from her gloves.',
        ]);
    }

    public function test_malformed_output_is_retried_then_recorded_as_failed(): void
    {
        // Two attempts (1 retry); fake sleep so backoff is instant.
        config(['services.openrouter.max_retries' => 1]);
        Sleep::fake();
        $this->fakeProseCall('npc_moment');
        [$user, $story, $save] = $this->narratableSession();
        $this->actAsOwnerWithKey($user);

        $this->post(route('stories.saves.narrate', [$story, $save]));

        Http::assertSentCount(2);
        $this->assertDatabaseHas('llm_calls', [
            'session_id' => $save->id,
            'role' => LlmRole::NarratorProse->value,
            'status' => LlmCallStatus::Failed->value,
        ]);
        $this->assertDatabaseMissing('llm_calls', ['status' => LlmCallStatus::Ok->value]);
    }

    public function test_malformed_output_does_not_advance_the_loop(): void
    {
        config(['services.openrouter.max_retries' => 1]);
        Sleep::fake();
        $this->fakeProseCall('npc_moment');
        [$user, $story, $save] = $this->narratableSession();
        $this->actAsOwnerWithKey($user);

        $this->post(route('stories.saves.narrate', [$story, $save]));

        $this->assertSame(StateNode::NarratorTurn, $save->fresh()->state_node);
    }

    public function test_malformed_output_surfaces_an_error_toast_to_the_player(): void
    {
        config(['services.openrouter.max_retries' => 1]);
        Sleep::fake();
        $this->fakeProseCall('npc_moment');
        [$user, $story, $save] = $this->narratableSession();
        $this->actAsOwnerWithKey($user);

        $response = $this->post(route('stories.saves.narrate', [$story, $save]));

        $response->assertInertiaFlash('toast.type', 'error');
    }

    public function test_narrating_off_turn_is_rejected_without_a_call(): void
    {
        [$user, $story, $save] = $this->narratableSession(StateNode::PlayerMoment);

        $this->actingAs($user)->post(route('stories.saves.narrate', [$story, $save]));

        $this->assertSame(StateNode::PlayerMoment, $save->fresh()->state_node);
        $this->assertDatabaseCount('llm_calls', 0);
    }

    public function test_a_foreign_story_save_cannot_be_narrated(): void
    {
        $owner = User::factory()->create();
        $intruder = User::factory()->create();
        [, $story, $save] = $this->narratableSession(owner: $owner);

        $response = $this->actingAs($intruder)->post(route('stories.saves.narrate', [$story, $save]));

        $response->assertNotFound();
        $this->assertSame(StateNode::NarratorTurn, $save->fresh()->state_node);
    }

    public function test_guests_cannot_narrate(): void
    {
        [, $story, $save] = $this->narratableSession();

        $response = $this->post(route('stories.saves.narrate', [$story, $save]));

        $response->assertRedirect(route('login'));
    }

    /**
     * Authenticate as the owner and store their provider key.
     *
     * The key is stamped from the authenticated user, so storing it requires the
     * acting context the play request runs under.
     *
     * @param  User  $user  The save's owner.
     */
    private function actAsOwnerWithKey(User $user): void
    {
        $this->actingAs($user);
        (new ProviderCredentialService)->store($user, self::KEY);
    }

    /**
     * Fake the OpenRouter prose call with a structured payload for a handoff.
     *
     * @param  string  $handoff  The handoff signal to embed in the JSON content.
     */
    private function fakeProseCall(string $handoff): void
    {
        Http::fake([
            '*chat/completions' => Http::response([
                'id' => 'gen-narrator-turn',
                'choices' => [['message' => ['content' => json_encode([
                    'prose' => 'The classroom hums with tension as Luna looks up from her gloves.',
                    'handoff' => $handoff,
                    'elapsed_bucket' => 'continuous',
                ])]]],
                'usage' => ['prompt_tokens' => 60, 'completion_tokens' => 40, 'cost' => 0.0003],
            ]),
        ]);
    }

    /**
     * Build an owner with a provider key and a save positioned for a narrator turn.
     *
     * @param  StateNode  $state  The loop node the save starts on.
     * @param  User|null  $owner  The owner to use; created when omitted.
     * @return array{0: User, 1: Story, 2: PlaySession}
     */
    private function narratableSession(StateNode $state = StateNode::NarratorTurn, ?User $owner = null): array
    {
        $user = $owner ?? User::factory()->create();

        $story = Story::factory()->create(['user_id' => $user->id]);
        $chapter = Chapter::factory()->create([
            'story_id' => $story->id,
            'number' => 1,
            'pov_default' => 'third_limited',
        ]);
        $scene = Scene::factory()->create([
            'chapter_id' => $chapter->id,
            'number' => 1,
            'pov_mode' => 'third_limited',
            'pov_anchor' => 'luna',
            'tone' => 'tense',
            'present_characters' => ['luna', 'player'],
        ]);
        $beat = Beat::factory()->create([
            'scene_id' => $scene->id,
            'number' => 1,
            'goal' => 'Luna and the player meet',
        ]);

        $luna = Character::factory()->create(['story_id' => $story->id, 'slug' => 'luna', 'name' => 'Luna']);
        CharacterCard::factory()->create([
            'character_id' => $luna->id,
            'chapter_id' => $chapter->id,
            'appearance' => 'small, sharp-eyed, fidgets with gloves',
        ]);

        $player = Character::factory()->player()->create(['story_id' => $story->id, 'slug' => 'player', 'name' => 'You']);
        CharacterCard::factory()->create(['character_id' => $player->id, 'chapter_id' => $chapter->id, 'appearance' => null]);

        $save = $story->playSessions()->create([
            'name' => 'Playthrough 1',
            'state_node' => $state,
            'current_chapter_id' => $chapter->id,
            'current_scene_id' => $scene->id,
            'current_beat_id' => $beat->id,
            'last_played_at' => now(),
        ]);

        return [$user, $story, $save];
    }
}
