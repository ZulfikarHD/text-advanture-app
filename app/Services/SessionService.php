<?php

namespace App\Services;

use App\Enums\StateNode;
use App\Exceptions\Sessions\StoryNotPlayableException;
use App\Models\Beat;
use App\Models\PlaySession;
use App\Models\Story;
use Illuminate\Support\Facades\DB;

/**
 * Session lifecycle — forking a play-ready story into the save realm (S-2.1.1).
 *
 * A session is a fork: the authoring template (immutable at runtime, ADR 0012)
 * is referenced by the new save rather than copied, and a single
 * {@see PlaySession} row carries the narrator-loop position. This phase seeds
 * nothing else — the minimal characters carry no edges, so no `relationship_edges`
 * are created (disposition-prior seeding arrives in Phase 5, ADR 0002). The fork
 * is wrapped in a transaction so it is atomic: a failure partway leaves no
 * half-seeded, loadable save.
 *
 * Loop-state persistence (resume_anchor, last-played restore) is S-2.1.3 and
 * multi-save management (name/list/load/reset/delete) is S-2.1.2; this service
 * is only the fork entry point.
 */
class SessionService
{
    public function __construct(private readonly StoryOverviewService $overview) {}

    /**
     * Fork a play-ready story into a new save, positioned at its first beat.
     *
     * The save begins at {@see StateNode::SessionStart}, pointed at the earliest
     * beat in document order (first chapter → scene → beat by `number`), with
     * `last_played_at` stamped so it sorts as the most recent save.
     *
     * @param  Story  $story  The owner-scoped story to fork (already authorized).
     * @return PlaySession The freshly forked save.
     *
     * @throws StoryNotPlayableException When the story fails the play-readiness gate.
     */
    public function fork(Story $story): PlaySession
    {
        if (! $this->overview->readiness($story)['ready']) {
            throw StoryNotPlayableException::for($story);
        }

        return DB::transaction(function () use ($story): PlaySession {
            $beat = $this->firstPlayableBeat($story);

            return $story->playSessions()->create([
                'name' => $this->nextSaveName($story),
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
     * Resolve the earliest beat in document order for a play-ready story.
     *
     * Ordered by chapter → scene → beat `number`, so the position is the true
     * narrative start even when chapter 1 holds no beats. A play-ready story is
     * guaranteed at least one beat; the null guard fails closed defensively.
     *
     * @param  Story  $story  The story whose first beat anchors the save.
     *
     * @throws StoryNotPlayableException When no beat exists (should never happen post-gate).
     */
    private function firstPlayableBeat(Story $story): Beat
    {
        $beat = Beat::query()
            ->join('scenes', 'beats.scene_id', '=', 'scenes.id')
            ->join('chapters', 'scenes.chapter_id', '=', 'chapters.id')
            ->where('chapters.story_id', $story->getKey())
            ->orderBy('chapters.number')
            ->orderBy('scenes.number')
            ->orderBy('beats.number')
            ->select('beats.*')
            ->with('scene')
            ->first();

        if ($beat === null) {
            throw StoryNotPlayableException::for($story);
        }

        return $beat;
    }

    /**
     * Derive the default name for a story's next save ("Playthrough N").
     *
     * Naming is auto-generated this phase; authoring a save name is S-2.1.2.
     * `name` carries no unique constraint, so concurrent forks may produce the
     * same default harmlessly until rename ships.
     *
     * @param  Story  $story  The story being forked.
     */
    private function nextSaveName(Story $story): string
    {
        return 'Playthrough '.($story->playSessions()->count() + 1);
    }
}
