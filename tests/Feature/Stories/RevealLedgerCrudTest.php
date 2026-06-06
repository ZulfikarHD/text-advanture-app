<?php

namespace Tests\Feature\Stories;

use App\Models\Chapter;
use App\Models\Character;
use App\Models\RevealLedger;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for reveal-ledger entry CRUD (S-4.1.1, ADR 0013 §3).
 *
 * Covers: create (full + world-secret), who_knows storage + normalization,
 * required-field validation, the story-scoped reveal-chapter and "about"
 * character rules, owner-scoped listing, update, delete, scoped child binding
 * (cross-story 404s), and the auth gate.
 */
class RevealLedgerCrudTest extends TestCase
{
    use RefreshDatabase;

    // --- Create ---

    public function test_owner_can_create_an_entry_with_full_payload(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id]);
        $character = Character::factory()->create(['story_id' => $story->id]);

        $response = $this->actingAs($user)->post(route('stories.reveal-ledger.store', $story), [
            'fact' => 'the_diagnosis',
            'reveal_chapter_id' => $chapter->id,
            'character_id' => $character->id,
            'who_knows' => ['vixia-archi'],
            'notes' => 'The load-bearing secret of Saga 1.',
        ]);

        $response->assertRedirect(route('stories.reveal-ledger.index', $story));

        $this->assertDatabaseHas('reveal_ledger', [
            'story_id' => $story->id,
            'fact' => 'the_diagnosis',
            'character_id' => $character->id,
            'reveal_chapter_id' => $chapter->id,
        ]);
    }

    public function test_can_create_a_world_secret_without_a_character(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id]);

        $this->actingAs($user)->post(route('stories.reveal-ledger.store', $story), [
            'fact' => 'the_cure',
            'reveal_chapter_id' => $chapter->id,
        ]);

        $this->assertDatabaseHas('reveal_ledger', [
            'story_id' => $story->id,
            'fact' => 'the_cure',
            'character_id' => null,
        ]);
    }

    public function test_created_entry_stores_its_who_knows_array(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id]);

        $this->actingAs($user)->post(route('stories.reveal-ledger.store', $story), [
            'fact' => 'parents_died_searching',
            'reveal_chapter_id' => $chapter->id,
            'who_knows' => ['vixia-archi', 'luna-archi'],
        ]);

        $this->assertSame(['vixia-archi', 'luna-archi'], RevealLedger::firstOrFail()->who_knows);
    }

    public function test_who_knows_slugs_are_trimmed_and_deduplicated(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id]);

        $this->actingAs($user)->post(route('stories.reveal-ledger.store', $story), [
            'fact' => 'the_diagnosis',
            'reveal_chapter_id' => $chapter->id,
            'who_knows' => ['vixia-archi', ' vixia-archi ', 'luna-archi'],
        ]);

        $this->assertSame(['vixia-archi', 'luna-archi'], RevealLedger::firstOrFail()->who_knows);
    }

    public function test_entry_requires_a_fact(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id]);

        $response = $this->actingAs($user)->post(route('stories.reveal-ledger.store', $story), [
            'reveal_chapter_id' => $chapter->id,
        ]);

        $response->assertSessionHasErrors('fact');
    }

    public function test_entry_requires_a_reveal_chapter(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('stories.reveal-ledger.store', $story), [
            'fact' => 'the_diagnosis',
        ]);

        $response->assertSessionHasErrors('reveal_chapter_id');
    }

    public function test_reveal_chapter_must_belong_to_this_story(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $otherStory = Story::factory()->create(['user_id' => $user->id]);
        $foreignChapter = Chapter::factory()->create(['story_id' => $otherStory->id]);

        $response = $this->actingAs($user)->post(route('stories.reveal-ledger.store', $story), [
            'fact' => 'the_diagnosis',
            'reveal_chapter_id' => $foreignChapter->id,
        ]);

        $response->assertSessionHasErrors('reveal_chapter_id');
        $this->assertDatabaseEmpty('reveal_ledger');
    }

    public function test_about_character_must_belong_to_this_story(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id]);
        $otherStory = Story::factory()->create(['user_id' => $user->id]);
        $foreignCharacter = Character::factory()->create(['story_id' => $otherStory->id]);

        $response = $this->actingAs($user)->post(route('stories.reveal-ledger.store', $story), [
            'fact' => 'the_diagnosis',
            'reveal_chapter_id' => $chapter->id,
            'character_id' => $foreignCharacter->id,
        ]);

        $response->assertSessionHasErrors('character_id');
        $this->assertDatabaseEmpty('reveal_ledger');
    }

    public function test_creating_on_a_foreign_story_404s(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $foreign = Story::factory()->create(['user_id' => $other->id, 'slug' => 'theirs']);
        $chapter = Chapter::factory()->create(['story_id' => $foreign->id]);

        $response = $this->actingAs($owner)->post(route('stories.reveal-ledger.store', ['story' => 'theirs']), [
            'fact' => 'the_diagnosis',
            'reveal_chapter_id' => $chapter->id,
        ]);

        $response->assertNotFound();
        $this->assertDatabaseEmpty('reveal_ledger');
    }

    // --- Index ---

    public function test_index_lists_only_this_storys_entries(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $otherStory = Story::factory()->create(['user_id' => $user->id]);

        RevealLedger::factory()->count(2)->create(['story_id' => $story->id]);
        RevealLedger::factory()->create(['story_id' => $otherStory->id]);

        $response = $this->actingAs($user)->get(route('stories.reveal-ledger.index', $story));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('stories/RevealLedger')
            ->where('story.slug', $story->slug)
            ->has('entries', 2)
        );
    }

    public function test_index_404s_on_foreign_story(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Story::factory()->create(['user_id' => $other->id, 'slug' => 'theirs']);

        $response = $this->actingAs($owner)->get(route('stories.reveal-ledger.index', ['story' => 'theirs']));

        $response->assertNotFound();
    }

    // --- Update ---

    public function test_owner_can_update_an_entry(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id]);
        $entry = RevealLedger::factory()->create([
            'story_id' => $story->id,
            'reveal_chapter_id' => $chapter->id,
            'fact' => 'old_fact',
        ]);

        $response = $this->actingAs($user)->put(
            route('stories.reveal-ledger.update', ['story' => $story->slug, 'revealLedgerEntry' => $entry->id]),
            [
                'fact' => 'updated_fact',
                'reveal_chapter_id' => $chapter->id,
                'who_knows' => ['luna-archi'],
            ],
        );

        $response->assertRedirect(route('stories.reveal-ledger.index', $story));
        $this->assertDatabaseHas('reveal_ledger', [
            'id' => $entry->id,
            'fact' => 'updated_fact',
        ]);
    }

    public function test_update_404s_for_entry_from_another_story(): void
    {
        $user = User::factory()->create();
        $storyA = Story::factory()->create(['user_id' => $user->id]);
        $storyB = Story::factory()->create(['user_id' => $user->id]);
        $chapterB = Chapter::factory()->create(['story_id' => $storyB->id]);
        $entryB = RevealLedger::factory()->create([
            'story_id' => $storyB->id,
            'reveal_chapter_id' => $chapterB->id,
        ]);

        $response = $this->actingAs($user)->put(
            route('stories.reveal-ledger.update', ['story' => $storyA->slug, 'revealLedgerEntry' => $entryB->id]),
            [
                'fact' => 'cross_story_edit',
                'reveal_chapter_id' => $chapterB->id,
            ],
        );

        $response->assertNotFound();
    }

    // --- Delete ---

    public function test_owner_can_delete_an_entry(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $entry = RevealLedger::factory()->create(['story_id' => $story->id]);

        $response = $this->actingAs($user)->delete(
            route('stories.reveal-ledger.destroy', ['story' => $story->slug, 'revealLedgerEntry' => $entry->id]),
        );

        $response->assertRedirect(route('stories.reveal-ledger.index', $story));
        $this->assertDatabaseMissing('reveal_ledger', ['id' => $entry->id]);
    }

    public function test_destroy_404s_for_entry_from_another_story(): void
    {
        $user = User::factory()->create();
        $storyA = Story::factory()->create(['user_id' => $user->id]);
        $storyB = Story::factory()->create(['user_id' => $user->id]);
        $entryB = RevealLedger::factory()->create(['story_id' => $storyB->id]);

        $response = $this->actingAs($user)->delete(
            route('stories.reveal-ledger.destroy', ['story' => $storyA->slug, 'revealLedgerEntry' => $entryB->id]),
        );

        $response->assertNotFound();
        $this->assertDatabaseHas('reveal_ledger', ['id' => $entryB->id]);
    }

    // --- Auth gate ---

    public function test_guests_cannot_open_the_reveal_ledger(): void
    {
        $story = Story::factory()->create();

        $response = $this->get(route('stories.reveal-ledger.index', $story));

        $response->assertRedirect(route('login'));
    }
}
