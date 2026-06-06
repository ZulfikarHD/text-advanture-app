<?php

namespace App\Http\Controllers\Stories;

use App\Http\Controllers\Controller;
use App\Models\Story;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Placeholder surfaces for the per-story authoring workspace (E2.1 / S-2.1.1).
 *
 * The workspace shell promises navigation across every authoring surface
 * (characters, structure/outline, lorebook, settings, saves), but four of them
 * are not built yet — they land in later phases. Rather than leave dead nav
 * items, each unbuilt surface renders a reachable "coming soon" page so the
 * author can see the full workspace shape today.
 *
 * Every method is owner-scoped: `{story:slug}` binds under `OwnerScope`, so a
 * foreign story resolves to 404 without leaking its existence, and `view`
 * authorization is asserted before rendering.
 *
 * Replaceable by design: when a surface's real feature ships, its route is
 * repointed at the real controller and the matching method here is removed.
 * Tracked as PH-30 in docs/guides/PLACEHOLDER_TRACKING.md.
 */
class StoryPlaceholderController extends Controller
{
    /**
     * Characters surface — compiled character cards (lands in Phase 3, O5).
     */
    public function characters(Story $story): Response
    {
        return $this->placeholder($story, [
            'key' => 'characters',
            'title' => 'Characters',
            'description' => 'Compile spoiler-free character cards, relationship edges, and sensitivities from each source bible. The cast that the engine plays lives here.',
            'phase' => 'Phase 3',
        ]);
    }

    /**
     * Structure surface — chapters, scenes, and beats (lands in Phase 4, O6).
     */
    public function structure(Story $story): Response
    {
        return $this->placeholder($story, [
            'key' => 'structure',
            'title' => 'Structure',
            'description' => 'Author the outline and compile it into chapters, scenes, and the hidden beat documents that steer every turn toward its goal.',
            'phase' => 'Phase 4',
        ]);
    }

    /**
     * Lorebook surface — world facts injected on keyword match (E3.1, Sprint 9).
     */
    public function lorebook(Story $story): Response
    {
        return $this->placeholder($story, [
            'key' => 'lorebook',
            'title' => 'Lorebook',
            'description' => 'Record world facts that are injected on keyword match at runtime — places, objects, and mechanisms, never a character\'s private interiority.',
            'phase' => 'Sprint 9 · E3.1',
        ]);
    }

    /**
     * Saves surface — save-realm playthroughs forked from this story (Phase 5).
     */
    public function saves(Story $story): Response
    {
        return $this->placeholder($story, [
            'key' => 'saves',
            'title' => 'Saves',
            'description' => 'Browse the save-realm playthroughs forked from this story\'s authoring template. Each save evolves independently once play begins.',
            'phase' => 'Phase 5',
        ]);
    }

    /**
     * Render the shared "coming soon" page for an unbuilt workspace surface.
     *
     * The `story` prop is required by the workspace layout (it scopes the shell
     * and renders the tab bar); `surface` drives the teaching empty state.
     *
     * @param  Story  $story  The owner-scoped story whose workspace is open.
     * @param  array{key: string, title: string, description: string, phase: string}  $surface  The surface descriptor.
     */
    private function placeholder(Story $story, array $surface): Response
    {
        Gate::authorize('view', $story);

        return Inertia::render('stories/ComingSoon', [
            'story' => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
            ],
            'surface' => $surface,
        ]);
    }
}
