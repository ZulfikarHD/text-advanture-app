# Reveal Ledger API Contract (S-4.1.1)

> Per-story reveal-ledger CRUD inside the authoring workspace. A reveal-ledger entry records a load-bearing secret and the chapter it becomes known, so spoiler-safety is explicit rather than inferred. All endpoints are auth-gated and owner-scoped; the child entry binds under its parent story (scoped bindings). Governed by ADR 0013 §3.

## Routes

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| `GET` | `/stories/{story:slug}/reveal-ledger` | `stories.reveal-ledger.index` | `RevealLedgerController@index` |
| `POST` | `/stories/{story:slug}/reveal-ledger` | `stories.reveal-ledger.store` | `RevealLedgerController@store` |
| `PUT` | `/stories/{story:slug}/reveal-ledger/{revealLedgerEntry}` | `stories.reveal-ledger.update` | `RevealLedgerController@update` |
| `DELETE` | `/stories/{story:slug}/reveal-ledger/{revealLedgerEntry}` | `stories.reveal-ledger.destroy` | `RevealLedgerController@destroy` |

- `{story:slug}` resolves under the `OwnerScope` global scope — a foreign story is **404**, never leaked.
- `{revealLedgerEntry}` resolves via **scoped bindings** (`->scopeBindings()`): it must belong to the bound story (relationship `Story::revealLedgerEntries()`), so an entry from another story is **404**.
- Write routes are throttled (`throttle:30,1`).
- The reveal-clamp **preview** (S-4.1.2) is a separate, later story — it is not part of this contract.

## Inertia props

### `stories/RevealLedger` (index)

| Prop | Type | Notes |
|------|------|-------|
| `story` | `StoryRef` | The owner-scoped story whose workspace is open |
| `entries` | `RevealLedgerEntry[]` | This story's entries, ordered by fact then id desc |
| `chapters` | `ChapterOption[]` | The story's chapters for the required reveal point (usually empty until structure is authored) |
| `characters` | `CharacterOption[]` | The story's cast for the optional "about" attribution (usually empty until characters are authored) |

```typescript
type StoryRef = { id: number; slug: string; title: string };

type RevealLedgerEntry = {
    id: number;
    fact: string;
    character: { id: number; slug: string; name: string } | null; // null = world secret
    revealChapter: { id: number; number: number; title: string };
    whoKnows: string[]; // character slugs exempt from the clamp
    notes: string | null;
};

type ChapterOption = { id: number; number: number; title: string };
type CharacterOption = { id: number; slug: string; name: string };
```

## Request bodies

### `POST /stories/{story:slug}/reveal-ledger` (StoreRevealLedgerEntryRequest)

| Field | Type | Rules |
|-------|------|-------|
| `fact` | `string` | required, max 255 (a short, stable identifier, e.g. `the_diagnosis`) |
| `reveal_chapter_id` | `int` | required, must be a chapter id of **this** story |
| `character_id` | `int?` | nullable, must be a character id of **this** story (null = world secret) |
| `who_knows` | `string[]?` | nullable array of character slugs |
| `who_knows.*` | `string` | required, max 120 |
| `notes` | `string?` | nullable, max 2000 |

### `PUT /stories/{story:slug}/reveal-ledger/{revealLedgerEntry}` (UpdateRevealLedgerEntryRequest)

Same shape as create. `who_knows` slugs are normalised server-side (trimmed, blanks dropped, de-duplicated) by `RevealLedgerService` before persistence.

## Spoiler-safety semantics (ADR 0013 §3)

- The ledger backstops the bible's coarse section tags for the **few critical facts** that must never leak early — so spoiler-safety never rests on inference.
- **Clamp rule** (applied at compile time, not here) for a card at chapter `N`: include a fact iff `reveal_chapter ≤ N`; otherwise it becomes an explicit `does_not_know` entry on the card's `knowledge_boundary`.
- `who_knows` lists character slugs that know the fact **before** its reveal chapter — those characters are **exempt** from the clamp for that fact. Slugs are not existence-checked because characters are authored in a later phase; a slug that names no character simply exempts nobody.

## Validation messages

| Rule | Message |
|------|---------|
| `fact.required` | "Name the secret so it can be tracked." |
| `reveal_chapter_id.required` | "Choose the chapter where this fact becomes known." |
| `reveal_chapter_id.exists` | "The selected reveal chapter does not belong to this story." |
| `character_id.exists` | "The selected character does not belong to this story." |

## Flash / toast

All mutating endpoints flash `Inertia::flash('toast', ...)` and redirect to `stories.reveal-ledger.index`:

| Action | Type | Message |
|--------|------|---------|
| Create | `success` | "Reveal-ledger entry created." |
| Update | `success` | "Reveal-ledger entry updated." |
| Delete | `success` | "Reveal-ledger entry deleted." |

## Ownership & authorization

- `StoryPolicy` (extending `OwnerPolicy`) gates by ownership: `view` for index, `update` for store/update/destroy.
- Reveal-ledger entries carry no `user_id`; they inherit isolation transitively through their story, so there is no entry-level policy — the parent gate + scoped binding are the enforcement.

## Out of scope

- **Reveal-clamp preview** (S-4.1.2, Sprint 11): a read-only per-chapter `knowledge_boundary` projection that verifies no early-chapter card leaks a future arc.
- **Compile / runtime consumption** of the ledger (the card compiler that applies the clamp) lands with the character-card pipeline (Phase 3, ADR 0013 §3–4). CRUD is decoupled and useful on its own (PH-34).
