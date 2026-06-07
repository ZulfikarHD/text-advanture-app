# Saves / Session API Contract (S-2.1.1 / S-2.1.2 / S-2.1.3)

> Per-story **session save** management inside the authoring workspace — start a playthrough by deep-forking a **play-ready** story into the save realm, then manage the independent saves forked from it (name on create, rename, reset, delete) and open a save's **Play** surface, which resumes at the save's persisted loop position. Forking creates one `play_sessions` row at `session_start`, positioned at the story's first beat, and **never mutates the authoring template** (ADR 0012). Each save is independent — managing one never affects a sibling or the template. No relationship edges are seeded this phase (disposition-prior seeding is Phase 5, ADR 0002). All endpoints are auth-gated and owner-scoped; the nested `{playSession}` binds through `Story::playSessions()` via scoped bindings. Governed by ADR 0012/0016. The full Play prose reader is S-5.4.1.

## Routes

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| `GET` | `/stories/{story:slug}/saves` | `stories.saves.index` | `SessionController@index` |
| `POST` | `/stories/{story:slug}/saves` | `stories.saves.store` | `SessionController@store` |
| `PUT` | `/stories/{story:slug}/saves/{playSession}` | `stories.saves.update` | `SessionController@update` |
| `POST` | `/stories/{story:slug}/saves/{playSession}/reset` | `stories.saves.reset` | `SessionController@reset` |
| `DELETE` | `/stories/{story:slug}/saves/{playSession}` | `stories.saves.destroy` | `SessionController@destroy` |
| `GET` | `/stories/{story:slug}/saves/{playSession}/play` | `stories.saves.play` | `SessionController@play` |

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
- **`reset`** and **`destroy`** take **no body**.

## Behaviour

- **`store`** re-checks play-readiness server-side (never trusting the disabled button). When ready, it forks via `SessionService::fork()` inside a transaction (atomic — a mid-fork failure leaves no loadable save), then redirects to `stories.saves.play` for the new save. When not ready, it flashes an error toast and redirects back to `stories.saves.index` (no save created).
- **`update`** renames the save (no uniqueness constraint — two saves may share a label) and redirects to `stories.saves.index`.
- **`reset`** returns the save to its freshly-forked state in a transaction: re-positioned at the first beat, `state_node = session_start`, every loop-state counter cleared (`beat_word_count`/`chapter_word_count = 0`, `nudge_level`/`resume_anchor`/`narrative_clock = null`), `last_played_at` re-stamped. The same id/name are kept, and no sibling save or authoring row is touched.
- **`destroy`** deletes the save (the `play_sessions` FK is `cascadeOnDelete`, so future save-realm children go with it); siblings and the template are untouched.
- **`play`** *is* the load-as-resume path (S-2.1.3): it stamps `last_played_at` (so the save sorts most-recent and "continue where I left off" is accurate) and renders the save at its **persisted** loop position — never reset to the beat start.
- **`index`** lists existing saves most-recently-played first, even if the story has since drifted out of readiness; only the Start action is gated.

## Flash / toast

| Action | Type | Message |
|--------|------|---------|
| Start a session (success) | `success` | "Session started." |
| Start a session (not play-ready) | `error` | "This story is not play-ready yet — finish the requirements on its overview first." |
| Rename a save | `success` | "Save renamed." |
| Reset a save | `success` | "Save reset to its starting position." |
| Delete a save | `success` | "Save deleted." |

## Ownership & authorization

- `StoryPolicy` (extending `OwnerPolicy`) gates by ownership: `view` for `index`/`play`, `update` for `store`/`update`/`reset`/`destroy`.
- Saves write only to the save realm (`play_sessions`); the authoring template is immutable at runtime (ADR 0012), so the story is never mutated by forking, resetting, or play.

## Out of scope

- **The Play reader** — narrated prose, scrollback, advance/pause controls (S-5.4.1). `sessions/Play` is a reachable placeholder this phase.
- **Loop advancement has no HTTP surface yet.** The `state_node` transitions are built (`SessionStateMachine`, S-3.1.1) but are **service-level only** this phase — there is **no route** to advance the loop. The advance/pause endpoint lands with the Play reader (S-5.4.1), and the handoff that drives the transition comes from the prose call (S-4.2.1). The remaining persisted columns are written by play later: `resume_anchor` content by the narrator turn (S-5.3.1), word/nudge/clock counters in Phase 4 (PH-37).
- **Edge seeding** — disposition-prior relationship edges on fork (Phase 5, ADR 0002).
