# Feature domain — Session

State machine, persistence, context-memory layers, the per-character internal-state schema, and the player-facing UI.

## Map

| Concern | ADR / item | Status |
|---------|-----------|--------|
| Session state machine (the conductor) | brief — *session state machine* | Decided (spine) |
| Context-memory layers (immediate / scene-summary / chapter-log / lorebook) | brief — *context memory layers* | Decided (model) |
| Internal-state schema (active emotion, motivation, masks, own-clock decay) | **O3** ([GAPS](../../adr/GAPS.md)) | **Open** → [O3-internal-state-schema.md](./O3-internal-state-schema.md) |
| Persistence + tech stack + UI + shared review-gate surface | **O4** ([GAPS](../../adr/GAPS.md)) | Tech stack locked; schema drafted → [O4-persistence-and-ui.md](./O4-persistence-and-ui.md) |

## Note

O3 is "the one place the *complete* NPC subsystem is actually incomplete" (GAPS): ADR 0001 names internal state and ADR 0007 injects it as `[SELF]`, but no ADR defines its shape. O4 covers the database (drafted in [../../architecture/DATABASE.md](../../architecture/DATABASE.md)) and the review-gate UI shared by deltas (0003) / nudge-compile (0008) / beat records (0010).
