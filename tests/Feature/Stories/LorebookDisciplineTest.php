<?php

namespace Tests\Feature\Stories;

use App\Models\LorebookEntry;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for lorebook world-fact discipline (S-3.1.2, ADR 0013 §5).
 *
 * The discipline is a soft gate: content that reads as a character's interiority
 * is rejected unless the author explicitly acknowledges it as a world fact. A
 * clean world fact — including one that merely contains an emotive word — saves
 * without an acknowledgement.
 */
class LorebookDisciplineTest extends TestCase
{
    use RefreshDatabase;

    public function test_interiority_content_is_rejected_without_acknowledgement(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('stories.lorebook.index', $story), [
            'keywords' => ['Luna'],
            'content' => 'She secretly loves the archivist and would never say so.',
        ]);

        $response->assertSessionHasErrors('interiority');
        $this->assertDatabaseEmpty('lorebook_entries');
    }

    public function test_interiority_content_is_stored_when_acknowledged(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('stories.lorebook.index', $story), [
            'keywords' => ['Luna'],
            'content' => 'She secretly loves the archivist and would never say so.',
            'acknowledge_interiority' => true,
        ]);

        $response->assertRedirect(route('stories.lorebook.index', $story));
        $this->assertDatabaseCount('lorebook_entries', 1);
    }

    public function test_clean_world_fact_is_stored_without_acknowledgement(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('stories.lorebook.index', $story), [
            'keywords' => ['Crystal Hollow'],
            'content' => 'The Crystal Hollow is a sealed Aether sink beneath the old city.',
        ]);

        $response->assertRedirect(route('stories.lorebook.index', $story));
        $this->assertDatabaseCount('lorebook_entries', 1);
    }

    public function test_world_fact_with_an_emotive_word_is_not_a_false_positive(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->post(route('stories.lorebook.index', $story), [
            'keywords' => ['gloves'],
            'content' => 'The suppressor gloves feel cold and dampen Aether resonance.',
        ]);

        $response->assertSessionDoesntHaveErrors('interiority');
        $this->assertDatabaseCount('lorebook_entries', 1);
    }

    public function test_update_rejects_interiority_without_acknowledgement(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $entry = LorebookEntry::factory()->create([
            'story_id' => $story->id,
            'content' => 'The Hollow is a sealed Aether sink.',
        ]);

        $response = $this->actingAs($user)->put(
            route('stories.lorebook.update', ['story' => $story->slug, 'lorebookEntry' => $entry->id]),
            [
                'keywords' => ['Luna'],
                'content' => 'He knows but will not admit the diagnosis.',
            ],
        );

        $response->assertSessionHasErrors('interiority');
        $this->assertDatabaseHas('lorebook_entries', [
            'id' => $entry->id,
            'content' => 'The Hollow is a sealed Aether sink.',
        ]);
    }

    public function test_update_stores_interiority_when_acknowledged(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $entry = LorebookEntry::factory()->create(['story_id' => $story->id]);

        $response = $this->actingAs($user)->put(
            route('stories.lorebook.update', ['story' => $story->slug, 'lorebookEntry' => $entry->id]),
            [
                'keywords' => ['Luna'],
                'content' => 'He knows but will not admit the diagnosis.',
                'acknowledge_interiority' => true,
            ],
        );

        $response->assertRedirect(route('stories.lorebook.index', $story));
        $this->assertDatabaseHas('lorebook_entries', [
            'id' => $entry->id,
            'content' => 'He knows but will not admit the diagnosis.',
        ]);
    }
}
