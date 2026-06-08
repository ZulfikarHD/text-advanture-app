# Saves / Session API Contract (S-2.1.1 / S-2.1.2 / S-2.1.3 / S-4.2.1 / S-5.1.1 / S-5.4.1)

> Per-story **session save** management plus the **playable loop** inside the workspace — start a playthrough by deep-forking a **play-ready** story into the save realm, manage the independent saves forked from it (name on create, rename, reset, delete), and open a save's **Writing/Play** page, which resumes at the save's persisted loop position and drives the narrator → me → narrator loop: the narrator advances (`narrate`, S-4.2.1), the player writes back (`input`, S-5.1.1), and the player closes a finished beat (`continue`, S-3.1.2). Forking creates one `play_sessions` row at `session_start`, positioned at the story's first beat, and **never mutates the authoring template** (ADR 0012). Each save is independent — managing one never affects a sibling or the template. No relationship edges are seeded this phase (disposition-prior seeding is Phase 5, ADR 0002). All endpoints are auth-gated and owner-scoped; the nested `{playSession}` binds through `Story::playSessions()` via scoped bindings. Governed by ADR 0012/0016.

## Routes

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| `GET` | `/stories/{story:slug}/saves` | `stories.saves.index` | `SessionController@index` |
| `POST` | `/stories/{story:slug}/saves` | `stories.saves.store` | `SessionController@store` |
| `PUT` | `/stories/{story:slug}/saves/{playSession}` | `stories.saves.update` | `SessionController@update` |
| `POST` | `/stories/{story:slug}/saves/{playSession}/reset` | `stories.saves.reset` | `SessionController@reset` |
| `DELETE` | `/stories/{story:slug}/saves/{playSession}` | `stories.saves.destroy` | `SessionController@destroy` |
| `GET` | `/stories/{story:slug}/saves/{playSession}/play` | `stories.saves.play` | `SessionController@play` |
| `POST` | `/stories/{story:slug}/saves/{playSession}/narrate` | `stories.saves.narrate` | `SessionController@narrate` |
| `POST` | `/stories/{story:slug}/saves/{playSession}/input` | `stories.saves.input` | `SessionController@input` |
| `POST` | `/stories/{story:slug}/saves/{playSession}/continue` | `stories.saves.continue` | `SessionController@continueBeat` |

- `{story:slug}` resolves under the `OwnerScope` global scope — a foreign story is **404**, never leaked.
- `{playSession}` resolves via **scoped bindings** (`->scopeBindings()`) through `Story::playSessions()`: a save from another story (or owner) is **404**. `PlaySession` carries no `user_id`; isolation is transitive through the owner-scoped story.
- All write routes are throttled (`throttle:30,1`).

## Inertia props

### `stories/Saves` (index)

| Prop | Type | Notes |
|------|------|-------|
| `story` | `StoryRef` | The owner-scoped story whose workspace is open |
| `readiness` | `Readiness` | The same play-readiness gate the Overview renders; gates the Start button |
| `saves` | `SaveItem[]` | The saves forked from this story, most-recently-played first |

### `sessions/Play` (play)

| Prop | Type | Notes |
|------|------|-------|
| `story` | `StoryRef` | The owner-scoped story the save belongs to |
| `save` | `SaveItem` | The save (with its authoring position) the player landed on |
| `timeline` | `TimelineEvent[]` | The save's scene log in order — the readable prose scrollback (S-5.4.1) |
| `codex` | `Codex` | The book's cast + world facts for the read-only codex rail (never feeds a prompt) |
| `flow` | `Flow` | Whose turn it is, so the page shows exactly one turn control |

```typescript
type TimelineEvent = {
    id: number;
    type: string;            // EventType value, e.g. "narration" | "player_input"
    content: string;
    speaker: string | null;  // the character name for a player/NPC line, else null
    createdAt: string | null; // ISO-8601 (UTC)
};

type Codex = {
    characters: { id: number; name: string; slug: string; isPlayer: boolean }[];
    lore: { id: number; title: string | null }[];
};

type Flow = {
    state: string;            // StateNode value, e.g. "player_moment"
    awaitingNarrator: boolean; // session_start | narrator_turn
    awaitingPlayer: boolean;   // player_moment
    atBeatBoundary: boolean;   // beat_complete with a next beat to continue to
    ended: boolean;            // beat_complete with no next beat (end of story, PH-38)
};
```

```typescript
type StoryRef = { id: number; slug: string; title: string };

type Requirement = { key: string; label: string; met: boolean; detail: string };
type Readiness = { ready: boolean; requirements: Requirement[] };

type SaveItem = {
    id: number;
    name: string;            // author-supplied on create, or auto "Playthrough N"
    stateNode: string;       // StateNode value, e.g. "session_start"
    stateLabel: string;      // human label, e.g. "Session start"
    lastPlayedAt: string | null; // ISO-8601 (UTC); rendered WIB on the client
    resumeAnchor: Record<string, unknown> | null; // null until a narrator turn writes it (S-5.3.1)
    position: {
        chapterNumber: number | null;
        chapterTitle: string | null;
        sceneNumber: number | null;
        beatGoal: string | null;
    };
};
```

## Request bodies

- **`store`** takes an optional `{ name?: string (max 150) }`. When blank/omitted, `SessionService::fork()` derives the default `Playthrough N`.
- **`update`** (rename) takes `{ name: string (required, max 150) }`.
- **`input`** takes `{ content: string (required, max 5000) }` — the player's written contribution (`SubmitPlayerInputRequest`).
- **`reset`**, **`destroy`**, **`narrate`**, and **`continue`** take **no body**.

