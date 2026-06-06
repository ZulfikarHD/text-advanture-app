# Lorebook CRUD Flow (S-3.1.1 / S-3.1.2 / S-3.2.1)

> Request flow for per-story lorebook entry CRUD through the workspace UI, plus the world-fact discipline soft gate (S-3.1.2) and the keyword match preview (S-3.2.1). World facts injected on keyword match at runtime (ADR 0013 §5).

```mermaid
sequenceDiagram
    participant U as Author (Browser)
    participant I as Inertia
    participant C as LorebookController
    participant S as LorebookService
    participant H as InteriorityHeuristic
    participant DB as MariaDB

    Note over U,DB: INDEX
    U->>I: Open story -> Lorebook tab
    I->>C: GET /stories/{slug}/lorebook
    C->>C: authorize('view', story)
    C->>DB: SELECT entries (with minRevealChapter) + chapters
    C-->>I: Inertia::render('stories/Lorebook', { story, entries, chapters })

    Note over U,DB: CREATE (with world-fact discipline soft gate)
    U->>I: "New entry" -> Dialog -> Submit
    I->>C: POST /stories/{slug}/lorebook
    C->>C: authorize('update', story)
    C->>C: StoreLorebookEntryRequest (keywords>=1, content, chapter in-story)
    C->>H: after(): flag(content) unless acknowledge_interiority
    alt interiority flagged AND not acknowledged
        C-->>I: redirect back, errors.interiority (+ flagged phrases)
        I->>U: warning panel + "Save as world fact anyway"
        U->>I: resubmit with acknowledge_interiority=true
        I->>C: POST (acknowledged)
    end
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

## Keyword match preview (S-3.2.1)

A read-only tuning aid, transported via `useHttp` (a standalone JSON request, no page visit) so the sample excerpt rides in the POST body. It uses the **canonical `LorebookKeywordMatcher`** — the same match runtime injection will reuse (PH-31).

```mermaid
sequenceDiagram
    participant U as Author (Browser)
    participant P as LorebookPreviewDialog
    participant C as LorebookController
    participant M as LorebookKeywordMatcher

    U->>P: "Test keywords" -> sample text (+ optional chapter)
    P->>C: POST /stories/{slug}/lorebook/preview (useHttp, JSON)
    C->>C: authorize('view', story)
    C->>C: PreviewLorebookRequest (sample_text required, chapter in-story)
    C->>M: preview(entries, sampleText, chapterNumber?)
    M-->>C: { triggered[], withheld[] }
    C-->>P: 200 JSON results
    P->>U: triggered (matched keywords) + withheld (reveal-gated, Lock badge)
```

## Ownership & scoping

The parent `{story:slug}` resolves under `OwnerScope` (foreign story → 404 on binding). The child `{lorebookEntry}` uses `->scopeBindings()`, so Laravel scopes the lookup through `Story::lorebookEntries()` — an entry from another story is 404, never leaked. Authorization is on the parent `Story` (`view` for index, `update` for writes); entries carry no `user_id` and inherit isolation transitively through their story.

## Reveal gate

`min_reveal_chapter_id` is an optional FK to a chapter **of the same story** (validated). Chapters land in a later phase, so the selector is usually empty today and the UI degrades to a disabled hint. Runtime injection withholds an entry before its reveal chapter — wired with the narrator loop, not in this story. The **preview** lets the author test this clamp ahead of runtime: pick a chapter, and entries gated later are reported as `withheld` rather than `triggered`.

## World-fact discipline (S-3.1.2)

`InteriorityHeuristic` is a deterministic, offline linter (no LLM) that scans `content` for phrases reading as a character's interiority — private feelings, hidden intent, concealed knowledge. The store/update requests run it in an `after()` hook as a **soft gate**: a flagged entry is rejected with an `interiority` error unless `acknowledge_interiority` is set, so the default keeps interiority out of the lorebook (preserving character isolation at injection time) while a false positive remains overridable. The signal list is an authored, tunable default (PH-33).
