# Session Save Diagnostics (S-2.1.1 / S-2.1.2 / S-2.1.3 / S-3.1.1)

Operational playbook for the **save realm** — forking a story into a save, then naming, renaming, resetting, deleting, and resuming saves, plus the **state-machine spine** that advances a save through the narrator loop. Use this when "Start session" does nothing, a save lands at the wrong position, a manage action fails, a Play link 404s, or a loop transition throws. Backed by `SessionService` + `SessionController` + `SessionStateMachine` (see [../api/saves.md](../api/saves.md), [../architecture/Diagrams/Engine/Session_Fork_Flow.md](../architecture/Diagrams/Engine/Session_Fork_Flow.md), and [../architecture/Diagrams/Engine/Session_State_Machine.md](../architecture/Diagrams/Engine/Session_State_Machine.md)).

## What "start a session" does

1. `POST /stories/{slug}/saves` → `SessionController@store` → `Gate::authorize('update', $story)`.
2. `SessionService::fork()` re-checks play-readiness, then in **one transaction** inserts a `play_sessions` row at `state_node = session_start`, positioned at the first beat (document order), `last_played_at = now()`.
3. Redirects to `GET /stories/{slug}/saves/{playSession}/play`.

No relationship edges (or other save-realm children) are created — that is the Phase-5 seam (ADR 0002).

## Symptom → cause → fix

| Symptom | Likely cause | Triage |
|---------|--------------|--------|
| "Start session" is **disabled** with a "Not yet playable" panel | Story fails the play-readiness gate (needs ≥ 1 character, ≥ 1 beat, a resolvable model for every engine role) | Follow the panel's **Review readiness** link to the Overview; satisfy the listed requirements. Confirm `model_profiles` are seeded (`GlobalLibrarySeeder`). |
| POST returns and shows an **error toast** ("not play-ready yet") with no save created | The server-side re-check failed (the client button was stale, e.g. a character/beat was deleted in another tab) | Expected fail-closed behavior — refresh the Saves page; the Start button reflects current readiness. No row is written (the gate throws `StoryNotPlayableException` before the insert). |
| A save appears but at the **wrong chapter** | The first beat in document order isn't where you expect — chapter 1 may have no beats | The fork positions at the earliest beat ordered by `chapter.number → scene.number → beat.number`. Check the Structure tab's ordering; add a beat earlier if needed. |
| Opening a save **404s**, or a rename/reset/delete **404s** | The `{playSession}` doesn't belong to the `{story}` in the URL (scoped binding), or the story isn't owned by the signed-in user | This is correct isolation — `play_sessions` has no `user_id`; it is reached only through its owner-scoped story. Navigate via the Saves list, never a hand-typed URL. |
| Two saves share the name "Playthrough N" | Names carry **no** unique constraint (auto-default is `count + 1`); two saves may legitimately share a label | Harmless by design. Rename either via the pencil action to disambiguate. |
| **Reset** seems to do nothing | Reset returns the save to its *freshly-forked* state — `session_start`, first beat, counters cleared. If the save was already fresh, nothing visibly changes except `last_played_at` | Expected. Reset is confirmed in the UI first; it only ever touches the one save (never a sibling or the template). |
| **Resume** restarts the beat instead of continuing | `resume_anchor` is null because no narrator turn has written one yet (the loop engine is later, PH-37) | Expected this phase — loading restores the persisted `state_node` + position, but seamless mid-beat continuation needs the resume anchor (S-5.3.1). |
| Save list order looks stale | The list sorts by `last_played_at` desc then `id` desc; loading a save (opening Play) or resetting it re-stamps `last_played_at` | Expected — the most-recently-opened save floats to the top. |

## Verify a fork from tinker (read-only)

```bash
php artisan tinker --execute '
  $s = App\Models\Story::firstWhere("slug", "your-slug");
  dump(app(App\Services\StoryOverviewService::class)->readiness($s)["ready"]);
  dump($s->playSessions()->latest("id")->first()?->only(["id","name","state_node","current_chapter_id","current_scene_id","current_beat_id"]));
'
```

## Verify save management from tinker (read-only)

```bash
php artisan tinker --execute '
  $s = App\Models\Story::firstWhere("slug", "your-slug");
  $s->playSessions()->orderByDesc("last_played_at")->get()
    ->each(fn ($p) => dump($p->only(["id","name","state_node","current_beat_id","last_played_at"])));
'
```

## Loop transitions (S-3.1.1)

`SessionStateMachine` is the only conductor of the narrator-loop spine. It is backend-only this phase: there is **no route** yet (the Play advance UI is S-5.4.1) and the `Handoff` is **injected** by the caller (the prose-call producer is S-4.2.1), so today the transitions are exercised by tests, not by play. The spine touches only `state_node` + the `current_*` position.

| Symptom | Likely cause | Triage |
|---------|--------------|--------|
| An `IllegalLoopTransitionException` ("Cannot run loop transition [X] from state node [Y]") | A transition was called from the wrong node — `begin` off `session_start`, `applyHandoff` off `narrator_turn`, `resumeFromPlayerMoment` off `player_moment`, or `completeBeat` off `beat_complete` | Correct fail-closed behavior: the spine never advances from an unexpected node. Check the save's `state_node` and call the matching transition. |
| An `IllegalLoopTransitionException` naming `npc_moment` | `applyHandoff()` received `Handoff::NpcMoment` — its branch is **not reachable until Phase 2** | Expected this phase. The narrator must hand off only `player_moment` or `beat_complete`; `npc_moment` lights up when live NPCs arrive (Phase 2). The save is left unmoved. |
| `completeBeat` doesn't advance the position | The save is on the **last** beat in document order, so there is no next beat — it holds on `beat_complete` (terminal, PH-38) | Expected end-of-story behavior this phase. The boundary/loop-exit subsystem is Phase 4. Confirm the Structure ordering if you expected a later beat. |
| `completeBeat` lands on an unexpected beat | "Next" is resolved by `BeatSequence` ordered `chapter.number → scene.number → beat.number`, **not** by row id | Check the Structure tab's numbering — the next beat is the next ordinal tuple across scene/chapter, the same order the fork uses. |

### Verify the spine from tinker (read-only)

```bash
php artisan tinker --execute '
  $p = App\Models\PlaySession::firstWhere("id", 1);
  dump($p?->only(["id","state_node","current_chapter_id","current_scene_id","current_beat_id"]));
  dump(app(App\Services\BeatSequence::class)->next($p->currentBeat)?->only(["id","number"]));
'
```

## Atomicity note

The fork **and reset** are wrapped in `DB::transaction`. Today each is a single write, but the wrapper is the seam where Phase 5 adds disposition-prior edge seeding (fork) and clear/reseed + child cleanup (reset) **inside the same transaction** — a mid-operation failure must roll back to leave **no** half-seeded, loadable state. `tests/Feature/Sessions/SessionForkTest.php::test_fork_is_atomic_when_a_step_fails` proves the rollback.

## Out of scope (later stories)

- The remaining loop-state **producers** that feed the spine — the handoff producer (the prose call, **S-4.2.1**), the resume anchor (**S-5.3.1**), and the word/nudge clocks (Phase 4). The spine (S-3.1.1) advances `state_node`/position now, but consumes an injected handoff; nothing produces it in normal play yet (**PH-37**).
- The actual Play reader + advance/pause controls that *call* the spine — **S-5.4.1** (the current Play surface is a placeholder, PH-36).
- The boundary/loop-exit subsystem when the last beat closes — Phase 4 (**PH-38**).