## Behaviour

- **`store`** re-checks play-readiness server-side (never trusting the disabled button). When ready, it forks via `SessionService::fork()` inside a transaction (atomic — a mid-fork failure leaves no loadable save), then redirects to `stories.saves.play` for the new save. When not ready, it flashes an error toast and redirects back to `stories.saves.index` (no save created).
- **`update`** renames the save (no uniqueness constraint — two saves may share a label) and redirects to `stories.saves.index`.
- **`reset`** returns the save to its freshly-forked state in a transaction: re-positioned at the first beat, `state_node = session_start`, every loop-state counter cleared (`beat_word_count`/`chapter_word_count = 0`, `nudge_level`/`resume_anchor`/`narrative_clock = null`), `last_played_at` re-stamped. The same id/name are kept, and no sibling save or authoring row is touched.
- **`destroy`** deletes the save (the `play_sessions` FK is `cascadeOnDelete`, so future save-realm children go with it); siblings and the template are untouched.
- **`play`** *is* the load-as-resume path (S-2.1.3): it stamps `last_played_at` (so the save sorts most-recent and "continue where I left off" is accurate) and renders the save at its **persisted** loop position — never reset to the beat start.
- **`narrate`** (S-4.2.1 / S-4.2.2) runs **one narrator turn** via `NarratorTurnService`: it assembles the narrator prompt, runs a single structured prose call (`prose · handoff · elapsed_bucket`), and on a **validated** result advances the loop spine by the handoff (entering the loop first if the save is at `session_start`), persists the prose/handoff to the `events` scene log (`SceneLogService::recordNarration`, S-5.2.1), then redirects to `stories.saves.play` with a success toast. The call runs **before** any transition, so a malformed or failed call (retried to the bound, then `Failed`) leaves the save **exactly unchanged** and flashes an error toast — the loop never trusts an unparseable result. Narrating off-turn (the save is not at `session_start`/`narrator_turn`) is rejected without spending a call. Valid only from `session_start` or `narrator_turn`; gated by `update`. The reachable advance control + prose reader render in the Play page (S-5.4.1).
- **`input`** (S-5.1.1) commits the player's contribution at a player moment via `SessionService::recordPlayerMoment()`: inside **one `DB::transaction`** it hands the turn back to the narrator (`resumeFromPlayerMoment`: `player_moment → narrator_turn`, same beat) and appends the input to the `events` scene log (`recordPlayerInput`). The node guard runs first, so acting off-turn throws `IllegalLoopTransitionException` and rolls the whole moment back — the save stays on `player_moment` with nothing recorded, surfaced as an error toast. A success redirects to `stories.saves.play`. Valid only from `player_moment`; gated by `update`.
- **`continue`** (S-3.1.2) closes a finished beat at a beat boundary via `SessionStateMachine::completeBeat()`: it advances to the next beat in document order and hands back to the narrator, or holds on `beat_complete` at the end of the story when none remains. Continuing off a beat boundary is rejected with an error toast. Valid only from `beat_complete`; gated by `update`.
- **`index`** lists existing saves most-recently-played first, even if the story has since drifted out of readiness; only the Start action is gated.

## Flash / toast

| Action | Type | Message |
|--------|------|---------|
| Start a session (success) | `success` | "Session started." |
| Start a session (not play-ready) | `error` | "This story is not play-ready yet — finish the requirements on its overview first." |
| Rename a save | `success` | "Save renamed." |
| Reset a save | `success` | "Save reset to its starting position." |
| Delete a save | `success` | "Save deleted." |
| Narrate a turn (success) | `success` | "The narrator advanced the scene." |
| Narrate — call failed / malformed (S-4.2.2) | `error` | "The narrator was interrupted and its turn could not be read. Your save is unchanged — try again." |
| Narrate — not the narrator's turn | `error` | "It is not the narrator's turn right now." |
| Narrate — no narrator model configured | `error` | "No narrator model is configured yet — set one under Settings → Model roles first." |
| Submit player input (success) | `success` | "Your turn is in — the narrator takes it from here." |
| Submit player input — off-turn | `error` | "It is not your turn to act right now." |
| Continue past a beat (success) | `success` | "On to the next beat." |
| Continue at the final beat (success) | `success` | "You have reached the end of the story." |
| Continue — not at a beat boundary | `error` | "There is no beat to continue from right now." |

## Ownership & authorization

- `StoryPolicy` (extending `OwnerPolicy`) gates by ownership: `view` for `index`/`play`, `update` for `store`/`update`/`reset`/`destroy`/`narrate`/`input`/`continue`.
- Saves write only to the save realm (`play_sessions`); the authoring template is immutable at runtime (ADR 0012), so the story is never mutated by forking, resetting, or play.

## Out of scope

- **Bounded immediate context + scene summary.** The scene log records raw `events`; the ~2000-token window and scene-summary compaction at SCENE_DONE are the rest of **S-5.2.1** (E5.2).
- **`elapsed_bucket` + resume anchor + clocks.** `narrate` returns `elapsed_bucket` but it is consumed nowhere until decay (Phase 5, PH-40); `resume_anchor` content (S-5.3.1) and the word/nudge/clock counters (Phase 4) still have no producer (PH-37).
- **Two-layer record + sourced delivery + NPC turns.** Player input is stored as plain prose in the scene log this phase; sourced delivery and the surface/`true_state` record arrive in Phase 2, where an NPC witnesses the player's surface.
- **Edge seeding** — disposition-prior relationship edges on fork (Phase 5, ADR 0002).
