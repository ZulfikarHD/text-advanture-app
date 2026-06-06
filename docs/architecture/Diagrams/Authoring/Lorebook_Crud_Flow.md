# Lorebook CRUD Flow (S-3.1.1)

> Request flow for per-story lorebook entry CRUD through the workspace UI. World facts injected on keyword match at runtime (ADR 0013 §5).

```mermaid
sequenceDiagram
    participant U as Author (Browser)
    participant I as Inertia
    participant C as LorebookController
    participant S as LorebookService
    participant DB as MariaDB

    Note over U,DB: INDEX
    U->>I: Open story -> Lorebook tab
    I->>C: GET /stories/{slug}/lorebook
    C->>C: authorize('view', story)
    C->>DB: SELECT entries (with minRevealChapter) + chapters
    C-->>I: Inertia::render('stories/Lorebook', { story, entries, chapters })

    Note over U,DB: CREATE
    U->>I: "New entry" -> Dialog -> Submit
    I->>C: POST /stories/{slug}/lorebook
    C->>C: authorize('update', story)
    C->>C: StoreLorebookEntryRequest (keywords>=1, content, chapter in-story)
    C->>S: create(story, validated)
    S->>S: normalizeKeywords (trim/drop/dedupe)
    S->>DB: BEGIN -> INSERT lorebook_entries -> COMMIT
    C-->>I: redirect lorebook.index + toast

    Note over U,DB: UPDATE / DELETE (scoped child binding)
    U->>I: Edit dialog / delete (useConfirm)
    I->>C: PUT|DELETE /stories/{slug}/lorebook/{lorebookEntry}
    C->>C: scopeBindings: entry must belong to story (else 404)
    C->>C: authorize('update', story)
    C->>S: update(entry, data) | delete(entry)
    S->>DB: BEGIN -> UPDATE|DELETE -> COMMIT
    C-->>I: redirect lorebook.index + toast
```

## Ownership & scoping

The parent `{story:slug}` resolves under `OwnerScope` (foreign story → 404 on binding). The child `{lorebookEntry}` uses `->scopeBindings()`, so Laravel scopes the lookup through `Story::lorebookEntries()` — an entry from another story is 404, never leaked. Authorization is on the parent `Story` (`view` for index, `update` for writes); entries carry no `user_id` and inherit isolation transitively through their story.

## Reveal gate

`min_reveal_chapter_id` is an optional FK to a chapter **of the same story** (validated). Chapters land in a later phase, so the selector is usually empty today and the UI degrades to a disabled hint. Runtime injection withholds an entry before its reveal chapter — wired with the narrator loop, not in this story.
