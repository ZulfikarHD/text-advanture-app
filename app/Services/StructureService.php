<?php

namespace App\Services;

use App\Enums\ElapsedBucket;
use App\Enums\ElapsedSource;
use App\Models\Beat;
use App\Models\Chapter;
use App\Models\Scene;
use App\Models\Story;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * Minimal manual structure authoring (S-1.2.1, ADR 0015 minimal slice).
 *
 * Persists a story's chapter → scene → beat hierarchy as owner-scoped atomic
 * operations, with no LLM call. This is the hand-authored slice only: a scene
 * carries its POV contract (`pov_mode`, `pov_anchor`, `tone`) + present cast,
 * and a beat carries just its `goal` (its satisfaction anchor). The full beat
 * document (`intent`, `word_budget`, `nudge_target`) and outline compilation
 * arrive in Phase 4, so `intent`/`word_budget` are written as deferred defaults
 * (PH-35) to satisfy their NOT NULL columns without surfacing them yet.
 *
 * Ordering is the per-parent integer `number` (unique per `(parent, number)`);
 * each create takes `max(number) + 1` inside a transaction so concurrent writes
 * never collide on the unique index. The exactly-one-player rule and the
 * present-cast / anchor validation live at the request layer; this service is
 * the persistence boundary.
 */
class StructureService
{
    /**
     * Placeholder word budget stamped on a hand-authored beat (PH-35).
     *
     * The beat's word budget is a Phase-4 concern (it clocks the nudge ladder),
     * but the column is NOT NULL, so a sensible default is written until the
     * full beat document is authored. Unused this phase.
     */
    public const int DEFAULT_WORD_BUDGET = 500;

    /**
     * Create a chapter under the given story, numbered after the last one.
     *
     * @param  Story  $story  The owner-scoped parent story.
     * @param  array{title: string, pov_default: string}  $data
     */
    public function createChapter(Story $story, array $data): Chapter
    {
        return DB::transaction(function () use ($story, $data): Chapter {
            return $story->chapters()->create([
                'number' => $this->nextNumber($story->chapters()),
                'title' => $data['title'],
                'pov_default' => $data['pov_default'],
            ]);
        });
    }

    /**
     * Update a chapter's authored fields (its `number` is system-managed).
     *
     * @param  Chapter  $chapter  The chapter to update (already scope-authorized).
     * @param  array{title: string, pov_default: string}  $data
     */
    public function updateChapter(Chapter $chapter, array $data): Chapter
    {
        return DB::transaction(function () use ($chapter, $data): Chapter {
            $chapter->update([
                'title' => $data['title'],
                'pov_default' => $data['pov_default'],
            ]);

            return $chapter;
        });
    }

    /**
     * Delete a chapter, cascading to its scenes and beats.
     *
     * The caller must guarantee the chapter holds no character cards: deleting
     * one would cascade-delete the E1.1 chapter-1 cards and orphan characters.
     * That invariant is asserted in the controller before this runs.
     *
     * @param  Chapter  $chapter  The chapter to remove (already scope-authorized).
     */
    public function deleteChapter(Chapter $chapter): void
    {
        DB::transaction(fn () => $chapter->delete());
    }

    /**
     * Create a scene under the given chapter with its POV contract.
     *
     * `elapsed_source` has no DB default, so a hand-authored scene records
     * `Default`/`Continuous` — declaring an in-world time gap is deferred to a
     * later phase (PH-35).
     *
     * @param  Chapter  $chapter  The owner-scoped parent chapter.
     * @param  array{pov_mode: string, pov_anchor: string, tone?: string|null, present_characters: list<string>}  $data
     */
    public function createScene(Chapter $chapter, array $data): Scene
    {
        return DB::transaction(function () use ($chapter, $data): Scene {
            return $chapter->scenes()->create([
                'number' => $this->nextNumber($chapter->scenes()),
                'pov_mode' => $data['pov_mode'],
                'pov_anchor' => $data['pov_anchor'],
                'tone' => $data['tone'] ?? null,
                'present_characters' => $this->normalizeList($data['present_characters'] ?? []),
                'elapsed_bucket' => ElapsedBucket::Continuous,
                'elapsed_source' => ElapsedSource::Default,
            ]);
        });
    }

    /**
     * Update a scene's POV contract and present cast.
     *
     * @param  Scene  $scene  The scene to update (already scope-authorized).
     * @param  array{pov_mode: string, pov_anchor: string, tone?: string|null, present_characters: list<string>}  $data
     */
    public function updateScene(Scene $scene, array $data): Scene
    {
        return DB::transaction(function () use ($scene, $data): Scene {
            $scene->update([
                'pov_mode' => $data['pov_mode'],
                'pov_anchor' => $data['pov_anchor'],
                'tone' => $data['tone'] ?? null,
                'present_characters' => $this->normalizeList($data['present_characters'] ?? []),
            ]);

            return $scene;
        });
    }

    /**
     * Delete a scene, cascading to its beats.
     *
     * @param  Scene  $scene  The scene to remove (already scope-authorized).
     */
    public function deleteScene(Scene $scene): void
    {
        DB::transaction(fn () => $scene->delete());
    }

    /**
     * Create a beat under the given scene with its goal as the only authored field.
     *
     * @param  Scene  $scene  The owner-scoped parent scene.
     * @param  array{goal: string}  $data
     */
    public function createBeat(Scene $scene, array $data): Beat
    {
        return DB::transaction(function () use ($scene, $data): Beat {
            return $scene->beats()->create([
                'number' => $this->nextNumber($scene->beats()),
                // intent/word_budget are Phase-4 beat-document fields (PH-35):
                // written as deferred defaults to satisfy their NOT NULL columns.
                'intent' => '',
                'goal' => $data['goal'],
                'word_budget' => self::DEFAULT_WORD_BUDGET,
                'nudge_target_character_id' => null,
            ]);
        });
    }

    /**
     * Update a beat's goal (its satisfaction anchor — the only field this phase).
     *
     * @param  Beat  $beat  The beat to update (already scope-authorized).
     * @param  array{goal: string}  $data
     */
    public function updateBeat(Beat $beat, array $data): Beat
    {
        return DB::transaction(function () use ($beat, $data): Beat {
            $beat->update(['goal' => $data['goal']]);

            return $beat;
        });
    }

    /**
     * Delete a beat.
     *
     * @param  Beat  $beat  The beat to remove (already scope-authorized).
     */
    public function deleteBeat(Beat $beat): void
    {
        DB::transaction(fn () => $beat->delete());
    }

    /**
     * Next `number` for a parent's children: `max(number) + 1`, locked.
     *
     * Runs inside the caller's transaction with a row lock so two concurrent
     * creates can never derive the same ordinal and trip the unique index.
     *
     * @param  HasMany<covariant \Illuminate\Database\Eloquent\Model, *>  $children
     */
    private function nextNumber($children): int
    {
        return (int) $children->lockForUpdate()->max('number') + 1;
    }

    /**
     * Trim, drop empties, and de-duplicate a list of character slugs.
     *
     * @param  list<string>  $items
     * @return list<string>
     */
    private function normalizeList(array $items): array
    {
        return collect($items)
            ->map(fn (string $item): string => trim($item))
            ->filter(fn (string $item): bool => $item !== '')
            ->unique()
            ->values()
            ->all();
    }
}
