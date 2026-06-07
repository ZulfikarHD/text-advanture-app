# Saves / Session API Contract (S-2.1.1)

> Per-story **session fork** inside the authoring workspace — start a playthrough by deep-forking a **play-ready** story into the save realm, list the saves forked from it, and open a save's **Play** surface. Forking creates one `play_sessions` row at `session_start`, positioned at the story's first beat, and **never mutates the authoring template** (ADR 0012). No relationship edges are seeded this phase (disposition-prior seeding is Phase 5, ADR 0002). All endpoints are auth-gated and owner-scoped; the nested `{playSession}` binds through `Story::playSessions()` via scoped bindings. Governed by ADR 0012/0016. Multi-save management (rename/load/reset/delete, S-2.1.2) and loop-state resume (S-2.1.3) are later; the full Play reader is S-5.4.1.

## Routes

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| `GET` | `/stories/{story:slug}/saves` | `stories.saves.index` | `SessionController@index` |
| `POST` | `/stories/{story:slug}/saves` | `stories.saves.store` | `SessionController@store` |
| `GET` | `/stories/{story:slug}/saves/{playSession}/play` | `stories.saves.play` | `SessionController@play` |

- `{story:slug}` resolves under the `OwnerScope` global scope — a foreign story is **404**, never leaked.
- `{playSession}` resolves via **scoped bindings** (`->scopeBindings()`) through `Story::playSessions()`: a save from another story (or owner) is **404**. `PlaySession` carries no `user_id`; isolation is transitive through the owner-scoped story.
- The fork route is throttled (`throttle:30,1`).

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
    name: string;            // auto-named "Playthrough N" this phase (rename: S-2.1.2)
    stateNode: string;       // StateNode value, e.g. "session_start"
    stateLabel: string;      // human label, e.g. "Session start"
    lastPlayedAt: string | null; // ISO-8601 (UTC); rendered WIB on the client
    position: {
        chapterNumber: number | null;
        chapterTitle: string | null;
        sceneNumber: number | null;
        beatGoal: string | null;
    };
};
```

## Request bodies

- **`store`** takes **no body** — the fork is derived entirely from the (server-authorized) story. The name is auto-generated (`Playthrough N`).

## Behaviour

- **`store`** re-checks play-readiness server-side (never trusting the disabled button). When ready, it forks via `SessionService::fork()` inside a transaction (atomic — a mid-fork failure leaves no loadable save), then redirects to `stories.saves.play` for the new save. When not ready, it flashes an error toast and redirects back to `stories.saves.index` (no save created).
- **`index`** lists existing saves even if the story has since drifted out of readiness; only the Start action is gated.

## Flash / toast

| Action | Type | Message |
|--------|------|---------|
| Start a session (success) | `success` | "Session started." |
| Start a session (not play-ready) | `error` | "This story is not play-ready yet — finish the requirements on its overview first." |

## Ownership & authorization

- `StoryPolicy` (extending `OwnerPolicy`) gates by ownership: `view` for `index`/`play`, `update` for the `store` fork.
- Forking writes only to the save realm (`play_sessions`); the authoring template is immutable at runtime (ADR 0012), so the story is never mutated by play.

## Out of scope

- **Multi-save management** — naming on create, load, reset, delete (S-2.1.2).
- **Loop-state persistence / resume** — restoring the exact position and continuing from the resume anchor (S-2.1.3).
- **The Play reader** — narrated prose, scrollback, advance/pause controls (S-5.4.1). `sessions/Play` is a reachable placeholder this phase.
- **Edge seeding** — disposition-prior relationship edges on fork (Phase 5, ADR 0002).
