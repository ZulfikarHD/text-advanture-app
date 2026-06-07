<?php

namespace Tests\Feature\Sessions;

use App\Enums\Handoff;
use App\Enums\StateNode;
use App\Exceptions\Sessions\IllegalLoopTransitionException;
use App\Models\Beat;
use App\Models\Chapter;
use App\Models\PlaySession;
use App\Models\Scene;
use App\Models\Story;
use App\Services\SessionStateMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Feature tests for the narrator-loop spine — the state machine (S-3.1.1 /
 * S-3.1.2, ADR 0016).
 *
 * Covers the deterministic transitions
 * `session_start -> narrator_turn -> { player_moment | beat_complete } ->
 * narrator_resumes`: each edge moves the persisted `state_node` (and, when a
 * beat closes, the position) exactly as the handoff dictates; the `npc_moment`
 * branch is not reachable this phase; out-of-order transitions fail closed; and
 * one full cycle is driven entirely through the single state-machine conductor.
 */
class SessionStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_begin_moves_session_start_to_narrator_turn(): void
    {
        $session = $this->sessionAt(StateNode::SessionStart);

        $this->machine()->begin($session);

        $this->assertSame(StateNode::NarratorTurn, $session->fresh()->state_node);
    }

    public function test_player_moment_handoff_moves_narrator_turn_to_player_moment(): void
    {
        $session = $this->sessionAt(StateNode::NarratorTurn);

        $this->machine()->applyHandoff($session, Handoff::PlayerMoment);

        $this->assertSame(StateNode::PlayerMoment, $session->fresh()->state_node);
    }

    public function test_beat_complete_handoff_moves_narrator_turn_to_beat_complete(): void
    {
        $session = $this->sessionAt(StateNode::NarratorTurn);

        $this->machine()->applyHandoff($session, Handoff::BeatComplete);

        $this->assertSame(StateNode::BeatComplete, $session->fresh()->state_node);
    }

    public function test_resume_from_player_moment_returns_to_narrator_turn(): void
    {
        $session = $this->sessionAt(StateNode::PlayerMoment);

        $this->machine()->resumeFromPlayerMoment($session);

        $this->assertSame(StateNode::NarratorTurn, $session->fresh()->state_node);
    }

    public function test_resume_from_player_moment_keeps_the_same_beat(): void
    {
        $session = $this->sessionAt(StateNode::PlayerMoment);
        $beatId = $session->current_beat_id;

        $this->machine()->resumeFromPlayerMoment($session);

        $this->assertSame($beatId, $session->fresh()->current_beat_id);
    }

    public function test_complete_beat_returns_to_narrator_turn(): void
    {
        [$first] = $this->twoBeatsInOneScene();
        $session = $this->sessionAt(StateNode::BeatComplete, $first);

        $this->machine()->completeBeat($session);

        $this->assertSame(StateNode::NarratorTurn, $session->fresh()->state_node);
    }

    public function test_complete_beat_advances_to_the_next_beat_in_the_same_scene(): void
    {
        [$first, $second] = $this->twoBeatsInOneScene();
        $session = $this->sessionAt(StateNode::BeatComplete, $first);

        $this->machine()->completeBeat($session);

        $this->assertSame($second->id, $session->fresh()->current_beat_id);
    }

    public function test_complete_beat_walks_into_the_next_chapter_in_document_order(): void
    {
        $story = Story::factory()->create();
        $chapterOne = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        $sceneOne = Scene::factory()->create(['chapter_id' => $chapterOne->id, 'number' => 1]);
        $first = Beat::factory()->create(['scene_id' => $sceneOne->id, 'number' => 1]);

        $chapterTwo = Chapter::factory()->create(['story_id' => $story->id, 'number' => 2]);
        $sceneTwo = Scene::factory()->create(['chapter_id' => $chapterTwo->id, 'number' => 1]);
        $next = Beat::factory()->create(['scene_id' => $sceneTwo->id, 'number' => 1]);

        $session = $this->sessionAt(StateNode::BeatComplete, $first->load('scene.chapter'));

        $this->machine()->completeBeat($session);

        $fresh = $session->fresh();
        $this->assertSame($next->id, $fresh->current_beat_id);
        $this->assertSame($chapterTwo->id, $fresh->current_chapter_id);
    }

    public function test_complete_beat_at_the_last_beat_stays_beat_complete(): void
    {
        $session = $this->sessionAt(StateNode::BeatComplete);

        $this->machine()->completeBeat($session);

        $this->assertSame(StateNode::BeatComplete, $session->fresh()->state_node);
    }

    public function test_npc_moment_handoff_is_rejected_this_phase(): void
    {
        $session = $this->sessionAt(StateNode::NarratorTurn);

        $this->expectException(IllegalLoopTransitionException::class);

        $this->machine()->applyHandoff($session, Handoff::NpcMoment);
    }

    public function test_npc_moment_handoff_does_not_move_the_save(): void
    {
        $session = $this->sessionAt(StateNode::NarratorTurn);

        try {
            $this->machine()->applyHandoff($session, Handoff::NpcMoment);
        } catch (IllegalLoopTransitionException) {
            // The branch is not reachable until Phase 2; the save must not move.
        }

        $this->assertSame(StateNode::NarratorTurn, $session->fresh()->state_node);
    }

    public function test_apply_handoff_is_rejected_when_not_on_narrator_turn(): void
    {
        $session = $this->sessionAt(StateNode::PlayerMoment);

        $this->expectException(IllegalLoopTransitionException::class);

        $this->machine()->applyHandoff($session, Handoff::PlayerMoment);
    }

    public function test_begin_is_rejected_when_not_on_session_start(): void
    {
        $session = $this->sessionAt(StateNode::NarratorTurn);

        $this->expectException(IllegalLoopTransitionException::class);

        $this->machine()->begin($session);
    }

    public function test_resume_is_rejected_when_not_on_player_moment(): void
    {
        $session = $this->sessionAt(StateNode::NarratorTurn);

        $this->expectException(IllegalLoopTransitionException::class);

        $this->machine()->resumeFromPlayerMoment($session);
    }

    public function test_complete_beat_is_rejected_when_not_on_beat_complete(): void
    {
        $session = $this->sessionAt(StateNode::NarratorTurn);

        $this->expectException(IllegalLoopTransitionException::class);

        $this->machine()->completeBeat($session);
    }

    public function test_state_machine_drives_a_full_loop_cycle_as_the_only_conductor(): void
    {
        [$first, $second] = $this->twoBeatsInOneScene();
        $session = $this->sessionAt(StateNode::SessionStart, $first);
        $machine = $this->machine();

        // session_start -> narrator_turn
        $machine->begin($session);
        $this->assertSame(StateNode::NarratorTurn, $session->fresh()->state_node);

        // narrator_turn -> player_moment (handoff is the sole branch input)
        $machine->applyHandoff($session, Handoff::PlayerMoment);
        $this->assertSame(StateNode::PlayerMoment, $session->fresh()->state_node);

        // player_moment -> narrator_turn (resumes on the same beat)
        $machine->resumeFromPlayerMoment($session);
        $this->assertSame(StateNode::NarratorTurn, $session->fresh()->state_node);
        $this->assertSame($first->id, $session->fresh()->current_beat_id);

        // narrator_turn -> beat_complete
        $machine->applyHandoff($session, Handoff::BeatComplete);
        $this->assertSame(StateNode::BeatComplete, $session->fresh()->state_node);

        // beat_complete -> narrator_turn at the next beat
        $machine->completeBeat($session);
        $fresh = $session->fresh();
        $this->assertSame(StateNode::NarratorTurn, $fresh->state_node);
        $this->assertSame($second->id, $fresh->current_beat_id);
    }

    private function machine(): SessionStateMachine
    {
        return app(SessionStateMachine::class);
    }

    /**
     * Build a save positioned at a beat and parked on the given node.
     *
     * @param  StateNode  $node  The loop node to place the save on.
     * @param  Beat|null  $beat  The beat to position at; a fresh single beat by default.
     */
    private function sessionAt(StateNode $node, ?Beat $beat = null): PlaySession
    {
        $beat ??= $this->singleBeat();
        $beat->loadMissing('scene.chapter');

        return PlaySession::factory()->create([
            'story_id' => $beat->scene->chapter->story_id,
            'state_node' => $node,
            'current_chapter_id' => $beat->scene->chapter_id,
            'current_scene_id' => $beat->scene_id,
            'current_beat_id' => $beat->id,
        ]);
    }

    /**
     * Author a story with a single chapter/scene/beat and return that beat.
     */
    private function singleBeat(): Beat
    {
        $story = Story::factory()->create();
        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);

        return Beat::factory()->create(['scene_id' => $scene->id, 'number' => 1])->load('scene.chapter');
    }

    /**
     * Author a story with two ordered beats in one scene.
     *
     * @return array{0: Beat, 1: Beat} The first and second beat, in document order.
     */
    private function twoBeatsInOneScene(): array
    {
        $story = Story::factory()->create();
        $chapter = Chapter::factory()->create(['story_id' => $story->id, 'number' => 1]);
        $scene = Scene::factory()->create(['chapter_id' => $chapter->id, 'number' => 1]);
        $first = Beat::factory()->create(['scene_id' => $scene->id, 'number' => 1]);
        $second = Beat::factory()->create(['scene_id' => $scene->id, 'number' => 2]);

        return [$first->load('scene.chapter'), $second];
    }
}
