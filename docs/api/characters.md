# Characters API Contract (S-1.1.1 / S-1.1.2)

> Per-story **minimal manual** character CRUD inside the authoring workspace — hand-authored, **no LLM call, no API key**. A character is `{ name, appearance, base_opacity, is_player }`; a non-player (NPC) additionally carries a `folded_identity` and a mandatory `knowledge_boundary`. The minimal fields live on the per-`(character, chapter)` chapter-1 `character_card`, so the create path **auto-ensures a default Chapter 1** to anchor it. All endpoints are auth-gated and owner-scoped; the child character binds under its parent story (scoped bindings). Governed by ADR 0018 §2 (manual mode); the AI/hybrid pipeline + bible→card compile is later (Phase 5).

## Routes

| Method | URI | Name | Controller |
|--------|-----|------|------------|
| `GET` | `/stories/{story:slug}/characters` | `stories.characters.index` | `CharacterController@index` |
| `POST` | `/stories/{story:slug}/characters` | `stories.characters.store` | `CharacterController@store` |
| `PUT` | `/stories/{story:slug}/characters/{character}` | `stories.characters.update` | `CharacterController@update` |
| `DELETE` | `/stories/{story:slug}/characters/{character}` | `stories.characters.destroy` | `CharacterController@destroy` |

- `{story:slug}` resolves under the `OwnerScope` global scope — a foreign story is **404**, never leaked.
- `{character}` resolves via **scoped bindings** (`->scopeBindings()`): it must belong to the bound story (relationship `Story::characters()`), so a character from another story is **404**.
- Write routes are throttled (`throttle:30,1`).

## Inertia props

### `stories/Characters` (index)

| Prop | Type | Notes |
|------|------|-------|
| `story` | `StoryRef` | The owner-scoped story whose workspace is open |
| `characters` | `Character[]` | This story's cast, players first, then by name; each with its chapter-1 card data |

```typescript
type StoryRef = { id: number; slug: string; title: string };

type Character = {
    id: number;
    slug: string;
    name: string;
    isPlayer: boolean;
    baseOpacity: number;
    appearance: string | null;
    foldedIdentity: string | null; // '' for the player
    knowledgeBoundary: {
        knows: string[];        // empty for the player
        doesNotKnow: string[];  // empty for the player
    };
};
```

## Request bodies

### `POST /stories/{story:slug}/characters` (StoreCharacterRequest)

| Field | Type | Rules |
|-------|------|-------|
| `name` | `string` | required, max 150 |
| `is_player` | `bool?` | boolean; defaults to `false` |
| `appearance` | `string` | required, max 2000 |
| `base_opacity` | `int` | required, integer 0–100 |
| `folded_identity` | `string?` | nullable, max 5000; **required for an NPC** (`is_player = false`) |
| `knowledge_boundary.knows` | `string[]?` | nullable array; items max 255 |
| `knowledge_boundary.does_not_know` | `string[]?` | nullable array; items max 255 |

- **NPC (`is_player = false`):** `folded_identity` is required and `knowledge_boundary` must carry **≥ 1 entry** across `knows` / `does_not_know` (the mandatory-boundary rule, enforced in `after()`).
- **Player (`is_player = true`):** only `name` + `appearance` + `base_opacity` are used. `folded_identity` is stored `''` and `knowledge_boundary` `{ knows: [], does_not_know: [] }`; the NPC fields are ignored.
- **Exactly one player per story:** a second `is_player = true` fails with an error on `is_player`. On update the bound character is excluded, so editing the existing player is allowed.

### `PUT /stories/{story:slug}/characters/{character}` (UpdateCharacterRequest)

Same shape and rules as create. `knowledge_boundary` lists are normalised server-side (trimmed, blanks dropped, de-duplicated) by `CharacterService` before persistence; switching a character to the player resets its card to the empty player shape (no stale interiority).

## Chapter-1 anchor

`character_cards.chapter_id` is `NOT NULL` and the minimal fields live only on the card, so a character cannot exist without a chapter. On the first character commit, `CharacterService` `firstOrCreate`s a default **Chapter 1** (`number = 1`, `title = "Chapter 1"`, `pov_default` = the story's resolved default POV via `StorySettingsService::resolveDefaultPov`) and writes the chapter-1 card under it; later characters reuse the same chapter. E1.2 (Structure) refines that chapter rather than re-creating it.

## Validation messages

| Rule | Message |
|------|---------|
| `name.required` | "Give the character a name." |
| `appearance.required` | "Describe how the character looks." |
| `base_opacity.required` | "Set how guarded the character is (0–100)." |
| `folded_identity` (NPC, synthetic) | "Folded identity is required for a non-player character." |
| `knowledge_boundary` (NPC, synthetic) | "Knowledge boundary is required: list at least one thing this character knows or does not know." |
| `is_player` (synthetic) | "This story already has a player character — only one character can be the player." |

## Flash / toast

All mutating endpoints flash `Inertia::flash('toast', ...)` and redirect to `stories.characters.index`:

| Action | Type | Message |
|--------|------|---------|
| Create | `success` | "Character created." |
| Update | `success` | "Character updated." |
| Delete | `success` | "Character deleted." |

## Ownership & authorization

- `StoryPolicy` (extending `OwnerPolicy`) gates by ownership: `view` for index, `update` for store/update/destroy.
- Characters carry no `user_id`; they inherit isolation transitively through their story, so there is no character-level policy — the parent gate + scoped binding are the enforcement.

## Out of scope

- **Edges / registers / sensitivities** (`live_axes` content) are not authored here — stored `[]` this phase (Phase 5).
- The **AI / hybrid creation pipeline** and the **bible → card compile** (ADR 0018 §2–3) land in Phase 5; E1.1 is the minimal manual slice only.
- The runtime consumers of `knowledge_boundary` (Phase 2 NPC `IDENTITY`/`SCENE_EXCERPT` blocks; Phase 4 `NUDGE` leak-check) are separate and later — no guard runs at authoring time.
