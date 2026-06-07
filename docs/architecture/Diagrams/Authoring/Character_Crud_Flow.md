# Character CRUD Flow (S-1.1.1 / S-1.1.2)

> Request flow for per-story **minimal manual** character CRUD through the workspace UI. A character is hand-authored — no LLM call, no API key — as `{ name, appearance, base_opacity, is_player }` plus, for a non-player (NPC), a `folded_identity` and a mandatory `knowledge_boundary { knows, does_not_know }`. The minimal fields live on the per-`(character, chapter)` chapter-1 `character_card`, so the surface **auto-ensures a default Chapter 1** to anchor it (characters are tied to chapters — Novel-Crafter model; ADR 0018 §2 manual mode). The full AI/hybrid creation + bible→card compile pipeline is separate and later (Phase 5).

```mermaid
sequenceDiagram
    participant U as Author (Browser)
    participant I as Inertia
    participant C as CharacterController
    participant S as CharacterService
    participant DB as MariaDB

    Note over U,DB: INDEX
    U->>I: Open story -> Characters tab
    I->>C: GET /stories/{slug}/characters
    C->>C: authorize('view', story)
    C->>DB: SELECT characters (with chapterOneCard) ordered by is_player, name
    C-->>I: Inertia::render('stories/Characters', { story, characters })

    Note over U,DB: CREATE
    U->>I: "New character" -> Dialog (player switch toggles NPC fields) -> Submit
    I->>C: POST /stories/{slug}/characters
    C->>C: authorize('update', story)
    C->>C: StoreCharacterRequest (name/appearance/base_opacity; NPC: folded_identity + knowledge_boundary; one-player guard)
    C->>S: create(story, validated)
    S->>S: derive unique (story_id, slug); normalise knowledge_boundary
    S->>DB: BEGIN -> firstOrCreate Chapter 1 -> INSERT character -> INSERT chapter-1 card -> COMMIT
    C-->>I: redirect characters.index + toast

    Note over U,DB: UPDATE / DELETE (scoped child binding)
    U->>I: Edit dialog / delete (useConfirm)
    I->>C: PUT|DELETE /stories/{slug}/characters/{character}
    C->>C: scopeBindings: character must belong to story (else 404)
    C->>C: authorize('update', story)
    C->>S: update(character, data) | delete(character)
    S->>DB: BEGIN -> UPDATE character + chapter-1 card | DELETE (cascade cards) -> COMMIT
    C-->>I: redirect characters.index + toast
```

## Ownership & scoping

The parent `{story:slug}` resolves under `OwnerScope` (foreign story → 404 on binding). The child `{character}` uses `->scopeBindings()`, so Laravel scopes the lookup through `Story::characters()` — a character from another story is 404, never leaked. Authorization is on the parent `Story` (`view` for index, `update` for writes); characters carry no `user_id` and inherit isolation transitively through their story.

## Chapter-1 anchor (the scrum fix)

`character_cards.chapter_id` is `NOT NULL` and the minimal fields (`appearance`, `folded_identity`, `knowledge_boundary`) live only on the card — so a character **cannot** exist without a chapter. This is correct by design: characters are parts of the novel, tied to its chapters. `CharacterService` therefore `firstOrCreate`s a default **Chapter 1** (`number = 1`, `title = "Chapter 1"`, `pov_default` = the story's resolved default POV via `StorySettingsService::resolveDefaultPov`) when the first character is committed, then writes the chapter-1 card under it. The second character reuses the same chapter (no duplicate). E1.2 (Structure) later refines that same chapter — it is not re-created. The E1.1 scrum *Technical Notes* preconditions were corrected to list the `chapters` table + this anchor.

## Player vs NPC (S-1.1.2)

- **Player** (`is_player = true`) carries **appearance + base_opacity only** — no simulated interiority. `folded_identity` is stored as `''`, `knowledge_boundary` as `{ knows: [], does_not_know: [] }`, `model_tier = Minor`, and `live_axes = []` (no edges). The dialog hides the NPC-only fields behind the player switch.
- **NPC** (`is_player = false`) requires a `folded_identity` and a **mandatory** `knowledge_boundary` (≥ 1 entry across `knows` / `does_not_know`) — captured now because Phase 2's NPC `IDENTITY`/`SCENE_EXCERPT` blocks and Phase 4's `NUDGE` leak-check depend on it. `model_tier = Major`.
- **Exactly one player per story.** Enforced in the request `after()` hook (excluding the bound character on update, so editing the existing player is allowed). `live_axes` content (edges/registers/sensitivities) is deferred to Phase 5 for everyone.

## Related

- [../../../api/characters.md](../../../api/characters.md) — endpoint/props contract
- [Reveal_Ledger_Crud_Flow.md](./Reveal_Ledger_Crud_Flow.md) · [Lorebook_Crud_Flow.md](./Lorebook_Crud_Flow.md) — the sibling CRUD surfaces this mirrors
- [Story_Workspace_Shell.md](./Story_Workspace_Shell.md) — the workspace shell this surface lives in
- [../../ARCHITECTURE.md](../../ARCHITECTURE.md) — Sprint 11 (E1.1)
