<?php

namespace Tests\Feature\Stories;

use App\Models\Story;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Feature tests for the per-story workspace surfaces (E1–E2).
 *
 * Every workspace surface is now live (the last placeholder, Saves, shipped in
 * S-2.1.1). This guards the shell invariants the workspace promises: each
 * surface index is reachable by its owner, owner-scoped (a foreign story 404s
 * without leaking existence), and behind the auth gate. Surface-specific
 * behaviour lives in each surface's own test (e.g. SessionForkTest for Saves).
 */
class StoryWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * The workspace surface index route names, keyed by tab.
     *
     * @return array<string, array{0: string}>
     */
    public static function surfaceProvider(): array
    {
        return [
            'overview' => ['stories.show'],
            'characters' => ['stories.characters.index'],
            'structure' => ['stories.structure.index'],
            'lorebook' => ['stories.lorebook.index'],
            'reveal-ledger' => ['stories.reveal-ledger.index'],
            'settings' => ['stories.settings.edit'],
            'saves' => ['stories.saves.index'],
            'details' => ['stories.edit'],
        ];
    }

    #[DataProvider('surfaceProvider')]
    public function test_owner_can_open_a_workspace_surface(string $routeName): void
    {
        $user = User::factory()->create();
        $story = Story::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)->get(route($routeName, $story));

        $response->assertOk();
    }

    #[DataProvider('surfaceProvider')]
    public function test_workspace_surface_404s_on_foreign_story(string $routeName): void
    {
        $owner = User::factory()->create();
        $other = User::factory()->create();
        Story::factory()->create(['user_id' => $other->id, 'slug' => 'theirs']);

        $response = $this->actingAs($owner)->get(route($routeName, ['story' => 'theirs']));

        $response->assertNotFound();
    }

    #[DataProvider('surfaceProvider')]
    public function test_guests_cannot_open_a_workspace_surface(string $routeName): void
    {
        $story = Story::factory()->create();

        $response = $this->get(route($routeName, $story));

        $response->assertRedirect(route('login'));
    }
}
