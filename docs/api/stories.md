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
