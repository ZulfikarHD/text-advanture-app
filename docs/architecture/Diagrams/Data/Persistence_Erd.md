# Persistence ERD

Two-realm schema, the living detail of [ADR 0012](../../../adr/0012-persistence-schema.md). See [../../DATABASE.md](../../DATABASE.md) for column detail. The **authoring realm + `stories` ownership** (solid edges below) is **built as of Sprint 3** (S-4.1.1); the save realm and global libraries land in Sprint 4.

```mermaid
erDiagram
  users ||--o{ stories : "owns (BelongsToOwner, Sprint 3)"
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
  register_archetypes ||--o{ registers : "based on (FK deferred, PH-16)"
  character_archetypes ||--o{ characters : "seeds creation (ADR 0018)"
  characters ||--o{ sensitivities : declares

  stories ||--o{ sessions : "forked into"
  sessions ||--o{ relationship_edges : holds
  relationship_edges ||--o{ edge_axes : "live axes"
  relationship_edges ||--o{ axis_deltas : "append-only log"
  sessions ||--o{ internal_states : holds
  internal_states ||--o{ active_emotions : "own-clock decay"
  sessions ||--o{ beat_records : commits
  beat_records ||--o{ beat_true_states : "private, never cross-fed"
  beat_records ||--o{ beat_witnesses : "fidelity"
  sessions ||--o{ nudges : holds
  sessions ||--o{ review_items : "shared review gate"
  sessions ||--o{ llm_calls : "append-only call log (ADR 0017)"

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
```

> Authoring-realm entities (top block) are immutable at runtime; save-realm entities (bottom block) are per-`session`. `beat_true_states` is deliberately a child table of `beat_records`, not a column, to make cross-feeding structurally impossible.
>
> **Ownership (Sprint 3).** Only `stories` carries `user_id` (`BelongsToOwner` + `StoryPolicy`); the `OwnerScope` auto-filters `stories` alone. Authoring children (chapters, scenes, characters, …) have **no `user_id`** — they are isolated **transitively** through their story and `cascadeOnDelete` when it is removed (see PH-16).
>
> **Global libraries (no FK, app-wide):** `register_archetypes`, `universal_priors`, `character_archetypes` (ADR 0018), `prompt_blocks` (ADR 0020), and `model_profiles` (ADR 0017; `story_id` nullable for per-story override) sit outside both realms — omitted from the diagram's relationships since they are not FK-scoped to a story or session.
