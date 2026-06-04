# Persistence ERD (draft)

Two-realm schema for open item **O4**. See [../../DATABASE.md](../../DATABASE.md) for column detail. **DRAFT** until the persistence ADR lands.

```mermaid
erDiagram
  stories ||--o{ characters : has
  stories ||--o{ chapters : has
  chapters ||--o{ scenes : has
  scenes ||--o{ beats : has
  characters ||--o{ character_cards : "compiled per chapter"
  characters ||--o{ registers : instantiates
  register_archetypes ||--o{ registers : "based on"
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
