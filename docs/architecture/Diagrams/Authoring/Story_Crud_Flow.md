# Story CRUD Flow (S-1.1.1 / S-1.1.2)

> Request flow for story lifecycle operations through the workspace UI.

```mermaid
sequenceDiagram
    participant U as Author (Browser)
    participant I as Inertia
    participant C as StoryController
    participant S as StoryService
    participant DB as MariaDB

    Note over U,DB: CREATE (S-1.1.1)
    U->>I: Click "New story" → Dialog opens
    U->>I: Fill form → Submit
    I->>C: POST /stories
    C->>C: authorize('create', Story)
    C->>S: create(owner, data)
    S->>S: deriveUniqueSlug() if no slug
    S->>DB: BEGIN → INSERT stories → COMMIT
    S-->>C: Story
    C-->>I: redirect /dashboard + toast

    Note over U,DB: INDEX (S-1.1.2)
    U->>I: Navigate to /dashboard
    I->>C: GET /dashboard
    C->>DB: SELECT * FROM stories WHERE user_id = ? ORDER BY updated_at DESC
    C-->>I: Inertia::render('Dashboard', { stories })

    Note over U,DB: EDIT (S-1.1.2)
    U->>I: Click edit icon on card
    I->>C: GET /stories/{slug}/edit
    C->>C: authorize('view', story)
    C-->>I: Inertia::render('stories/Edit', { story })
    U->>I: Edit fields → Submit
    I->>C: PUT /stories/{slug}
    C->>C: authorize('update', story)
    C->>S: update(story, data)
    S->>DB: BEGIN → UPDATE stories → COMMIT
    S-->>C: Story
    C-->>I: redirect back + toast

    Note over U,DB: DELETE (S-1.1.2)
    U->>I: Click delete → useConfirm dialog
    U->>I: Confirm deletion
    I->>C: DELETE /stories/{slug}
    C->>C: authorize('delete', story)
    C->>S: delete(story)
    S->>DB: BEGIN → DELETE stories (FK cascade) → COMMIT
    C-->>I: redirect /dashboard + toast
```

## Ownership isolation

All queries run under the `OwnerScope` global scope — a foreign story resolves to 404 on route-model binding. The `StoryPolicy` (extending `OwnerPolicy`) gates create/view/update/delete by ownership alone.

## Slug uniqueness

The `(user_id, slug)` composite unique index allows two different owners to hold the same slug. `StoryService::deriveUniqueSlug()` auto-suffixes derived slugs on per-owner collision; explicit slugs fail validation.
