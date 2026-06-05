# Story Overview API Contract (S-1.2.2)

> The story workspace entry surface: derived authoring counts + play-readiness. Read-only, auth-gated, owner-scoped. Every figure is computed on read — nothing is stored or cached.

## Routes

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| `GET` | `/stories/{story:slug}` | `stories.show` | `StoryOverviewController@show` |

## Inertia props

### `stories/Overview`

| Prop | Type | Notes |
|------|------|-------|
| `story` | `StoryData` | `{ id, slug, title, description }` — also drives the workspace sub-nav |
| `counts` | `Counts` | Derived authoring inventory + save count |
| `readiness` | `Readiness` | Play-readiness gate with an enumerated requirement list |

```typescript
type Counts = {
    characters: number;
    chapters: number;
    scenes: number;
    beats: number;
    lorebookEntries: number;
    revealLedgerEntries: number;
    saves: number;
};

type Requirement = {
    key: string;     // 'characters' | 'structure' | 'model_config'
    label: string;
    met: boolean;
    detail: string;  // teaches the next step when unmet
};

type Readiness = {
    ready: boolean;          // true only when every requirement is met
    requirements: Requirement[];
};
```

## Derivation (StoryOverviewService)

- **Counts** aggregate the story's authoring rows; `scenes`/`beats` are counted
  via the chapter→scene subquery, `saves` via `story->playSessions()`.
- **Readiness** requires: ≥ 1 character; a chapter that contains a scene and a
  beat (any beat implies the full chain); and a resolvable model for **every**
  `LlmRole` (per-story override → global default, catching
  `UnresolvedModelRoleException`). The gate is reused by the full readiness
  checklist UI in E2.1 (S-2.1.2).

## Ownership & authorization

- Route-model binding resolves `{story:slug}` under `OwnerScope`; foreign stories 404.
- `Gate::authorize('view', $story)` (via `StoryPolicy`).

## Related

- [story-settings.md](./story-settings.md) · [stories.md](./stories.md) · [model-roles.md](./model-roles.md)
- [../architecture/Diagrams/Authoring/Story_Settings_Overview_Flow.md](../architecture/Diagrams/Authoring/Story_Settings_Overview_Flow.md)
