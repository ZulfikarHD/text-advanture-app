<?php

namespace Tests\Feature\Stories;

use App\Models\Chapter;
use App\Models\LorebookEntry;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for lorebook entry CRUD (S-3.1.1, ADR 0013 §5).
 *
 * Covers: create (full + title-omitted + keyword normalization), required-field
 * validation, the story-scoped reveal-chapter rule, owner-scoped listing,
 * update, delete, scoped child binding (cross-story 404s), and the auth gate.
 */
class LorebookCrudTest extends TestCase
{
    use RefreshDatabase;

    // --- Create ---

    public function test_owner_can_create_a_lorebook_entry_with_full_payload(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id]);

        $response = $this->actingAs($user)->post(route('stories.lorebook.index', $story), [
            'title' => 'The Crystal Hollow',
            'keywords' => ['Crystal Hollow', 'gloves', 'Chrysalis'],
            'content' => 'The Hollow is a sealed Aether sink beneath the old city.',
            'min_reveal_chapter_id' => $chapter->id,
        ]);

        $response->assertRedirect(route('stories.lorebook.index', $story));

        $this->assertDatabaseHas('lorebook_entries', [
            'story_id' => $story->id,
            'title' => 'The Crystal Hollow',
            'min_reveal_chapter_id' => $chapter->id,
        ]);
    }

    public function test_created_entry_stores_its_keywords_array(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('stories.lorebook.index', $story), [
            'keywords' => ['Aether', 'gloves'],
            'content' => 'World fact.',
        ]);

        $this->assertSame(['Aether', 'gloves'], LorebookEntry::firstOrFail()->keywords);
    }

    public function test_lorebook_entry_can_be_created_without_a_title(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('stories.lorebook.index', $story), [
            'keywords' => ['Aether'],
            'content' => 'The ambient energy that powers Link Resonance.',
        ]);

        $this->assertDatabaseHas('lorebook_entries', [
            'story_id' => $story->id,
            'title' => null,
        ]);
    }

    public function test_keywords_are_trimmed_and_deduplicated(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $this->actingAs($user)->post(route('stories.lorebook.index', $story), [
            'keywords' => ['gloves', ' gloves ', 'Aether'],
            'content' => 'World fact.',
        ]);

        $this->assertSame(['gloves', 'Aether'], LorebookEntry::firstOrFail()->keywords);
    }

    public function test_lorebook_entry_requires_at_least_one_keyword(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('stories.lorebook.index', $story), [
            'keywords' => [],
            'content' => 'World fact.',
        ]);

        $response->assertSessionHasErrors('keywords');
    }

    public function test_lorebook_entry_requires_content(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('stories.lorebook.index', $story), [
            'keywords' => ['Aether'],
        ]);

        $response->assertSessionHasErrors('content');
    }

    public function test_min_reveal_chapter_must_belong_to_this_story(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $otherStory = Story::factory()->create(['user_id' => $user->id]);
        $foreignChapter = Chapter::factory()->create(['story_id' => $otherStory->id]);

        $response = $this->actingAs($user)->post(route('stories.lorebook.index', $story), [
            'keywords' => ['Aether'],
            'content' => 'World fact.',
            'min_reveal_chapter_id' => $foreignChapter->id,
        ]);

        $response->assertSessionHasErrors('min_reveal_chapter_id');
        $this->assertDatabaseEmpty('lorebook_entries');
    }

    public function test_creating_on_a_foreign_story_404s(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $foreign = Story::factory()->create(['user_id' => $other->id, 'slug' => 'theirs']);

        $response = $this->actingAs($owner)->post(route('stories.lorebook.index', ['story' => 'theirs']), [
            'keywords' => ['Aether'],
            'content' => 'World fact.',
        ]);

        $response->assertNotFound();
        $this->assertDatabaseEmpty('lorebook_entries');
    }

    // --- Index ---

    public function test_index_lists_only_this_storys_entries(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $otherStory = Story::factory()->create(['user_id' => $user->id]);

        LorebookEntry::factory()->count(2)->create(['story_id' => $story->id]);
        LorebookEntry::factory()->create(['story_id' => $otherStory->id]);

        $response = $this->actingAs($user)->get(route('stories.lorebook.index', $story));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('stories/Lorebook')
            ->where('story.slug', $story->slug)
            ->has('entries', 2)
        );
    }

    public function test_index_404s_on_foreign_story(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Story::factory()->create(['user_id' => $other->id, 'slug' => 'theirs']);

        $response = $this->actingAs($owner)->get(route('stories.lorebook.index', ['story' => 'theirs']));

        $response->assertNotFound();
    }

    // --- Update ---

    public function test_owner_can_update_an_entry(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $entry = LorebookEntry::factory()->create([
            'story_id' => $story->id,
            'content' => 'Old content.',
        ]);

        $response = $this->actingAs($user)->put(
            route('stories.lorebook.update', ['story' => $story->slug, 'lorebookEntry' => $entry->id]),
            [
                'title' => 'Updated',
                'keywords' => ['updated'],
                'content' => 'New content.',
            ],
        );

        $response->assertRedirect(route('stories.lorebook.index', $story));
        $this->assertDatabaseHas('lorebook_entries', [
            'id' => $entry->id,
            'title' => 'Updated',
            'content' => 'New content.',
        ]);
    }

    public function test_update_404s_for_entry_from_another_story(): void
    {
        $user = User::factory()->create();
        $storyA = Story::factory()->create(['user_id' => $user->id]);
        $storyB = Story::factory()->create(['user_id' => $user->id]);
        $entryB = LorebookEntry::factory()->create(['story_id' => $storyB->id]);

        $response = $this->actingAs($user)->put(
            route('stories.lorebook.update', ['story' => $storyA->slug, 'lorebookEntry' => $entryB->id]),
            [
                'keywords' => ['x'],
                'content' => 'Attempted cross-story edit.',
            ],
        );

        $response->assertNotFound();
    }

    // --- Delete ---

    public function test_owner_can_delete_an_entry(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $entry = LorebookEntry::factory()->create(['story_id' => $story->id]);

        $response = $this->actingAs($user)->delete(
            route('stories.lorebook.destroy', ['story' => $story->slug, 'lorebookEntry' => $entry->id]),
        );

        $response->assertRedirect(route('stories.lorebook.index', $story));
        $this->assertDatabaseMissing('lorebook_entries', ['id' => $entry->id]);
    }

    public function test_destroy_404s_for_entry_from_another_story(): void
    {
        $user = User::factory()->create();
        $storyA = Story::factory()->create(['user_id' => $user->id]);
        $storyB = Story::factory()->create(['user_id' => $user->id]);
        $entryB = LorebookEntry::factory()->create(['story_id' => $storyB->id]);

        $response = $this->actingAs($user)->delete(
            route('stories.lorebook.destroy', ['story' => $storyA->slug, 'lorebookEntry' => $entryB->id]),
        );

        $response->assertNotFound();
        $this->assertDatabaseHas('lorebook_entries', ['id' => $entryB->id]);
    }

    // --- Auth gate ---

    public function test_guests_cannot_open_the_lorebook(): void
    {
        $story = Story::factory()->create();

        $response = $this->get(route('stories.lorebook.index', $story));

        $response->assertRedirect(route('login'));
    }
}
