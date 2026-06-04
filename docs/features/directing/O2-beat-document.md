# O2 — Beat document + `BEAT_DONE` + boundary events

> **Status:** Closed (designed) → **[ADR 0015](../../adr/0015-beat-document-and-boundaries.md)** (`Proposed`) · **Domain:** directing · depends-on 0008; touches 0003/0004 · **Last Updated:** 2026-06-04

## Summary

The beat document is the **nudge producer** that ADR 0008 names as a hard dependency. This feature defines how a beat declares its intent/goal/word-budget, how the psychological nudge is derived from it, what fires `BEAT_DONE`, and the scene/chapter boundary events that trigger drift-batching and decay.

## Goal & non-goals

- **Goal:** an authoring format a *pantser of route* can write, that compiles into a leak-checked, knowledge-bounded nudge (ADR 0008) and drives pacing by word budget.
- **Non-goals:** the narrator loop that consumes it ([O1](../narrator/O1-narrator-loop.md)); persistence shape (drafted in [DATABASE.md](../../architecture/DATABASE.md) `beats`).

## Scope (from GAPS O2)

- **Authoring format:** intent / goal / word-budget (the signals the nudge ladder depends on).
- **Nudge derivation:** author-side omniscient beat intent → compiled bounded nudge (the *derived* mode of ADR 0008), validated at the assembler boundary.
- **`BEAT_DONE` criteria:** goal-satisfaction signal + word-budget exhaustion.
- **Boundary events:** the chapter → scene → beat hierarchy emits explicit events that trigger drift-batching (0003) and decay (0004); today only `BEAT_DONE` is named.

## Agent / isolation impact

Authoring is **omniscient** (author side) and must **never cross** the boundary raw — the nudge-compile leak guard (ADR 0008) is the second leak guard. Beat intent is validated against the target's `knowledge_boundary`.

## Open questions

- Free-text intent + structured goal, or fully structured beats?
- How is the goal-satisfaction signal measured (LLM judge vs explicit beat assertions)?
- Word budget per beat AND per chapter — both authored, or chapter derived from beats?

## Related Documentation

- ADRs: [0008](../../adr/0008-psychological-nudge.md), [0003](../../adr/0003-delta-engine-two-channels-and-appraisal-review.md), [0004](../../adr/0004-time-decay-and-latched-scars.md)
- Architecture: [ARCHITECTURE.md §3,§4,§8](../../architecture/ARCHITECTURE.md)
- Open items: [GAPS O2](../../adr/GAPS.md)
