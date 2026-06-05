# Persistence ERD

Two-realm schema, the living detail of [ADR 0012](../../../adr/0012-persistence-schema.md). See [../../DATABASE.md](../../DATABASE.md) for column detail. The **authoring realm + `stories` ownership** is built as of Sprint 3 (S-4.1.1); the **save realm + global libraries** are **built as of Sprint 4** (S-4.2.1 / S-4.1.2), along with the owner-scoped **`provider_credentials`** key store (S-5.1.1).

```mermaid
erDiagram
  users ||--o{ stories : "owns (BelongsToOwner, Sprint 3)"
  users ||--o{ provider_credentials : "encrypted API key (S-5.1.1)"
  users ||--o{ llm_calls : "owns call log (user_id, S-5.3)"
  stories ||--o{ characters : has
  stories ||--o{ chapters : has
  stories ||--o{ chapter_outlines : "outline source (ADR 0019)"
  stories ||--o{ lorebook_entries : "world facts (ADR 0013)"
  stories ||--o{ reveal_ledger : "secret reveal map (ADR 0013)"
  chapters ||--o{ scenes : has
  scenes ||--o{ beats : has
  chapters ||--o{ character_cards : "valid for chapter"
  characters ||--o{ character_cards : "compiled per chapter"
  characters ||--o{ registers : instantiates
  register_archetypes ||--o{ registers : "based on (FK, PH-16 resolved)"
  character_archetypes ||--o{ characters : "seeds creation (ADR 0018)"
  characters ||--o{ sensitivities : declares

  stories ||--o{ play_sessions : "forked into"
  play_sessions ||--o{ relationship_edges : holds
  relationship_edges ||--o{ edge_axes : "live axes"
  relationship_edges ||--o{ axis_deltas : "append-only log"
  play_sessions ||--o{ internal_states : holds
  internal_states ||--o{ active_emotions : "own-clock decay"
  play_sessions ||--o{ acquired_sensitivities : "runtime scars"
  play_sessions ||--o{ beat_records : commits
  beat_records ||--o{ beat_true_states : "private, never cross-fed"
  beat_records ||--o{ beat_witnesses : "fidelity"
  play_sessions ||--o{ nudges : holds
  play_sessions ||--o{ review_items : "shared review gate"
  play_sessions ||--o{ scene_summaries : "context memory"
  play_sessions ||--o{ chapter_logs : "continuity"
  play_sessions ||--o{ events : "immediate timeline"
  play_sessions ||--o{ llm_calls : "append-only call log (ADR 0017)"
  review_items ||--o{ axis_deltas : "committed via gate"
  review_items ||--o{ nudges : "committed via gate"
  review_items ||--o{ character_cards : "card_compile (FK, S-4)"
  review_items ||--o{ chapter_outlines : "outline_compile (FK, S-4)"

  edge_axes {
    string axis
    int value
    string awareness_mode
    int soft_floor
    int hard_floor
    json scar
  }
  axis_deltas {
    string axis
    string direction
    int magnitude
    string channel
    string trigger
    int value_before
    int value_after
  }
  beat_records {
    text surface
    string pov_anchor
  }
  beat_true_states {
    bigint character_id
    text private_text
  }
  provider_credentials {
    bigint user_id
    string provider
    text api_key "encrypted, Hidden"
    string last_four
  }
```

> Authoring-realm entities (top block) are immutable at runtime; save-realm entities (bottom block) are per-`play_session`. `beat_true_states` is deliberately a child table of `beat_records`, not a column, to make cross-feeding structurally impossible.
>
> **`sessions` → `play_sessions` (Sprint 4, PH-17).** DATABASE.md §4.1 names the save "session"; it is built as **`play_sessions`** because the framework owns the `sessions` table for the database session driver. Child FK columns keep the spec name `session_id`.
>
> **Ownership.** `stories`, `provider_credentials`, and (as of Sprint 5) **`llm_calls`** carry `user_id` (`BelongsToOwner`; the `OwnerScope` auto-filters them). `llm_calls.user_id` is **nullable** (PH-20): both `session_id` and `story_id` are nullable — authoring-time calls have neither — so neither can carry ownership, and nullable keeps unauthenticated console/seed inserts valid while real calls always stamp the owner. Authoring children (chapters, scenes, characters, …) and other save-realm children have **no `user_id`** — they are isolated **transitively** through their story / save and `cascadeOnDelete` when it is removed. The three Sprint-3 deferred FKs are now real constraints (PH-16 resolved).
>
> **Global libraries (no story/session FK, app-wide):** `register_archetypes`, `universal_priors`, `character_archetypes` (ADR 0018), `prompt_blocks` (ADR 0020), and `model_profiles` (ADR 0017; `story_id` nullable for per-story override) sit outside both realms — only `register_archetypes → registers` is FK-scoped and shown above.
