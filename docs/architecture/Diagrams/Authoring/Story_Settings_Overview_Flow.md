# Story Settings & Overview Flow (S-1.2.1 / S-1.2.2)

> The per-story workspace surfaces (E1.2): saving settings, resolving an
> override, and deriving the overview counts + play-readiness on read.

## Settings save + override resolution (S-1.2.1)

```mermaid
sequenceDiagram
    participant U as Author (Browser)
    participant I as Inertia
    participant C as StorySettingsController
    participant S as StorySettingsService
    participant R as ModelRoleResolver
    participant DB as MariaDB

    Note over U,DB: EDIT
    U->>I: Open story → Settings tab
    I->>C: GET /stories/{slug}/settings
    C->>C: authorize('view', story)
    C->>DB: SELECT global + story model_profiles
    C-->>I: Inertia::render('stories/Settings', { defaultPov, povOptions, roles })

    Note over U,DB: SAVE
    U->>I: Choose POV, toggle role overrides → Save
    I->>C: PUT /stories/{slug}/settings
    C->>C: authorize('update', story)
    C->>S: update(story, validated)
    S->>DB: BEGIN
    S->>DB: UPDATE stories.settings.default_pov
    loop each role
        alt override on
            S->>DB: updateOrCreate model_profiles(scope=Story, story_id, role)
        else override off
            S->>DB: DELETE model_profiles(scope=Story, story_id, role)
        end
    end
    S->>DB: COMMIT
    C-->>I: redirect back + toast

    Note over U,DB: RESOLUTION (engine read, any later turn)
    R->>DB: SELECT model_profiles WHERE story override?
    alt story row exists
        R-->>R: use story override
    else
        R-->>R: fall back to global default
    end
```

## Overview counts + play-readiness (S-1.2.2, derived on read)

```mermaid
flowchart TD
    Req["GET /stories/{slug} (Overview)"] --> Svc["StoryOverviewService"]
    Svc --> Counts["counts(): characters / chapters / scenes / beats / lorebook / reveal-ledger / saves"]
    Svc --> Gate

    subgraph Gate [Play-readiness gate - recomputed every read]
        C1[">= 1 character"]
        C2["A chapter with a scene and a beat (any beat implies the chain)"]
        C3["every LlmRole resolves via ModelRoleResolver"]
        C1 --> Ready{"all met?"}
        C2 --> Ready
        C3 --> Ready
        Ready -->|yes| Playable["ready = true"]
        Ready -->|no| Missing["ready = false + enumerated unmet requirements"]
    end

    Counts --> Render["Inertia::render('stories/Overview', { counts, readiness })"]
    Playable --> Render
    Missing --> Render
```

## Notes

- **Nothing here is stored.** Counts are cheap aggregates and play-readiness is
  recomputed on each overview read; neither is cached or persisted.
- **Owner scope.** `{story:slug}` binds under `OwnerScope`, so a foreign story
  404s on every surface (Overview / Details / Settings).
- **Resolution order** is always per-story override → global default, reusing the
  existing `ModelRoleResolver`. The readiness "model" check catches
  `UnresolvedModelRoleException` and lists the unresolved roles.
- **Deferred:** per-story rubric/elapsed/drift tunable overrides (PH-29) wait on
  a global rubric config home (PH-8); Settings ships POV + model roles only.
