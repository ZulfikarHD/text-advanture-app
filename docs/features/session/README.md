# Feature domain — Session

State machine, persistence, context-memory layers, the per-character internal-state schema, and the player-facing UI.

## Map

| Concern | ADR / item | Status |
|---------|-----------|--------|
| Session state machine (the conductor) | brief — *session state machine* | Decided (spine) |
| Context-memory layers (immediate / scene-summary / chapter-log / lorebook) | brief — *context memory layers* | Decided (model) |
| Internal-state schema (active emotion, motivation, masks, own-clock decay) | **O3** ([GAPS](../../adr/GAPS.md)) | **Open** → [O3-internal-state-schema.md](./O3-internal-state-schema.md) |
| Persistence + tech stack + UI + shared review-gate surface | **O4** ([GAPS](../../adr/GAPS.md)) | Tech stack + LLM client locked ([0011](../../adr/0011-tech-stack.md)/[0017](../../adr/0017-llm-orchestration-openrouter.md)); schema drafted → [O4-persistence-and-ui.md](./O4-persistence-and-ui.md) |
| Prompt block registry (machine-readable block specs driving assembly + docs) | **O7** ([GAPS](../../adr/GAPS.md)) | **Designed** → [O7-prompt-block-registry.md](./O7-prompt-block-registry.md) ([ADR 0020](../../adr/0020-prompt-block-registry.md)) |

## Note

O3 is "the one place the *complete* NPC subsystem is actually incomplete" (GAPS): ADR 0001 names internal state and ADR 0007 injects it as `[SELF]`, but no ADR defines its shape. O4 covers the database (drafted in [../../architecture/DATABASE.md](../../architecture/DATABASE.md)) and the review-gate UI shared by deltas (0003) / nudge-compile (0008) / beat records (0010).
