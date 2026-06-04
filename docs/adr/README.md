# Architecture Decision Records

This folder is the **decision log** for the Directed Interactive Novel Engine.

- Each file records **one decision**: its context, the choice made, the alternatives rejected, and the consequences.
- ADRs are **dated**, and follow a lifecycle. **While `Proposed` (we are still planning — nothing is built to them yet) they are edited in place** as the design evolves; rigid append-only would be needless churn before any code exists. Once an ADR is **`Accepted`** (we have committed to build to it), it becomes **append-only**: a genuine reversal is a *new* ADR that supersedes the old one (marked `Superseded by NNNN`); in-place edits are then limited to clarifications/consistency. **The whole set is currently `Proposed` — we are in the planning stage.** The current truth always lives in the snapshot docs (the brief, `ARCHITECTURE.md`, `DATABASE.md`), so a superseded ADR never reads as a live contradiction.
- The consolidated "what the system is right now" snapshot lives separately in
  [`../directed_interactive_novel_engine_v2.html`](../directed_interactive_novel_engine_v2.html).
  That brief gets refreshed only when a cluster of decisions stabilizes; the ADRs are where the *why* lives.
- The structured docs that sit alongside these ADRs follow the
  [`../DOCUMENTATION_STRUCTURE.md`](../DOCUMENTATION_STRUCTURE.md) standard: see
  [`../architecture/ARCHITECTURE.md`](../architecture/ARCHITECTURE.md) (system snapshot),
  [`../architecture/DATABASE.md`](../architecture/DATABASE.md) (proposed schema), and the
  [`../features/`](../features/README.md) open-item specs (O1–O4).

## Status values

| Status | Meaning |
|--------|---------|
| `Proposed` | Drafted & confirmed in planning; **editable in place** until we build to it |
| `Accepted` | Locked, building to this — **append-only from here** (supersede to change) |
| `Superseded by NNNN` | Replaced by a later ADR |

## Format

Lightweight [MADR](https://adr.github.io/madr/): **Context -> Decision -> Alternatives considered -> Consequences**.

## Open items

The NPC behaviour subsystem (ADR 0001–0010) and the narrator-side / authoring design
(ADR 0013–0016) are now drafted (all `Proposed`). The original flow gaps are closed at the design
level: **O1** → ADR 0016, **O2** → ADR 0015, **O3** → ADR 0014, and the authoring/compile gap →
ADR 0013. A second design cluster adds the **LLM client** (OpenRouter, ADR 0017), **character
creation** + archetype library (ADR 0018, **O5**), **outline compilation** (ADR 0019, **O6**), and
the **prompt block registry** (ADR 0020, **O7**). What remains is **implementation** (migrations,
the compile→act orchestration, UI) plus the **O4 UI ADR** — tracked in [`GAPS.md`](GAPS.md).

## Index

| ADR | Title | Status |
|-----|-------|--------|
| [0001](0001-character-data-three-layer-separation.md) | Character data: three-layer separation | Proposed |
| [0002](0002-relationship-edge-schema.md) | Relationship-edge schema | Proposed |
| [0003](0003-delta-engine-two-channels-and-appraisal-review.md) | Delta engine: two channels + appraisal review gate | Proposed |
| [0004](0004-time-decay-and-latched-scars.md) | Time decay + latched scars | Proposed |
| [0005](0005-appraisal-trigger-taxonomy.md) | Appraisal trigger taxonomy | Proposed |
| [0006](0006-register-relational-mode-system.md) | Register / relational-mode system | Proposed |
| [0007](0007-npc-context-assembly.md) | NPC context assembly | Proposed |
| [0008](0008-psychological-nudge.md) | Psychological nudge (directed-pressure model) | Proposed |
| [0009](0009-pov-projection.md) | POV projection (perspective leak guard) | Proposed |
| [0010](0010-recorder-mechanics.md) | Recorder mechanics (beat record + legibility) | Proposed |
| [0011](0011-tech-stack.md) | Tech stack (Laravel 13 + Vue/Inertia v3 + Wayfinder + MySQL/MariaDB) | Proposed |
| [0012](0012-persistence-schema.md) | Persistence schema (two-realm, multi-save) | Proposed |
| [0013](0013-authoring-and-compile-pipeline.md) | Authoring & compile pipeline (bible → card / registers / sensitivities / lorebook) | Proposed |
| [0014](0014-internal-state-schema.md) | Internal-state schema (the `[SELF]` layer) | Proposed |
| [0015](0015-beat-document-and-boundaries.md) | Beat document + `BEAT_DONE` + boundary events | Proposed |
| [0016](0016-narrator-agent-and-turn-loop.md) | Narrator agent + turn loop | Proposed |
| [0017](0017-llm-orchestration-openrouter.md) | LLM orchestration & OpenRouter client (model-role tiering, `llm_calls`) | Proposed |
| [0018](0018-character-creation-pipeline.md) | Character creation pipeline (AI / manual / hybrid) + archetype library | Proposed |
| [0019](0019-outline-compilation.md) | Outline compilation (free outline → chapters / scenes / beats) | Proposed |
| [0020](0020-prompt-block-registry.md) | Prompt block registry (machine-readable block specs) | Proposed |
