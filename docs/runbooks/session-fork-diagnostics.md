# Session Fork Diagnostics (S-2.1.1)

Operational playbook for **starting a session** — forking a story into a save. Use this when "Start session" does nothing, a save lands at the wrong position, or a Play link 404s. Backed by `SessionService::fork()` + `SessionController` (see [../api/saves.md](../api/saves.md) and [../architecture/Diagrams/Engine/Session_Fork_Flow.md](../architecture/Diagrams/Engine/Session_Fork_Flow.md)).

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
| Opening a save **404s** | The `{playSession}` doesn't belong to the `{story}` in the URL (scoped binding), or the story isn't owned by the signed-in user | This is correct isolation — `play_sessions` has no `user_id`; it is reached only through its owner-scoped story. Navigate via the Saves list, never a hand-typed URL. |
| Two saves share the name "Playthrough N" | Names are auto-derived (`count + 1`) with no unique constraint this phase | Harmless until rename ships (S-2.1.2). |

## Verify a fork from tinker (read-only)

```bash
php artisan tinker --execute '
  $s = App\Models\Story::firstWhere("slug", "your-slug");
  dump(app(App\Services\StoryOverviewService::class)->readiness($s)["ready"]);
  dump($s->playSessions()->latest("id")->first()?->only(["id","name","state_node","current_chapter_id","current_scene_id","current_beat_id"]));
'
```

## Atomicity note

The fork is wrapped in `DB::transaction`. Today it is a single insert, but the wrapper is the seam where Phase 5 adds disposition-prior edge seeding **inside the same transaction** — a mid-fork failure must roll back to leave **no** half-seeded, loadable save. `tests/Feature/Sessions/SessionForkTest.php::test_fork_is_atomic_when_a_step_fails` proves the rollback.

## Out of scope (later stories)

- Load / reset / delete / rename a save — **S-2.1.2**.
- Resume from the exact loop position — **S-2.1.3**.
- The actual Play reader (prose, scrollback, advance/pause) — **S-5.4.1** (the current Play surface is a placeholder, PH-36).
