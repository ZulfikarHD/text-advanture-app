# Architecture Decision Records

This folder is the **append-only decision log** for the Directed Interactive Novel Engine.

- Each file records **one decision**: its context, the choice made, the alternatives rejected, and the consequences.
- ADRs are **dated and immutable**. We don't rewrite history — if a decision changes, we add a new ADR that supersedes the old one (and mark the old one `Superseded by NNNN`).
- The consolidated "what the system is right now" snapshot lives separately in
  [`../directed_interactive_novel_engine_v2.html`](../directed_interactive_novel_engine_v2.html).
  That brief gets refreshed only when a cluster of decisions stabilizes; the ADRs are where the *why* lives.

## Status values

| Status | Meaning |
|--------|---------|
| `Proposed` | Drafted, awaiting confirmation |
| `Accepted` | Locked, build to this |
| `Superseded by NNNN` | Replaced by a later ADR |

## Format

Lightweight [MADR](https://adr.github.io/madr/): **Context -> Decision -> Alternatives considered -> Consequences**.

## Open items

The NPC behaviour subsystem (ADR 0001–0010) is built. What's still open — scoped to the brief's
runtime **flow**, not a generic-backend audit — lives in [`GAPS.md`](GAPS.md): narrator internals +
turn loop, beat document + `BEAT_DONE`, the internal-state schema, and persistence/tech-stack/UI.
Close an item by writing the ADR named in its "home" and striking it.

## Index

| ADR | Title | Status |
|-----|-------|--------|
| [0001](0001-character-data-three-layer-separation.md) | Character data: three-layer separation | Accepted |
| [0002](0002-relationship-edge-schema.md) | Relationship-edge schema | Accepted |
| [0003](0003-delta-engine-two-channels-and-appraisal-review.md) | Delta engine: two channels + appraisal review gate | Accepted |
| [0004](0004-time-decay-and-latched-scars.md) | Time decay + latched scars | Accepted |
| [0005](0005-appraisal-trigger-taxonomy.md) | Appraisal trigger taxonomy | Accepted |
| [0006](0006-register-relational-mode-system.md) | Register / relational-mode system | Accepted |
| [0007](0007-npc-context-assembly.md) | NPC context assembly | Accepted |
| [0008](0008-psychological-nudge.md) | Psychological nudge (directed-pressure model) | Accepted |
| [0009](0009-pov-projection.md) | POV projection (perspective leak guard) | Accepted |
| [0010](0010-recorder-mechanics.md) | Recorder mechanics (beat record + legibility) | Accepted |
