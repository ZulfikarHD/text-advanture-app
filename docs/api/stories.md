# Stories API Contract (S-1.1.1 / S-1.1.2)

> Story CRUD endpoints for the workspace dashboard. All endpoints are auth-gated and owner-scoped.

## Routes

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| `GET` | `/dashboard` | `dashboard` | `StoryController@index` |
| `POST` | `/stories` | `stories.store` | `StoryController@store` |
| `GET` | `/stories/{story:slug}/edit` | `stories.edit` | `StoryController@edit` |
| `PUT` | `/stories/{story:slug}` | `stories.update` | `StoryController@update` |
| `DELETE` | `/stories/{story:slug}` | `stories.destroy` | `StoryController@destroy` |

### Workspace placeholder surfaces (E2.1 / S-2.1.1)

Reachable "coming soon" surfaces so the per-story workspace nav spans every authoring surface without dead links. Each repointed at its real controller when the feature ships (PH-30). **Lorebook** has since shipped its real CRUD — see [lorebook.md](./lorebook.md).

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| `GET` | `/stories/{story:slug}/characters` | `stories.characters.index` | `StoryPlaceholderController@characters` |
| `GET` | `/stories/{story:slug}/structure` | `stories.structure.index` | `StoryPlaceholderController@structure` |
| `GET` | `/stories/{story:slug}/saves` | `stories.saves.index` | `StoryPlaceholderController@saves` |

## Inertia props

### `Dashboard` (index)

| Prop | Type | Notes |
|------|------|-------|
| `stories` | `StorySummary[]` | Author's stories, ordered by `updated_at` DESC |

```typescript
type StorySummary = {
    id: number;
    slug: string;
    title: string;
    description: string | null;
    updatedAtForHumans: string | null;
};
```

### `stories/Edit`

| Prop | Type | Notes |
|------|------|-------|
| `story` | `StoryData` | The story being edited |

```typescript
type StoryData = {
    id: number;
    slug: string;
    title: string;
    description: string | null;
};
```

### `stories/ComingSoon` (placeholder surfaces)

Shared by all four placeholder workspace surfaces; `surface` drives the copy and icon.

| Prop | Type | Notes |
|------|------|-------|
| `story` | `StoryRef` | The story whose workspace is open (required by the workspace layout) |
| `surface` | `Surface` | Descriptor for the unbuilt surface |

```typescript
type StoryRef = { id: number; slug: string; title: string };

type Surface = {
    key: 'characters' | 'structure' | 'lorebook' | 'saves';
    title: string;
    description: string;
    phase: string; // e.g. "Phase 3", "Phase 4"
};
```

## Request bodies

### `POST /stories` (StoreStoryRequest)

| Field | Type | Rules |
|-------|------|-------|
| `title` | `string` | required, max 200 |
| `slug` | `string?` | nullable, max 120, regex `^[a-z0-9]+(?:-[a-z0-9]+)*$`, unique per owner. Derived from title when omitted |
| `description` | `string?` | nullable, max 5000 |

### `PUT /stories/{story:slug}` (UpdateStoryRequest)

| Field | Type | Rules |
|-------|------|-------|
| `title` | `string` | required, max 200 |
| `slug` | `string` | required, max 120, regex, unique per owner (excludes self) |
| `description` | `string?` | nullable, max 5000 |

## Flash / toast

All mutating endpoints flash `Inertia::flash('toast', ...)`:

| Action | Type | Message |
|--------|------|---------|
| Create | `success` | "Story created." |
| Update | `success` | "Story updated." |
| Delete | `success` | "Story deleted." |

## Ownership & authorization

- Route-model binding resolves `{story:slug}` under the `OwnerScope` global scope.
- Foreign stories resolve to 404 — existence is never leaked.
- `StoryPolicy` (extending `OwnerPolicy`) gates all actions by ownership.
