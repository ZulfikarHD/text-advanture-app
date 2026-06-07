<?php

namespace App\Http\Controllers\Stories;

use App\Http\Controllers\Controller;
use App\Http\Requests\Stories\StoreCharacterRequest;
use App\Http\Requests\Stories\UpdateCharacterRequest;
use App\Models\Character;
use App\Models\Story;
use App\Services\CharacterService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Minimal manual character CRUD for a story's workspace (S-1.1.1 / S-1.1.2).
 *
 * Characters are story-scoped and tied to the novel's chapters: each one's
 * minimal fields (`appearance`, `folded_identity`, `knowledge_boundary`) live on
 * its per-`(character, chapter)` chapter-1 card. The parent `{story:slug}` binds
 * under the `OwnerScope`, so a foreign story resolves to 404; the child
 * `{character}` is resolved by scoped route-model binding (it must belong to the
 * bound story). Authorization is on the parent story — characters inherit
 * isolation transitively through it, so there is no character-level policy.
 * Writes go through {@see CharacterService} for atomic, no-LLM operations.
 */
class CharacterController extends Controller
{
    public function __construct(private readonly CharacterService $characters) {}

    /**
     * Render the story's cast with each character's chapter-1 card.
     */
    public function index(Story $story): Response
    {
        Gate::authorize('view', $story);

        $characters = $story->characters()
            ->with('chapterOneCard')
            ->orderByDesc('is_player')
            ->orderBy('name')
            ->get()
            ->map(fn (Character $character): array => [
                'id' => $character->id,
                'slug' => $character->slug,
                'name' => $character->name,
                'isPlayer' => $character->is_player,
                'baseOpacity' => $character->base_opacity,
                'appearance' => $character->chapterOneCard?->appearance,
                'foldedIdentity' => $character->chapterOneCard?->folded_identity,
                'knowledgeBoundary' => [
                    'knows' => $character->chapterOneCard?->knowledge_boundary['knows'] ?? [],
                    'doesNotKnow' => $character->chapterOneCard?->knowledge_boundary['does_not_know'] ?? [],
                ],
            ]);

        return Inertia::render('stories/Characters', [
            'story' => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
            ],
            'characters' => $characters,
        ]);
    }

    /**
     * Create a character (and its chapter-1 card) from the workspace dialog.
     */
    public function store(StoreCharacterRequest $request, Story $story): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->characters->create($story, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Character created.')]);

        return to_route('stories.characters.index', $story);
    }

    /**
     * Persist edits to an existing character and its chapter-1 card.
     */
    public function update(UpdateCharacterRequest $request, Story $story, Character $character): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->characters->update($character, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Character updated.')]);

        return to_route('stories.characters.index', $story);
    }

    /**
     * Remove a character (cascading to its cards).
     */
    public function destroy(Story $story, Character $character): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->characters->delete($character);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Character deleted.')]);

        return to_route('stories.characters.index', $story);
    }
}
