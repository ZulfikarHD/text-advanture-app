<?php

namespace App\Services;

use App\Enums\StateNode;
use App\Exceptions\Sessions\StoryNotPlayableException;
use App\Models\Beat;
use App\Models\PlaySession;
use App\Models\Story;
use Illuminate\Support\Facades\DB;

/**
 * Session lifecycle — forking, managing, and resuming saves (E2.1).
 *
 * A session is a fork: the authoring template (immutable at runtime, ADR 0012)
 * is referenced by the new save rather than copied, and a single
 * {@see PlaySession} row carries the narrator-loop position. This service owns
 * the whole save lifecycle:
 *
 * - **fork** (S-2.1.1): create a new save at the first beat.
 * - **rename / reset / delete** (S-2.1.2): manage independent parallel saves.
 * - **resume** (S-2.1.3): stamp last-played so loading restores where play
 *   left off.
 *
 * Forking and resetting seed nothing else this phase — the minimal characters
 * carry no edges, so no `relationship_edges` are created (disposition-prior
 * seeding arrives in Phase 5, ADR 0002). Both are wrapped in a transaction so
 * they are atomic: a failure partway leaves no half-seeded, loadable save.
 */
class SessionService
{
    public function __construct(
        private readonly StoryOverviewService $overview,
        private readonly BeatSequence $beats,
    ) {}

    /**
     * Fork a play-ready story into a new save, positioned at its first beat.
     *
     * The save begins at {@see StateNode::SessionStart}, pointed at the earliest
     * beat in document order (first chapter → scene → beat by `number`), with
     * `last_played_at` stamped so it sorts as the most recent save.
     *
     * @param  Story  $story  The owner-scoped story to fork (already authorized).
     * @param  string|null  $name  An optional author-supplied save name; falls
     *                             back to the auto-derived "Playthrough N".
     * @return PlaySession The freshly forked save.
     *
     * @throws StoryNotPlayableException When the story fails the play-readiness gate.
     */
    public function fork(Story $story, ?string $name = null): PlaySession
    {
        if (! $this->overview->readiness($story)['ready']) {
            throw StoryNotPlayableException::for($story);
        }

        return DB::transaction(function () use ($story, $name): PlaySession {
            $beat = $this->firstPlayableBeat($story);

            return $story->playSessions()->create([
                'name' => $this->resolveName($story, $name),
                'state_node' => StateNode::SessionStart,
                'current_chapter_id' => $beat->scene->chapter_id,
                'current_scene_id' => $beat->scene_id,
                'current_beat_id' => $beat->id,
                'last_played_at' => now(),
            ]);

            // Phase 5 seam: disposition-prior edge seeding (ADR 0002) plugs in
            // here, inside this transaction, so the fork stays atomic as it
            // grows. No relationship_edges are created this phase.
        });
    }

    /**
     * Rename a save (S-2.1.2).
     *
     * Names carry no uniqueness constraint, so two saves may share a label
     * harmlessly. Renaming touches only the save's own row — never a sibling
     * or the authoring template.
     *
     * @param  PlaySession  $save  The save to rename (already authorized + scoped).
     * @param  string  $name  The new save name.
     * @return PlaySession The renamed save.
     */
    public function rename(PlaySession $save, string $name): PlaySession
    {
        $save->update(['name' => $name]);

        return $save;
    }

    /**
     * Reset a save back to its freshly-forked state (S-2.1.2).
     *
     * Re-positions the save at the story's first beat and clears every loop-state
     * counter so play can restart from the top, keeping the same id and name so
     * it stays the "same" save in the list. Wrapped in a transaction for the same
     * reason as {@see fork()}: it is the seam where Phase 5 reset will clear and
     * re-seed disposition-prior edges (and delete any save-realm children) inside
     * one atomic boundary. No save-realm children exist to clear this phase.
     *
     * @param  PlaySession  $save  The save to reset (already authorized + scoped).
     * @return PlaySession The reset save, positioned at session_start.
     *
     * @throws StoryNotPlayableException When the story has no resolvable first beat.
     */
    public function reset(PlaySession $save): PlaySession
    {
        return DB::transaction(function () use ($save): PlaySession {
            $beat = $this->firstPlayableBeat($save->story);

            $save->update([
                'state_node' => StateNode::SessionStart,
                'current_chapter_id' => $beat->scene->chapter_id,
                'current_scene_id' => $beat->scene_id,
                'current_beat_id' => $beat->id,
                'beat_word_count' => 0,
                'chapter_word_count' => 0,
                'nudge_level' => null,
                'resume_anchor' => null,
                'narrative_clock' => null,
                'last_played_at' => now(),
            ]);

            return $save;
        });
    }

    /**
     * Delete a save (S-2.1.2).
     *
     * Removes only this save; siblings and the authoring template are untouched.
     * The `play_sessions` FK is `cascadeOnDelete`, so future save-realm children
     * (edges, records, events) are removed with it automatically.
     *
     * @param  PlaySession  $save  The save to delete (already authorized + scoped).
     */
    public function delete(PlaySession $save): void
    {
        $save->delete();
    }

    /**
     * Resume a save by stamping it as the most recently played (S-2.1.3).
     *
     * Loading a save *is* resuming it: the persisted loop state (state node,
     * chapter/scene/beat position, resume anchor) is already on the row, so this
     * only refreshes `last_played_at` so the save sorts to the top of the list
     * and "continue where I left off" reflects the latest interaction. The
     * persisted position is returned unchanged — never reset to the beat start.
     *
     * @param  PlaySession  $save  The save being loaded (already authorized + scoped).
     * @return PlaySession The same save, now stamped as most-recently-played.
     */
    public function resume(PlaySession $save): PlaySession
    {
        $save->update(['last_played_at' => now()]);

        return $save;
    }

    /**
     * Resolve the earliest beat in document order for a play-ready story.
     *
     * Delegates the document-order walk to {@see BeatSequence} so the fork and
     * the loop spine share one ordering. A play-ready story is guaranteed at
     * least one beat; the null guard fails closed defensively.
     *
     * @param  Story  $story  The story whose first beat anchors the save.
     *
     * @throws StoryNotPlayableException When no beat exists (should never happen post-gate).
     */
    private function firstPlayableBeat(Story $story): Beat
    {
        $beat = $this->beats->first($story);

        if ($beat === null) {
            throw StoryNotPlayableException::for($story);
        }

        return $beat;
    }

    /**
     * Resolve the name for a new save — the author's, or an auto-derived default.
     *
     * @param  Story  $story  The story being forked.
     * @param  string|null  $name  An optional author-supplied name.
     */
    private function resolveName(Story $story, ?string $name): string
    {
        $name = $name === null ? null : trim($name);

        if ($name !== null && $name !== '') {
            return $name;
        }

        return $this->nextSaveName($story);
    }

    /**
     * Derive the default name for a story's next save ("Playthrough N").
     *
     * `name` carries no unique constraint, so concurrent forks may produce the
     * same default harmlessly.
     *
     * @param  Story  $story  The story being forked.
     */
    private function nextSaveName(Story $story): string
    {
        return 'Playthrough '.($story->playSessions()->count() + 1);
    }
}
