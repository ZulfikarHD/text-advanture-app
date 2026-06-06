# Lorebook API Contract (S-3.1.1 / S-3.1.2 / S-3.2.1)

> Per-story lorebook CRUD inside the authoring workspace, plus the world-fact discipline soft gate (S-3.1.2) and the keyword match preview (S-3.2.1). All endpoints are auth-gated and owner-scoped; the child entry binds under its parent story (scoped bindings). Governed by ADR 0013 §5.

## Routes

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| `GET` | `/stories/{story:slug}/lorebook` | `stories.lorebook.index` | `LorebookController@index` |
| `POST` | `/stories/{story:slug}/lorebook` | `stories.lorebook.store` | `LorebookController@store` |
| `POST` | `/stories/{story:slug}/lorebook/preview` | `stories.lorebook.preview` | `LorebookController@preview` |
| `PUT` | `/stories/{story:slug}/lorebook/{lorebookEntry}` | `stories.lorebook.update` | `LorebookController@update` |
| `DELETE` | `/stories/{story:slug}/lorebook/{lorebookEntry}` | `stories.lorebook.destroy` | `LorebookController@destroy` |

- `{story:slug}` resolves under the `OwnerScope` global scope — a foreign story is **404**, never leaked.
- `{lorebookEntry}` resolves via **scoped bindings** (`->scopeBindings()`): it must belong to the bound story (relationship `Story::lorebookEntries()`), so an entry from another story is **404**.
- Write routes and `preview` are throttled (`throttle:30,1`).
- `preview` is a **read-only JSON endpoint** consumed by the `useHttp` client; it has no page-visit form.

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
| `acknowledge_interiority` | `bool?` | nullable; transient (not persisted). When `true`, overrides the world-fact discipline soft gate (S-3.1.2) |

### `PUT /stories/{story:slug}/lorebook/{lorebookEntry}` (UpdateLorebookEntryRequest)

Same shape as create (including `acknowledge_interiority`). Keywords are normalised server-side (trimmed, blanks dropped, de-duplicated) by `LorebookService` before persistence.

## World-fact discipline (S-3.1.2, ADR 0013 §5)

A lorebook entry must be a **world fact**, never a character's interiority (private feelings, secret intent, hidden knowledge). Store/update run a deterministic linter (`InteriorityHeuristic`) over `content`:

- If interiority is detected **and** `acknowledge_interiority` is not `true`, validation fails with an error on the synthetic key **`interiority`** (no field), naming the flagged phrases and directing the author to the character cards. Nothing is persisted.
- If the author resubmits with `acknowledge_interiority = true`, the entry saves (**soft gate** — a false positive is never a hard lock).
- A clean fact, including one that merely contains an emotive word ("the gloves *feel* cold"), saves without acknowledgement.

The frontend renders the `interiority` error as a distinct **warning** panel (not the generic error alert) with a "Save as world fact anyway" action and a link to the character cards.

### `POST /stories/{story:slug}/lorebook/preview` (PreviewLorebookRequest)

Keyword match preview — answers which entries a sample excerpt would trigger, using the **same matching as runtime injection** (`LorebookKeywordMatcher`). Read-only and side-effect-free.

| Field | Type | Rules |
|-------|------|-------|
| `sample_text` | `string` | required, max 20000 |
| `chapter_id` | `int?` | nullable, must be a chapter id of **this** story; drives the reveal-gate clamp |

Response (`200`, JSON):

```typescript
type PreviewResult = {
    triggered: Array<{
        id: number;
        title: string | null;
        keywords: string[];
        matchedKeywords: string[];
    }>;
    withheld: Array<{
        id: number;
        title: string | null;
        keywords: string[];
        matchedKeywords: string[];
        minRevealChapter: { id: number; number: number; title: string } | null;
    }>;
};
```

- Matching is **case-insensitive substring containment** per keyword (so multi-word keywords like "Crystal Hollow" match as a phrase).
- An entry with ≥1 matched keyword is `triggered`, **unless** a `chapter_id` is given and the entry's `min_reveal_chapter.number` is later than that chapter's number — then it is `withheld`. With no `chapter_id` the reveal gate is not applied.
- Validation failures return a **JSON `422`** (`{ message, errors }`) via `failedValidation()`, because the app otherwise renders web redirects for non-`api/*` validation errors (`bootstrap/app.php` `shouldRenderJsonWhen`).

## Validation messages

| Rule | Message |
|------|---------|
| `keywords.required` / `keywords.min` | "Add at least one keyword so the entry can be matched at runtime." |
| `content.required` | "Lorebook content is required." |
| `min_reveal_chapter_id.exists` | "The selected reveal chapter does not belong to this story." |
| `interiority` (synthetic) | "This reads like a character's interiority (…). The lorebook is for world facts only — move private feelings, secret intent, or hidden knowledge to the character cards." |
| `sample_text.required` | "Paste some sample text to test which entries it triggers." |
| `chapter_id.exists` | "The selected chapter does not belong to this story." |

## Flash / toast

All mutating endpoints flash `Inertia::flash('toast', ...)` and redirect to `stories.lorebook.index`:

| Action | Type | Message |
|--------|------|---------|
| Create | `success` | "Lorebook entry created." |
| Update | `success` | "Lorebook entry updated." |
| Delete | `success` | "Lorebook entry deleted." |

## Ownership & authorization

- `StoryPolicy` (extending `OwnerPolicy`) gates by ownership: `view` for index and preview, `update` for store/update/destroy.
- Lorebook entries carry no `user_id`; they inherit isolation transitively through their story, so there is no entry-level policy — the parent gate + scoped binding are the enforcement.

## Out of scope

- **Runtime keyword injection** (narrator + knowledge-bounded NPC context) is wired with the narrator loop in a later phase (PH-31, ADR 0013 §5). The preview proves the matcher; it does not inject. Runtime will reuse `LorebookKeywordMatcher`.
