# Lorebook API Contract (S-3.1.1)

> Per-story lorebook CRUD inside the authoring workspace. All endpoints are auth-gated and owner-scoped; the child entry binds under its parent story (scoped bindings). Governed by ADR 0013 §5.

## Routes

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| `GET` | `/stories/{story:slug}/lorebook` | `stories.lorebook.index` | `LorebookController@index` |
| `POST` | `/stories/{story:slug}/lorebook` | `stories.lorebook.store` | `LorebookController@store` |
| `PUT` | `/stories/{story:slug}/lorebook/{lorebookEntry}` | `stories.lorebook.update` | `LorebookController@update` |
| `DELETE` | `/stories/{story:slug}/lorebook/{lorebookEntry}` | `stories.lorebook.destroy` | `LorebookController@destroy` |

- `{story:slug}` resolves under the `OwnerScope` global scope — a foreign story is **404**, never leaked.
- `{lorebookEntry}` resolves via **scoped bindings** (`->scopeBindings()`): it must belong to the bound story (relationship `Story::lorebookEntries()`), so an entry from another story is **404**.
- Write routes are throttled (`throttle:30,1`).

## Inertia props

### `stories/Lorebook` (index)

| Prop | Type | Notes |
|------|------|-------|
| `story` | `StoryRef` | The owner-scoped story whose workspace is open |
| `entries` | `LorebookEntry[]` | This story's entries, ordered by title then id desc |
| `chapters` | `ChapterOption[]` | The story's chapters for the optional reveal gate (usually empty until structure is authored) |

```typescript
type StoryRef = { id: number; slug: string; title: string };

type LorebookEntry = {
    id: number;
    title: string | null;
    keywords: string[];
    content: string;
    minRevealChapter: { id: number; number: number; title: string } | null;
};

type ChapterOption = { id: number; number: number; title: string };
```

## Request bodies

### `POST /stories/{story:slug}/lorebook` (StoreLorebookEntryRequest)

| Field | Type | Rules |
|-------|------|-------|
| `title` | `string?` | nullable, max 200 |
| `keywords` | `string[]` | required, array, min 1 item |
| `keywords.*` | `string` | required, max 100 |
| `content` | `string` | required, max 10000 |
| `min_reveal_chapter_id` | `int?` | nullable, must be a chapter id of **this** story |

### `PUT /stories/{story:slug}/lorebook/{lorebookEntry}` (UpdateLorebookEntryRequest)

Same shape as create. Keywords are normalised server-side (trimmed, blanks dropped, de-duplicated) by `LorebookService` before persistence.

## Validation messages

| Rule | Message |
|------|---------|
| `keywords.required` / `keywords.min` | "Add at least one keyword so the entry can be matched at runtime." |
| `content.required` | "Lorebook content is required." |
| `min_reveal_chapter_id.exists` | "The selected reveal chapter does not belong to this story." |

## Flash / toast

All mutating endpoints flash `Inertia::flash('toast', ...)` and redirect to `stories.lorebook.index`:

| Action | Type | Message |
|--------|------|---------|
| Create | `success` | "Lorebook entry created." |
| Update | `success` | "Lorebook entry updated." |
| Delete | `success` | "Lorebook entry deleted." |

## Ownership & authorization

- `StoryPolicy` (extending `OwnerPolicy`) gates by ownership: `view` for index, `update` for store/update/destroy.
- Lorebook entries carry no `user_id`; they inherit isolation transitively through their story, so there is no entry-level policy — the parent gate + scoped binding are the enforcement.

## Out of scope (this story)

- **Runtime keyword injection** (narrator + knowledge-bounded NPC context) is wired with the narrator loop in a later phase (ADR 0013 §5).
- **World-fact discipline validation** (rejecting/steering character interiority) is S-3.1.2.
- **Keyword match preview** is S-3.2.1.
