# Session Fork & Save Lifecycle Flow (S-2.1.1 / S-2.1.2 / S-2.1.3)

> Request flow for **starting and managing playthroughs** — the save-realm surface. From a story's **Saves** tab the author starts a session, which forks a **play-ready** story into the save realm: one `play_sessions` row at `session_start`, positioned at the story's first beat. The fork **references** the immutable authoring rows by FK rather than copying structure (ADR 0012), seeds **no** `relationship_edges` (Phase 5, ADR 0002), and is wrapped in a single transaction so a mid-fork failure leaves no loadable save. The author can then name, rename, **reset** (back to the freshly-forked state), and delete saves independently (S-2.1.2), and **load** a save to resume it at its persisted loop position (S-2.1.3). The full prose reader is S-5.4.1.

```mermaid
sequenceDiagram
    participant U as Player (Browser)
    participant I as Inertia
    participant C as SessionController
    participant O as StoryOverviewService
    participant S as SessionService
    participant DB as MariaDB

    Note over U,DB: INDEX (Saves tab)
    U->>I: Open story -> Saves tab
    I->>C: GET /stories/{slug}/saves
    C->>C: authorize('view', story)
    C->>O: readiness(story)
    C->>DB: SELECT play_sessions (with current chapter/scene/beat), latest first
    C-->>I: Inertia::render('stories/Saves', { story, readiness, saves[] })
    Note right of U: Start button is gated on readiness.ready

    Note over U,DB: START (fork) — re-checked server-side
    U->>I: "Start session" -> POST (no body)
    I->>C: POST /stories/{slug}/saves
    C->>C: authorize('update', story)
    C->>S: fork(story)
    S->>O: readiness(story)['ready']
    alt not play-ready
        S-->>C: throw StoryNotPlayableException
        C-->>I: error toast + redirect saves.index (no save created)
    else play-ready
        S->>DB: BEGIN
        S->>DB: SELECT first beat (ORDER BY chapter.number, scene.number, beat.number)
        S->>DB: INSERT play_sessions { state_node=session_start, current_*_id, name=author or "Playthrough N", last_played_at=now }
        Note right of S: Phase 5 seam — disposition-prior edges seed here, same tx. No edges this phase.
        S->>DB: COMMIT
        S-->>C: PlaySession
        C-->>I: success toast + redirect saves.play
        I->>C: GET /stories/{slug}/saves/{playSession}/play
        C->>C: authorize('view', story); scoped binding through Story::playSessions()
        C-->>I: Inertia::render('sessions/Play', { story, save })
    end
```

## Manage & resume saves (S-2.1.2 / S-2.1.3)

Once a save exists the Saves tab manages it; each action authorizes `update` on the parent story and resolves `{playSession}` through the scoped binding, then redirects back to the index. Destructive actions (reset/delete) are confirmed in the UI first (`useConfirm`, never a native dialog).

```mermaid
sequenceDiagram
    participant U as Player (Browser)
    participant C as SessionController
    participant S as SessionService
    participant DB as MariaDB

    Note over U,DB: RENAME (S-2.1.2)
    U->>C: PUT /saves/{playSession} { name }
    C->>S: rename(save, name)
    S->>DB: UPDATE play_sessions SET name
    C-->>U: redirect index + "Save renamed."

    Note over U,DB: RESET (S-2.1.2) — confirmed in UI
    U->>C: POST /saves/{playSession}/reset
    C->>S: reset(save)
    S->>DB: BEGIN
    S->>DB: SELECT first beat (document order)
    S->>DB: UPDATE play_sessions SET state_node=session_start, current_*_id, counters=0, resume_anchor/narrative_clock/nudge=null, last_played_at=now
    Note right of S: Phase 5 seam — clear + reseed edges / delete save-realm children here, same tx
    S->>DB: COMMIT
    C-->>U: redirect index + "Save reset to its starting position."

    Note over U,DB: DELETE (S-2.1.2) — confirmed in UI
    U->>C: DELETE /saves/{playSession}
    C->>S: delete(save)
    S->>DB: DELETE play_sessions (FK cascade → future children)
    C-->>U: redirect index + "Save deleted."

    Note over U,DB: LOAD → RESUME (S-2.1.3)
    U->>C: GET /saves/{playSession}/play
    C->>S: resume(save)
    S->>DB: UPDATE play_sessions SET last_played_at=now
    C-->>U: render Play at the PERSISTED state_node + position (never reset to beat start)
```

Each save is independent: rename/reset/delete touch only the addressed row, never a sibling or the authoring template. Reset re-uses the same first-beat resolution as the fork, so a reset save lands exactly where a fresh fork would.

## Ownership & scoping

The parent `{story:slug}` resolves under `OwnerScope` (foreign story → 404 on binding). The nested `{playSession}` uses `->scopeBindings()`, so Laravel scopes the lookup through `Story::playSessions()` — a save from another story (or owner) is **404**, never leaked. `play_sessions` carries no `user_id`; isolation is transitive through the owner-scoped story. Authorization is on the parent `Story` (`view` to read index/play, `update` to fork) because forking writes only to the save realm and never mutates the authoring template.

## Atomicity & the "deep-copy"

The fork is a single transactional write today: derive the first beat, then `INSERT` the `play_sessions` row. The "deep-copy" the story name implies is **structural referencing**, not duplication — the save points at the immutable chapter/scene/beat rows. Wrapping the insert in `DB::transaction` is the seam that keeps the fork atomic once Phase 5 adds disposition-prior **edge seeding** inside the same transaction (ADR 0002): a failure partway must roll back to leave no half-seeded, loadable save. The `SessionForkTest` proves this by forcing a failure on the `created` event and asserting zero rows survive.

## First-beat positioning (document order)

The save begins at the **earliest beat in document order**, found by ordering `chapter.number → scene.number → beat.number` and taking the first — so the position is the true narrative start even when chapter 1 holds no beats. A play-ready story is guaranteed at least one beat by `StoryOverviewService::readiness()` (`beats >= 1`); the service still null-guards defensively.

## Play-readiness gate

Starting a session reuses the **same** gate the Overview renders (`StoryOverviewService::readiness()`: ≥ 1 character, ≥ 1 beat, a resolvable model for every engine role). The Saves UI disables the Start button when not ready and links back to the Overview; `store` **re-checks** server-side and rejects with an error toast, never trusting the client.

## Related

- [../../../api/saves.md](../../../api/saves.md) — endpoint/props contract
- [Session_State_Machine.md](./Session_State_Machine.md) — the `state_node` lifecycle the save enters at `session_start`
- [../Data/Persistence_Erd.md](../Data/Persistence_Erd.md) — `stories → play_sessions` (now exercised) and the deferred save-realm children
- [../../../adr/0012-persistence-schema.md](../../../adr/0012-persistence-schema.md) — two-realm model (authoring immutable, save mutable)
- [../../ARCHITECTURE.md](../../ARCHITECTURE.md) — Sprint 13 (E2.1 Session fork)
