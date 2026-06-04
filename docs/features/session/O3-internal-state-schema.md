# O3 — Internal-state schema

> **Status:** Proposed (open) · **Domain:** session · **Owning ADR(s):** amend 0001 (or a small new ADR); touches 0004/0005/0006/0007/0010 · **Last Updated:** 2026-06-04

## Summary

The one place the "complete" NPC subsystem is actually incomplete. ADR 0001 *names* a per-character internal-state layer and ADR 0007 injects it as `[SELF]` every NPC turn; ADR 0006 step 4 reads "current emotional state"; ADR 0010 reads awareness+mask — but **no ADR defines its shape**. This feature finishes the layer that already exists (don't invent a new one).

## Goal & non-goals

- **Goal:** define active emotions, `motivation`, mask data, what *writes* transient emotions, and the "own clock" decay.
- **Non-goals:** relationship-edge axes (those are ADR 0002, a different store and clock).

## Scope (from GAPS O3)

- **Active emotion** — shape, intensity, source, and its own-clock decay (distinct from edge decay, ADR 0004).
- **`motivation`** — referenced by the interaction queue and the `[SELF]` block.
- **Mask data** — the global/active-state masks the expression layer reads (ADR 0006/0007), distinct from edge `topic_flags`.
- **Writer** — what *produces* transient emotion. ADR 0005 moves edge axes, **not** internal emotions; this gap must be closed (likely appraisal also emits emotion deltas, or the recorder does).

## Data touched

`internal_states` + `active_emotions` in [DATABASE.md](../../architecture/DATABASE.md) (currently skeletal). This feature pins their columns.

## Open questions

- Does appraisal (0005) emit transient-emotion proposals alongside axis deltas, through the same review gate?
- Is `mood` a derived rollup of `active_emotions`, or an independent field?
- Decay cadence for emotions — per beat, or narrative-time like edges?

## Related Documentation

- ADRs: [0001](../../adr/0001-character-data-three-layer-separation.md), [0004](../../adr/0004-time-decay-and-latched-scars.md), [0005](../../adr/0005-appraisal-trigger-taxonomy.md), [0006](../../adr/0006-register-relational-mode-system.md), [0007](../../adr/0007-npc-context-assembly.md)
- Architecture: [DATABASE.md §4](../../architecture/DATABASE.md) · Open items: [GAPS O3](../../adr/GAPS.md)
