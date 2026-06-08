# Narrator Turn Diagnostics (S-4.2.1 / S-4.2.2)

Operational playbook for the **narrator prose call** — the structured turn that produces `prose · handoff · elapsed_bucket` and advances the loop spine. Use this when a narrator turn surfaces an **error toast** ("The narrator was interrupted…"), the loop won't advance, a turn is rejected as off-turn, or you need to read why a call was recorded `Failed`. Backed by [`NarratorTurnService`](../../app/Services/Narrator/NarratorTurnService.php) + [`SessionController@narrate`](../../app/Http/Controllers/Stories/SessionController.php) + [`OpenRouterClient`](../../app/Services/Llm/OpenRouterClient.php) (see [../api/saves.md](../api/saves.md), [../architecture/Diagrams/Engine/Narrator_Turn_Prose_Call.md](../architecture/Diagrams/Engine/Narrator_Turn_Prose_Call.md), and [../architecture/Diagrams/Engine/Llm_Client_Flow.md](../architecture/Diagrams/Engine/Llm_Client_Flow.md)).

## What a narrator turn does

1. `POST /stories/{slug}/saves/{playSession}/narrate` → `SessionController@narrate` → `Gate::authorize('update', $story)`.
2. `NarratorTurnService::run()` guards the node (`session_start`/`narrator_turn` only), assembles the prompt (S-4.1.1), resolves the `narrator_prose` model, and runs **one** `completeStructured` call against `NarratorProseSchema`.
3. On a **validated** result it advances the spine (`begin` if at `session_start`, then `applyHandoff`) and flashes a success toast. On failure it flashes an error toast and leaves the save unchanged. Either way it redirects to Play.

The call runs **before** any state change, so a failed/malformed turn never advances the loop.

> **Reachable from the Play page (S-5.4.1).** The Writing/Play page's turn control `POST`s this endpoint ("Begin the scene" / "Continue") when it is the narrator's turn; the player half is the player moment (`input`, S-5.1.1 — see below).

## Symptom → cause → fix

