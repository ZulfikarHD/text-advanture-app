<?php

namespace Tests\Feature\Stories;

use App\Enums\StateNode;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\LorebookEntry;
use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for story CRUD (S-1.1.1 / S-1.1.2).
 *
 * Covers: create with full payload, slug derivation, auto-suffix on collision,
 * duplicate slug rejection, per-owner uniqueness, list isolation, edit/update,
 * delete with cascade, and cross-owner 404s.
 */
class StoryCrudTest extends TestCase
{
    use RefreshDatabase;

    // --- S-1.1.1: Create ---

    public function test_owner_can_create_a_story_with_full_payload(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('stories.store'), [
            'title' => 'The Crystal Hollow',
            'slug' => 'the-crystal-hollow',
            'description' => 'A wandering archivist and her ward.',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('stories', [
            'user_id' => $user->id,
            'slug' => 'the-crystal-hollow',
            'title' => 'The Crystal Hollow',
        ]);
    }

    public function test_slug_is_derived_from_title_when_omitted(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('stories.store'), [
            'title' => 'The Crystal Hollow',
        ]);

        $this->assertDatabaseHas('stories', [
            'user_id' => $user->id,
            'slug' => 'the-crystal-hollow',
        ]);
    }

    public function test_derived_slug_auto_suffixes_on_collision(): void
    {
        $user = User::factory()->create();
        Story::factory()->create(['user_id' => $user->id, 'slug' => 'the-crystal-hollow']);

        $this->actingAs($user)->post(route('stories.store'), [
            'title' => 'The Crystal Hollow',
        ]);

        $this->assertDatabaseHas('stories', [
            'user_id' => $user->id,
            'slug' => 'the-crystal-hollow-2',
        ]);
    }

    public function test_explicit_duplicate_slug_for_same_owner_is_rejected(): void
    {
        $user = User::factory()->create();
        Story::factory()->create(['user_id' => $user->id, 'slug' => 'the-crystal-hollow']);

        $response = $this->actingAs($user)->post(route('stories.store'), [
            'title' => 'Another Story',
            'slug' => 'the-crystal-hollow',
        ]);

        $response->assertSessionHasErrors('slug');
    }

    public function test_two_different_owners_can_hold_the_same_slug(): void
    {
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        Story::factory()->create(['user_id' => $alice->id, 'slug' => 'shared-slug']);

        $this->actingAs($bob)->post(route('stories.store'), [
            'title' => 'My Story',
            'slug' => 'shared-slug',
        ]);

        $this->assertDatabaseHas('stories', ['user_id' => $bob->id, 'slug' => 'shared-slug']);
    }

    public function test_title_is_required(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post(route('stories.store'), []);

        $response->assertSessionHasErrors('title');
    }

    public function test_guests_cannot_create_stories(): void
    {
        $response = $this->post(route('stories.store'), [
            'title' => 'Nope',
        ]);

        $response->assertRedirect(route('login'));
    }

    // --- S-1.1.2: Index / List ---

    public function test_index_only_shows_my_own_stories(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();

        Story::factory()->create(['user_id' => $owner->id, 'title' => 'Mine']);
        Story::factory()->create(['user_id' => $other->id, 'title' => 'Theirs']);

        $response = $this->actingAs($owner)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('stories', 1)
            ->where('stories.0.title', 'Mine')
        );
    }

    public function test_index_exposes_a_resume_hint_for_a_played_story(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);
        $chapter = Chapter::factory()->create([
            'story_id' => $story->id,
            'number' => 3,
            'title' => 'Into the Hollow',
        ]);
        $story->playSessions()->create([
            'name' => 'Playthrough 1',
            'state_node' => StateNode::NarratorTurn,
            'current_chapter_id' => $chapter->id,
            'last_played_at' => now(),
        ]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stories.0.resume.chapterNumber', 3)
            ->where('stories.0.resume.chapterTitle', 'Into the Hollow')
        );
    }

    public function test_index_resume_hint_is_null_for_an_unplayed_story(): void
    {
        $user = User::factory()->create();
        Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('stories.0.resume', null)
        );
    }

    public function test_empty_dashboard_renders_for_new_user(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('dashboard'));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('stories', 0)
        );
    }

    // --- S-1.1.2: Edit ---

    public function test_edit_renders_my_own_story(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id, 'slug' => 'my-story']);

        $response = $this->actingAs($user)->get(route('stories.edit', $story));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('stories/Edit')
            ->where('story.slug', 'my-story')
        );
    }

    public function test_edit_404s_on_foreign_story(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $foreign = Story::factory()->create(['user_id' => $other->id, 'slug' => 'theirs']);

        $response = $this->actingAs($owner)->get(route('stories.edit', ['story' => 'theirs']));

        $response->assertNotFound();
    }

    // --- S-1.1.2: Update ---

    public function test_update_persists_changes(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create([
            'user_id' => $user->id,
            'slug' => 'old-slug',
            'title' => 'Old Title',
        ]);

        $response = $this->actingAs($user)->put(route('stories.update', $story), [
            'title' => 'New Title',
            'slug' => 'new-slug',
            'description' => 'Updated description.',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('stories', [
            'id' => $story->id,
            'title' => 'New Title',
            'slug' => 'new-slug',
            'description' => 'Updated description.',
        ]);
    }

    public function test_update_rejects_slug_collision_with_my_other_story(): void
    {
        $user = User::factory()->create();
        Story::factory()->create(['user_id' => $user->id, 'slug' => 'existing']);
        $story = Story::factory()->create(['user_id' => $user->id, 'slug' => 'mine']);

        $response = $this->actingAs($user)->put(route('stories.update', $story), [
            'title' => 'Mine',
            'slug' => 'existing',
        ]);

        $response->assertSessionHasErrors('slug');
    }

    // --- S-1.1.2: Delete ---

    public function test_destroy_removes_my_story(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->delete(route('stories.destroy', $story));

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseMissing('stories', ['id' => $story->id]);
    }

    public function test_destroy_404s_on_foreign_story(): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        $foreign = Story::factory()->create(['user_id' => $other->id, 'slug' => 'theirs']);

        $response = $this->actingAs($owner)->delete(route('stories.destroy', ['story' => 'theirs']));

        $response->assertNotFound();
    }

    public function test_destroy_cascades_authoring_children(): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $chapter = Chapter::factory()->create(['story_id' => $story->id]);
        $character = Character::factory()->create(['story_id' => $story->id]);
        $lore = LorebookEntry::factory()->create(['story_id' => $story->id]);

        $this->actingAs($user)->delete(route('stories.destroy', $story));

        $this->assertDatabaseMissing('chapters', ['id' => $chapter->id]);
        $this->assertDatabaseMissing('characters', ['id' => $character->id]);
        $this->assertDatabaseMissing('lorebook_entries', ['id' => $lore->id]);
    }
}
