<?php

namespace App\Services;

use App\Models\Beat;
use App\Models\Story;
use Illuminate\Database\Eloquent\Builder;

/**
 * Beat sequencing in document order (S-2.1.1 / S-3.1.1, ADR 0016).
 *
 * The single home for "which beat is first / which beat is next" in a story,
 * ordered by chapter -> scene -> beat `number`. Both the session fork
 * ({@see SessionService}) and the loop spine ({@see SessionStateMachine}) walk
 * this same order, so resolving it once here keeps a save's position consistent
 * and prevents two diverging copies of the ordering query.
 *
 * Every query joins through `scenes` -> `chapters` to reach the owning story and
 * sorts on the per-parent `number` (the same ordinal the authoring surface
 * assigns). The `scene` is eager-loaded so callers can read `scene->chapter_id`
 * when they stamp a save's position without a follow-up query.
 */
class BeatSequence
{
    /**
     * Resolve the earliest beat in a story, in document order.
     *
     * @param  Story  $story  The story whose first beat is wanted.
     * @return Beat|null The first beat, or null when the story has no beats.
     */
    public function first(Story $story): ?Beat
    {
        return $this->ordered()
            ->where('chapters.story_id', $story->getKey())
            ->first();
    }

    /**
     * Resolve the beat immediately after the given one, in document order.
     *
     * Walks within the same story and returns the next beat whose
     * (chapter, scene, beat) ordinal tuple is strictly greater than the current
     * beat's: the next beat in the same scene, else the first beat of the next
     * scene, else the first beat of the next chapter.
     *
     * @param  Beat  $beat  The current beat; its scene/chapter are loaded as needed.
     * @return Beat|null The next beat, or null when the current beat is the last.
     */
    public function next(Beat $beat): ?Beat
    {
        $beat->loadMissing('scene.chapter');

        $chapterNumber = $beat->scene->chapter->number;
        $sceneNumber = $beat->scene->number;
        $beatNumber = $beat->number;

        return $this->ordered()
            ->where('chapters.story_id', $beat->scene->chapter->story_id)
            ->where(function (Builder $query) use ($chapterNumber, $sceneNumber, $beatNumber): void {
                // Lexicographic "tuple > current" across the three ordinals.
                $query
                    ->where('chapters.number', '>', $chapterNumber)
                    ->orWhere(function (Builder $sameChapter) use ($chapterNumber, $sceneNumber): void {
                        $sameChapter
                            ->where('chapters.number', $chapterNumber)
                            ->where('scenes.number', '>', $sceneNumber);
                    })
                    ->orWhere(function (Builder $sameScene) use ($chapterNumber, $sceneNumber, $beatNumber): void {
                        $sameScene
                            ->where('chapters.number', $chapterNumber)
                            ->where('scenes.number', $sceneNumber)
                            ->where('beats.number', '>', $beatNumber);
                    });
            })
            ->first();
    }

    /**
     * Base query: beats joined through scene -> chapter, document-ordered, with
     * the scene eager-loaded.
     *
     * @return Builder<Beat>
     */
    private function ordered(): Builder
    {
        return Beat::query()
            ->join('scenes', 'beats.scene_id', '=', 'scenes.id')
            ->join('chapters', 'scenes.chapter_id', '=', 'chapters.id')
            ->orderBy('chapters.number')
            ->orderBy('scenes.number')
            ->orderBy('beats.number')
            ->select('beats.*')
            ->with('scene');
    }
}