| Symptom | Likely cause | Triage |
|---------|--------------|--------|
| Error toast **"The narrator was interrupted and its turn could not be read."** | The prose call failed or never conformed within the retry bound (`LlmStructuredOutputException`/`LlmCallFailedException`) — provider 429/5xx, a network error, or malformed/out-of-vocabulary output | Expected fail-closed behavior: the save is **unchanged** and loadable — just retry. To see *why*, read the latest `Failed` `llm_calls` row for the save (below). If it repeats, check provider status + the model slug, and enable message logging (below) to inspect the raw content. |
| Error toast **"It is not the narrator's turn right now."** | `narrate` was called when the save is at `player_moment` / `beat_complete` (an `IllegalLoopTransitionException`) | Expected. A narrator turn is valid only from `session_start` or `narrator_turn`. Resume the player input (`player_moment`) or close the beat (`beat_complete`) first — the narrator doesn't run from those nodes this phase. |
| Error toast **"No narrator model is configured yet…"** | `ModelRoleResolver` found no `narrator_prose` profile (`UnresolvedModelRoleException`) | Seed/confirm the global `model_profiles` (`GlobalLibrarySeeder`) or set a `narrator_prose` model under **Settings → Model roles** (or a per-story override under the story's **Settings**). |
| The turn fails with **no provider key** | The owner has no stored `provider_credentials` row | Add the key under **Settings → Provider** (it's stored encrypted, per-owner). The client authenticates with the **owner's** key, never a shared env key (PH-18). |
| A `Failed` row exists but the `handoff` "looked fine" | The model returned `npc_moment` (or another value outside the schema enum) | Expected this phase: the `handoff` enum is **narrowed to `player_moment` / `beat_complete`** — `npc_moment` is out of vocabulary, so it is non-conforming and retried then surfaced (its branch lights up in Phase 2). Not a bug. |
| The turn `404`s | The `{playSession}` doesn't belong to the `{story}` (scoped binding), or the story isn't owned by the signed-in user | Correct isolation — navigate via the save, never a hand-typed URL. `play_sessions` carries no `user_id`; it is reached only through its owner-scoped story. |
| Many `Failed` rows in a row, all retried twice | The model consistently returns malformed/non-conforming JSON, or the provider is degraded | Inspect the raw `messages`/response by enabling `services.openrouter.log_messages` (below), then re-run. Consider a stronger `narrator_prose` slug. The retry **bound** is `services.openrouter.max_retries`. |

## Player moment (S-5.1.1)

The player half of the loop: `POST /stories/{slug}/saves/{playSession}/input` → `SessionController@input` → `Gate::authorize('update', $story)` → `SessionService::recordPlayerMoment()`. Inside **one `DB::transaction`** it hands the turn back (`resumeFromPlayerMoment`: `player_moment → narrator_turn`, same beat) and appends the input to the `events` scene log. The `continue` endpoint (`SessionController@continueBeat`) closes a finished beat at a beat boundary.

| Symptom | Likely cause | Triage |
|---------|--------------|--------|
| Error toast **"It is not your turn to act right now."** | `input` was `POST`ed when the save is not on `player_moment` (an `IllegalLoopTransitionException`) | Expected. Input is valid only at a player moment. The narrator must run first (`narrate`) and hand off with `player_moment`. The save is **unchanged** and nothing is recorded (the transaction rolled back before any write). |
| Validation error on **content** | Empty input, or longer than `max:5000` (`SubmitPlayerInputRequest`) | Write something before sending; the composer mirrors the cap with a live counter and disables Send past 5000. |
| Input "submitted" but **no `events` row** and the save still on `player_moment` | A failure mid-transaction (e.g. a DB error in `recordPlayerInput`) rolled the whole moment back | This is the atomicity guarantee, not a bug: the turn is never handed back without the input. Retry; if it repeats, inspect the DB error in the app log. |
| Error toast **"There is no beat to continue from right now."** | `continue` was `POST`ed when the save is not on `beat_complete` | Expected. "Continue" is the beat-boundary action only. |
| The story **won't advance past the last beat** | `completeBeat` holds on `beat_complete` when no next beat exists (terminal, PH-38) | Expected end-of-story this phase. The Play page shows the "end of the story" state; boundary/loop-exit subsystems arrive in Phase 4. |

## Read the failed call from tinker (read-only)

```bash
php artisan tinker --execute '
  $p = App\Models\PlaySession::firstWhere("id", 1);
  App\Models\LlmCall::where("session_id", $p->id)
    ->where("role", "narrator_prose")
    ->latest("id")->take(5)->get()
    ->each(fn ($c) => dump($c->only(["id","status","model_slug","error","latency_ms","created_at"])));
'
```

> Full request/response **message bodies** persist only when `services.openrouter.log_messages` is enabled (they are `#[Hidden]` and save-realm-sensitive). Turn it on in a non-prod env to inspect the raw content that failed validation, then turn it back off.

## Confirm a turn would resolve (read-only)

```bash
php artisan tinker --execute '
  $p = App\Models\PlaySession::firstWhere("id", 1);
  dump($p->only(["id","state_node"]));   // must be session_start or narrator_turn
  $story = $p->story()->first();
  dump(app(App\Services\Llm\ModelRoleResolver::class)
    ->resolve(App\Enums\LlmRole::NarratorProse, $story)
    ?->only(["model_slug","scope"]));
  dump(app(App\Services\ProviderCredentialService::class)->for($story->owner()->first()) !== null);
'
```

## Retry / backoff config

- `services.openrouter.max_retries` — attempts past the first (so `1` ⇒ 2 attempts total). Applies to 429/5xx, connection errors, **and** malformed/non-conforming structured output.
- Backoff is exponential between attempts; tests `Sleep::fake()` it so it's instant.
- When the bound is exhausted (or the failure is non-retryable), the client records a `Failed` `llm_calls` row and throws — the turn surfaces, the loop stays put.

## Out of scope (later stories)

- **`elapsed_bucket` consumption** by decay — Phase 5 (PH-40); the turn returns it but nothing consumes it yet.
- **The resume anchor** content (S-5.3.1) and **word/nudge/clock** counters (Phase 4).
- **The bounded immediate-context window + scene summary** at SCENE_DONE — S-5.2.1 remainder (the raw `events` are recorded now).
- **The recorder sub-call** + the **`npc_moment`** branch + **sourced delivery / two-layer record** (Phase 2).
