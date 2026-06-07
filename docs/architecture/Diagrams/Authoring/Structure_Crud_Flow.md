# Structure CRUD Flow (S-1.2.1)

> Request flow for per-story **minimal manual** structure CRUD through the workspace UI. The author hand-builds the **chapter → scene → beat** the loop plays through — no LLM call, no API key (ADR 0015 minimal slice). A chapter is `{ title, pov_default }`; a scene is `{ pov_mode, pov_anchor, tone?, present_characters }` (its POV contract); a beat is `{ goal }` (its satisfaction anchor). The per-parent `number` is system-managed (`max + 1`, locked), and a scene's `pov_anchor` / `present_characters` are **character slugs** validated against the story's cast. The full beat document (`intent`, `word_budget`, `nudge_target`) and outline compilation are separate and later (Phase 4 / O6, PH-35).

```mermaid
sequenceDiagram
    participant U as Author (Browser)
    participant I as Inertia
    participant C as StructureController
    participant S as StructureService
    participant DB as MariaDB

    Note over U,DB: INDEX
    U->>I: Open story -> Structure tab
    I->>C: GET /stories/{slug}/structure
    C->>C: authorize('view', story)
    C->>DB: SELECT chapters (withCount characterCards) -> scenes -> beats, ordered by number
    C-->>I: Inertia::render('stories/Structure', { story, characters, chapters, povOptions, defaultPov })

    Note over U,DB: CREATE (chapter | scene | beat)
    U->>I: "New chapter" / "Add scene" / "Add beat" -> Dialog -> Submit
    I->>C: POST .../structure/chapters | .../chapters/{chapter}/scenes | .../scenes/{scene}/beats
    C->>C: scopeBindings: each parent must nest in the bound story (else 404)
    C->>C: authorize('update', story)
    C->>C: Store{Chapter,Scene,Beat}Request (scene: pov_mode enum; present cast & anchor ∈ story cast; anchor ∈ present)
    C->>S: createChapter/createScene/createBeat(parent, validated)
    S->>DB: BEGIN -> number = max(number)+1 (lockForUpdate) -> INSERT -> COMMIT
    Note right of S: beat writes intent='' + word_budget=DEFAULT (PH-35); scene writes elapsed_source=Default
    C-->>I: redirect structure.index + toast

    Note over U,DB: UPDATE / DELETE (scoped nested binding)
    U->>I: Edit dialog / delete (useConfirm)
    I->>C: PUT|DELETE .../structure/chapters/{chapter}[/scenes/{scene}[/beats/{beat}]]
    C->>C: scopeBindings down {story}->{chapter}->{scene}->{beat} (mismatch -> 404)
    C->>C: authorize('update', story)
    alt delete chapter that still anchors character cards
        C-->>I: reject + error toast (cards would orphan the E1.1 cast)
    else
        C->>S: update*/delete*(model[, data])
        S->>DB: BEGIN -> UPDATE | DELETE (cascade children) -> COMMIT
        C-->>I: redirect structure.index + toast
    end
```

## Ownership & scoping

The parent `{story:slug}` resolves under `OwnerScope` (foreign story → 404 on binding). Every nested child uses `->scopeBindings()`, so Laravel scopes each lookup through its parent relationship in sequence — `Story::chapters()` → `Chapter::scenes()` → `Scene::beats()`. A chapter/scene/beat whose ancestor chain doesn't match the URL is **404**, never leaked. Authorization is on the parent `Story` (`view` for index, `update` for writes); structure rows carry no `user_id` and inherit isolation transitively through their story.

## Numbering (race-safe)

Ordering is the per-parent integer `number`, unique per `(story_id, number)` / `(chapter_id, number)` / `(scene_id, number)`. `StructureService` takes the next ordinal as `max(number) + 1` **inside the create transaction with `lockForUpdate()`**, so two concurrent creates can't derive the same ordinal and trip the unique index. There is no `slug`/`order` column.

## Scene POV contract & deferred fields (PH-35)

- **POV contract.** `pov_mode` is the `PovMode` enum (`first_person` / `second_person` / `third_limited` / `third_omniscient`). `pov_anchor` (the viewpoint character) and `present_characters` are stored as **character slugs**, validated in the request `after()` hook: every present slug must belong to the story, and the anchor must be **both a story character and one of the present cast** (the viewpoint has to be in the scene). The narrator's `POV_CONTRACT` block (E4) reads these.
- **Deferred defaults.** A beat's `intent` / `word_budget` (the Phase-4 beat document) are written as `''` / `StructureService::DEFAULT_WORD_BUDGET` to satisfy their `NOT NULL` columns without surfacing them. A scene's `elapsed_source` (no DB default) is set to `Default` paired with `elapsed_bucket = Continuous`; declaring an in-world gap is deferred.

## Chapter-delete guard

Deleting a chapter cascades to its scenes/beats **and** its `character_cards`. Because the E1.1 minimal fields live only on the chapter-1 card, cascading those would orphan the cast — so the controller rejects the delete with an error toast when the chapter still anchors character cards (Chapter 1 stays protected while characters exist). The frontend disables the delete with a hint, but the server is the enforcement.

## Play-readiness

Authoring at least one beat (which nests under scene → chapter) flips the **Overview** structure requirement green — `StoryOverviewService::readiness()` keys on `beats >= 1`. No readiness logic changed for E1.2.

## Related

- [../../../api/structure.md](../../../api/structure.md) — endpoint/props contract
- [Character_Crud_Flow.md](./Character_Crud_Flow.md) — the sibling surface this mirrors (and the Chapter-1 anchor it refines)
- [Story_Workspace_Shell.md](./Story_Workspace_Shell.md) — the workspace shell this surface lives in
- [Story_Settings_Overview_Flow.md](./Story_Settings_Overview_Flow.md) — Overview counts + play-readiness
- [../Data/Persistence_Erd.md](../Data/Persistence_Erd.md) — the chapters/scenes/beats schema
- [../../ARCHITECTURE.md](../../ARCHITECTURE.md) — Sprint 12 (E1.2 Structure)
