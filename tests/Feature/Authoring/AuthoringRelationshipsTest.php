<?php

namespace Tests\Feature\Authoring;

use App\Models\Beat;
use App\Models\Chapter;
use App\Models\Character;
use App\Models\Register;
use App\Models\Scene;
use App\Models\Story;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Relationship, keying, and transitive-ownership tests for authoring models
 * (S-4.1.1).
 *
 * Confirms the FK chain resolves end to end, per-parent slug uniqueness holds,
 * and that deleting a story cascades to its children (the mechanism that makes
 * child ownership transitive through the owned story).
 */
class AuthoringRelationshipsTest extends TestCase
{
    use RefreshDatabase;

    public function test_beat_resolves_up_to_its_owning_story(): void
    {
        $story = Story::factory()->create();
        $chapter = Chapter::factory()->create(['story_id' => $story->id]);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id]);
        $beat = Beat::factory()->create(['scene_id' => $scene->id]);

        $this->assertSame($scene->id, $beat->scene->id);
        $this->assertSame($chapter->id, $beat->scene->chapter->id);
        $this->assertSame($story->id, $beat->scene->chapter->story->id);
    }

    public function test_register_slug_is_unique_per_character(): void
    {
        $character = Character::factory()->create();

        Register::factory()->create([
            'character_id' => $character->id,
            'slug' => 'koakuma_default',
        ]);

        $this->expectException(QueryException::class);

        Register::factory()->create([
            'character_id' => $character->id,
            'slug' => 'koakuma_default',
        ]);
    }

    public function test_deleting_a_story_cascades_to_its_children(): void
    {
        $story = Story::factory()->create();
        $character = Character::factory()->create(['story_id' => $story->id]);
        $chapter = Chapter::factory()->create(['story_id' => $story->id]);

        $story->delete();

        $this->assertDatabaseMissing('characters', ['id' => $character->id]);
        $this->assertDatabaseMissing('chapters', ['id' => $chapter->id]);
    }
}
