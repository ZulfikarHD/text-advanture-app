<?php

namespace App\Http\Controllers\Stories;

use App\Enums\PovMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Stories\StoreBeatRequest;
use App\Http\Requests\Stories\StoreChapterRequest;
use App\Http\Requests\Stories\StoreSceneRequest;
use App\Http\Requests\Stories\UpdateBeatRequest;
use App\Http\Requests\Stories\UpdateChapterRequest;
use App\Http\Requests\Stories\UpdateSceneRequest;
use App\Models\Beat;
use App\Models\Chapter;
use App\Models\Scene;
use App\Models\Story;
use App\Services\StorySettingsService;
use App\Services\StructureService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Minimal manual structure CRUD for a story's workspace (S-1.2.1).
 *
 * Authors the story's chapter → scene → beat hierarchy by hand — no LLM call —
 * so the loop has a position and an anchor to narrate toward. A scene carries
 * its POV contract (`pov_mode`, `pov_anchor`, `tone`) + present cast; a beat
 * carries its `goal`. The parent `{story:slug}` binds under the `OwnerScope`,
 * so a foreign story resolves to 404; the nested `{chapter}`/`{scene}`/`{beat}`
 * children are resolved by scoped route-model binding down the relationship
 * chain (each must belong to its bound parent). Authorization is on the parent
 * story — structure rows inherit isolation transitively through it, so there is
 * no row-level policy. Writes go through {@see StructureService}.
 */
class StructureController extends Controller
{
    public function __construct(
        private readonly StructureService $structure,
        private readonly StorySettingsService $settings,
    ) {}

    /**
     * Render the story's structure tree, its cast, and the POV vocabulary.
     */
    public function index(Story $story): Response
    {
        Gate::authorize('view', $story);

        $characters = $story->characters()
            ->orderByDesc('is_player')
            ->orderBy('name')
            ->get()
            ->map(fn ($character): array => [
                'id' => $character->id,
                'slug' => $character->slug,
                'name' => $character->name,
                'isPlayer' => $character->is_player,
            ]);

        $chapters = $story->chapters()
            ->withCount('characterCards')
            ->with(['scenes' => function ($query): void {
                $query->orderBy('number')
                    ->with(['beats' => fn ($beats) => $beats->orderBy('number')]);
            }])
            ->orderBy('number')
            ->get()
            ->map(fn (Chapter $chapter): array => [
                'id' => $chapter->id,
                'number' => $chapter->number,
                'title' => $chapter->title,
                'povDefault' => $chapter->pov_default,
                // A chapter holding character cards can't be deleted — that would
                // cascade-delete the E1.1 chapter-1 cards and orphan characters.
                'canDelete' => $chapter->character_cards_count === 0,
                'scenes' => $chapter->scenes->map(fn (Scene $scene): array => [
                    'id' => $scene->id,
                    'number' => $scene->number,
                    'povMode' => $scene->pov_mode,
                    'povAnchor' => $scene->pov_anchor,
                    'tone' => $scene->tone,
                    'presentCharacters' => $scene->present_characters ?? [],
                    'beats' => $scene->beats->map(fn (Beat $beat): array => [
                        'id' => $beat->id,
                        'number' => $beat->number,
                        'goal' => $beat->goal,
                    ]),
                ]),
            ]);

        return Inertia::render('stories/Structure', [
            'story' => [
                'id' => $story->id,
                'slug' => $story->slug,
                'title' => $story->title,
            ],
            'characters' => $characters,
            'chapters' => $chapters,
            'povOptions' => collect(PovMode::cases())->map(fn (PovMode $pov): array => [
                'value' => $pov->value,
                'label' => $pov->label(),
                'description' => $pov->description(),
            ])->all(),
            'defaultPov' => $this->settings->resolveDefaultPov($story)->value,
        ]);
    }

    // --- Chapters ---

    /**
     * Create a chapter under the story.
     */
    public function storeChapter(StoreChapterRequest $request, Story $story): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->structure->createChapter($story, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Chapter created.')]);

        return to_route('stories.structure.index', $story);
    }

    /**
     * Persist edits to an existing chapter.
     */
    public function updateChapter(UpdateChapterRequest $request, Story $story, Chapter $chapter): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->structure->updateChapter($chapter, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Chapter updated.')]);

        return to_route('stories.structure.index', $story);
    }

    /**
     * Remove a chapter unless it still anchors character cards.
     *
     * Deleting a chapter cascades to its character cards, which would orphan the
     * E1.1 cast; that delete is rejected with a clear message rather than run.
     */
    public function destroyChapter(Story $story, Chapter $chapter): RedirectResponse
    {
        Gate::authorize('update', $story);

        if ($chapter->characterCards()->exists()) {
            Inertia::flash('toast', [
                'type' => 'error',
                'message' => __('This chapter anchors character cards — move or delete those characters first.'),
            ]);

            return to_route('stories.structure.index', $story);
        }

        $this->structure->deleteChapter($chapter);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Chapter deleted.')]);

        return to_route('stories.structure.index', $story);
    }

    // --- Scenes ---

    /**
     * Create a scene under the given chapter.
     */
    public function storeScene(StoreSceneRequest $request, Story $story, Chapter $chapter): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->structure->createScene($chapter, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scene created.')]);

        return to_route('stories.structure.index', $story);
    }

    /**
     * Persist edits to an existing scene.
     */
    public function updateScene(UpdateSceneRequest $request, Story $story, Chapter $chapter, Scene $scene): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->structure->updateScene($scene, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scene updated.')]);

        return to_route('stories.structure.index', $story);
    }

    /**
     * Remove a scene (cascading to its beats).
     */
    public function destroyScene(Story $story, Chapter $chapter, Scene $scene): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->structure->deleteScene($scene);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Scene deleted.')]);

        return to_route('stories.structure.index', $story);
    }

    // --- Beats ---

    /**
     * Create a beat under the given scene.
     */
    public function storeBeat(StoreBeatRequest $request, Story $story, Chapter $chapter, Scene $scene): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->structure->createBeat($scene, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Beat created.')]);

        return to_route('stories.structure.index', $story);
    }

    /**
     * Persist edits to an existing beat.
     */
    public function updateBeat(UpdateBeatRequest $request, Story $story, Chapter $chapter, Scene $scene, Beat $beat): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->structure->updateBeat($beat, $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Beat updated.')]);

        return to_route('stories.structure.index', $story);
    }

    /**
     * Remove a beat.
     */
    public function destroyBeat(Story $story, Chapter $chapter, Scene $scene, Beat $beat): RedirectResponse
    {
        Gate::authorize('update', $story);

        $this->structure->deleteBeat($beat);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Beat deleted.')]);

        return to_route('stories.structure.index', $story);
    }
}
