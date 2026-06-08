<?php

namespace Tests\Feature\Narrator;

use App\Enums\BlockSection;
use App\Enums\StateNode;
use App\Models\Beat;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\CharacterCard;
use App\Models\LorebookEntry;
use App\Models\PlaySession;
use App\Models\PromptBlock;
use App\Models\Scene;
use App\Models\Story;
use App\Models\User;
use App\Services\Narrator\AssembledPrompt;
use App\Services\Narrator\NarratorPromptAssembler;
use Database\Seeders\PromptBlockSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for narrator prompt assembly — the registry-driven builder (S-4.1.1).
 *
 * Covers: the lit narrator blocks are selected and ordered from the seeded
 * `prompt_blocks` registry; deferred blocks with no producer (MESH_AWARENESS,
 * DIRECTOR_STATE) are absent with no filler; selection/order come from
 * order_index/section/is_active rows (not code constants); RESUME_ANCHOR is
 * injected only when resuming; the narrator LOREBOOK folds keyword-matched
 * facts (omniscient, but reveal-gated); and the prompt renders into split
 * system/user chat messages that always close with the continue instruction.
 */
class NarratorPromptAssemblyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(PromptBlockSeeder::class);
    }

    public function test_it_assembles_the_lit_narrator_blocks_in_registry_order(): void
    {
        $session = $this->positionedSession();
        $this->matchingLorebookEntry($session);

        $prompt = $this->assemble($session);

        $this->assertSame(
            ['POV_CONTRACT', 'BEAT', 'LOREBOOK_NARRATOR', 'SCENE_STATE'],
            $prompt->keys(),
        );
    }

    public function test_blocks_without_a_producer_are_absent_with_no_filler(): void
    {
        $session = $this->positionedSession();

        $keys = $this->assemble($session)->keys();

        $this->assertNotContains('MESH_AWARENESS', $keys);
        $this->assertNotContains('DIRECTOR_STATE', $keys);
    }

    public function test_block_order_is_read_from_the_registry_not_code(): void
    {
        $session = $this->positionedSession();
        // Re-order BEAT ahead of POV_CONTRACT purely via the registry row.
        PromptBlock::query()->where('key', 'BEAT')->update(['order_index' => 0]);

        $keys = $this->assemble($session)->keys();

        $this->assertSame('BEAT', $keys[0]);
    }

    public function test_deactivating_a_lit_block_removes_it_without_code_changes(): void
    {
        $session = $this->positionedSession();
        PromptBlock::query()->where('key', 'POV_CONTRACT')->update(['is_active' => false]);

        $keys = $this->assemble($session)->keys();

        $this->assertNotContains('POV_CONTRACT', $keys);
    }

    public function test_resume_anchor_is_absent_when_not_resuming(): void
    {
        $session = $this->positionedSession();

        $prompt = $this->assemble($session);

        $this->assertNull($prompt->block('RESUME_ANCHOR'));
    }

    public function test_resume_anchor_is_injected_when_resuming(): void
    {
        $session = $this->positionedSession([
            'resume_anchor' => [
                'scene_type' => 'dialogue',
                'last_line' => 'She did not look up from the gloves.',
                'pov' => 'third_limited',
                'tone' => 'tense',
            ],
        ]);

        $block = $this->assemble($session)->block('RESUME_ANCHOR');

        $this->assertNotNull($block);
        $this->assertSame(BlockSection::User, $block->section);
    }

    public function test_lorebook_is_omitted_when_no_keyword_matches(): void
    {
        $session = $this->positionedSession();
        LorebookEntry::factory()->create([
            'story_id' => $session->story_id,
            'keywords' => ['a faraway citadel nobody mentions'],
            'content' => 'Unrelated world fact.',
        ]);

        $prompt = $this->assemble($session);

        $this->assertNull($prompt->block('LOREBOOK_NARRATOR'));
    }

    public function test_lorebook_folds_keyword_matched_facts(): void
    {
        $session = $this->positionedSession();
        $this->matchingLorebookEntry($session, 'The gloves suppress her resonance.');

        $block = $this->assemble($session)->block('LOREBOOK_NARRATOR');

        $this->assertNotNull($block);
        $this->assertStringContainsString('The gloves suppress her resonance.', $block->body);
    }

    public function test_lorebook_withholds_a_fact_before_its_reveal_chapter(): void
    {
        $session = $this->positionedSession();
        $laterChapter = Chapter::factory()->create([
            'story_id' => $session->story_id,
            'number' => 2,
        ]);
        LorebookEntry::factory()->create([
            'story_id' => $session->story_id,
            'keywords' => ['Luna'],
            'content' => 'A spoiler that unlocks in chapter 2.',
            'min_reveal_chapter_id' => $laterChapter->id,
        ]);

        $prompt = $this->assemble($session);

        $this->assertNull($prompt->block('LOREBOOK_NARRATOR'));
    }

    public function test_pov_contract_carries_mode_anchor_and_tone(): void
    {
        $session = $this->positionedSession();

        $block = $this->assemble($session)->block('POV_CONTRACT');

        $this->assertNotNull($block);
        $this->assertStringContainsString('Third person limited', $block->body);
        $this->assertStringContainsString('Luna', $block->body);
        $this->assertStringContainsString('tense', $block->body);
    }

    public function test_beat_carries_its_goal(): void
    {
        $session = $this->positionedSession();

        $block = $this->assemble($session)->block('BEAT');

        $this->assertNotNull($block);
        $this->assertSame('Goal: Luna and the player meet', $block->body);
    }

    public function test_scene_state_lists_present_characters_with_appearance(): void
    {
        $session = $this->positionedSession();

        $block = $this->assemble($session)->block('SCENE_STATE');

        $this->assertNotNull($block);
        $this->assertStringContainsString('Luna (small, sharp-eyed, fidgets with gloves)', $block->body);
    }

    public function test_messages_split_system_and_user_sections(): void
    {
        $session = $this->positionedSession([
            'resume_anchor' => ['scene_type' => 'dialogue', 'tone' => 'tense'],
        ]);

        $messages = $this->assemble($session)->messages();

        $this->assertSame('system', $messages[0]['role']);
        $this->assertSame('user', $messages[1]['role']);
        $this->assertStringContainsString('[RESUME ANCHOR]', $messages[1]['content']);
    }

    public function test_user_message_always_closes_with_the_continue_instruction(): void
    {
        $session = $this->positionedSession();

        $messages = $this->assemble($session)->messages();

        $this->assertStringEndsWith(
            NarratorPromptAssembler::CONTINUE_INSTRUCTION,
            $messages[array_key_last($messages)]['content'],
        );
    }

    /**
     * Resolve the assembler from the container and assemble for a save.
     */
    private function assemble(PlaySession $session): AssembledPrompt
    {
        return app(NarratorPromptAssembler::class)->assemble($session);
    }

    /**
     * Build a save positioned at a narrator turn with a minimal authored scene.
     *
     * @param  array<string, mixed>  $sessionOverrides  Extra columns for the save (e.g. resume_anchor).
     */
    private function positionedSession(array $sessionOverrides = []): PlaySession
    {
        $user = User::factory()->create();
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

        $luna = Character::factory()->create([
            'story_id' => $story->id,
            'slug' => 'luna',
            'name' => 'Luna',
        ]);
        CharacterCard::factory()->create([
            'character_id' => $luna->id,
            'chapter_id' => $chapter->id,
            'appearance' => 'small, sharp-eyed, fidgets with gloves',
        ]);

        $player = Character::factory()->player()->create([
            'story_id' => $story->id,
            'slug' => 'player',
            'name' => 'You',
        ]);
        CharacterCard::factory()->create([
            'character_id' => $player->id,
            'chapter_id' => $chapter->id,
            'appearance' => null,
        ]);

        return PlaySession::factory()->create([
            'story_id' => $story->id,
            'state_node' => StateNode::NarratorTurn,
            'current_chapter_id' => $chapter->id,
            'current_scene_id' => $scene->id,
            'current_beat_id' => $beat->id,
            ...$sessionOverrides,
        ]);
    }

    /**
     * Add a lorebook entry whose keyword appears in the scene sample text.
     */
    private function matchingLorebookEntry(PlaySession $session, string $content = 'Luna hides a resonance the school would punish.'): LorebookEntry
    {
        return LorebookEntry::factory()->create([
            'story_id' => $session->story_id,
            'title' => null,
            'keywords' => ['Luna'],
            'content' => $content,
            'min_reveal_chapter_id' => null,
        ]);
    }
}
