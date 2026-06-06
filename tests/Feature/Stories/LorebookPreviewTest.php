<?php

namespace Tests\Feature\Stories;

use App\Models\Chapter;
use App\Models\LorebookEntry;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the lorebook keyword match preview (S-3.2.1, ADR 0013 §5).
 *
 * Covers the triggered/withheld split, that the reveal-chapter clamp matches the
 * compile/runtime rule, validation, the story-scoped chapter rule, owner-scoped
 * access (foreign story 404), and the auth gate. The endpoint answers JSON for
 * the `useHttp` client.
 */
class LorebookPreviewTest extends TestCase
{
    use RefreshDatabase;

    public function test_preview_lists_entries_whose_keywords_match_the_sample(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        LorebookEntry::factory()->create(['story_id' => $story->id, 'keywords' => ['gloves']]);
        LorebookEntry::factory()->create(['story_id' => $story->id, 'keywords' => ['Aether']]);

        $response = $this->actingAs($user)->postJson(route('stories.lorebook.preview', $story), [
            'sample_text' => 'She adjusted her suppressor gloves before touching the Aether.',
        ]);

        $response->assertOk();
        $response->assertJsonCount(2, 'triggered');
    }

    public function test_preview_excludes_entries_with_no_matching_keyword(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        LorebookEntry::factory()->create(['story_id' => $story->id, 'keywords' => ['dragon']]);

        $response = $this->actingAs($user)->postJson(route('stories.lorebook.preview', $story), [
            'sample_text' => 'She adjusted her suppressor gloves.',
        ]);

        $response->assertOk();
        $response->assertJsonCount(0, 'triggered');
    }

    public function test_reveal_clamp_withholds_an_entry_previewed_before_its_reveal(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $chapterOne = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        $chapterThree = Chapter::factory()->create(['story_id' => $story->id, 'number' => 3]);
        LorebookEntry::factory()->create([
            'story_id' => $story->id,
            'keywords' => ['gloves'],
            'min_reveal_chapter_id' => $chapterThree->id,
        ]);

        $response = $this->actingAs($user)->postJson(route('stories.lorebook.preview', $story), [
            'sample_text' => 'the suppressor gloves',
            'chapter_id' => $chapterOne->id,
        ]);

        $response->assertOk();
        $response->assertJsonCount(0, 'triggered');
        $response->assertJsonCount(1, 'withheld');
    }

    public function test_entry_is_triggered_when_previewing_at_its_reveal_chapter(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $chapterThree = Chapter::factory()->create(['story_id' => $story->id, 'number' => 3]);
        LorebookEntry::factory()->create([
            'story_id' => $story->id,
            'keywords' => ['gloves'],
            'min_reveal_chapter_id' => $chapterThree->id,
        ]);

        $response = $this->actingAs($user)->postJson(route('stories.lorebook.preview', $story), [
            'sample_text' => 'the suppressor gloves',
            'chapter_id' => $chapterThree->id,
        ]);

        $response->assertOk();
        $response->assertJsonCount(1, 'triggered');
        $response->assertJsonCount(0, 'withheld');
    }

    public function test_preview_requires_sample_text(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->postJson(route('stories.lorebook.preview', $story), []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('sample_text');
    }

    public function test_chapter_id_must_belong_to_this_story(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $otherStory = Story::factory()->create(['user_id' => $user->id]);
        $foreignChapter = Chapter::factory()->create(['story_id' => $otherStory->id]);

        $response = $this->actingAs($user)->postJson(route('stories.lorebook.preview', $story), [
            'sample_text' => 'anything',
            'chapter_id' => $foreignChapter->id,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('chapter_id');
    }

    public function test_preview_on_a_foreign_story_404s(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Story::factory()->create(['user_id' => $other->id, 'slug' => 'theirs']);

        $response = $this->actingAs($owner)->postJson(route('stories.lorebook.preview', ['story' => 'theirs']), [
            'sample_text' => 'anything',
        ]);

        $response->assertNotFound();
    }

    public function test_guests_cannot_preview(): void
    {
        $story = Story::factory()->create();

        $response = $this->postJson(route('stories.lorebook.preview', $story), [
            'sample_text' => 'anything',
        ]);

        // The app renders web redirects for auth outside api/* (bootstrap/app.php),
        // so a guest is bounced to login rather than receiving a 401.
        $response->assertRedirect(route('login'));
    }
}
