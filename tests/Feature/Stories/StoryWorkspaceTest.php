<?php

namespace Tests\Feature\Stories;

use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature tests for the workspace placeholder surfaces (E2.1 / S-2.1.1).
 *
 * Covers: every placeholder surface renders the shared ComingSoon page with its
 * descriptor for the owner, surfaces are owner-scoped (foreign story 404s without
 * leaking existence), and they sit behind the auth gate.
 */
class StoryWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The placeholder surfaces as [route name, surface key, surface title] rows.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function surfaceProvider(): array
    {
        return [
            // Characters shipped in E1.1 and Structure in E1.2 — neither is a
            // placeholder surface anymore (see CharacterCrudTest /
            // StructureCrudTest). Only Saves remains a placeholder.
            'saves' => ['stories.saves.index', 'saves', 'Saves'],
        ];
    }

    #[DataProvider('surfaceProvider')]
    public function test_owner_can_open_a_placeholder_surface(string $routeName, string $key, string $title): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route($routeName, $story));

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('stories/ComingSoon')
            ->where('story.slug', $story->slug)
            ->where('surface.key', $key)
            ->where('surface.title', $title)
        );
    }

    #[DataProvider('surfaceProvider')]
    public function test_placeholder_surface_404s_on_foreign_story(string $routeName): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Story::factory()->create(['user_id' => $other->id, 'slug' => 'theirs']);

        $response = $this->actingAs($owner)->get(route($routeName, ['story' => 'theirs']));

        $response->assertNotFound();
    }

    #[DataProvider('surfaceProvider')]
    public function test_guests_cannot_open_a_placeholder_surface(string $routeName): void
    {
        $story = Story::factory()->create();

        $response = $this->get(route($routeName, $story));

        $response->assertRedirect(route('login'));
    }
}
