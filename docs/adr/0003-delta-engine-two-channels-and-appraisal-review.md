# 0003 — Delta engine: two channels + appraisal review gate

- **Status:** Accepted
- **Date:** 2026-06-04

## Context

Relationship axes must evolve **slowly** under ordinary events but allow occasional **rug-pulls**
(betrayal, confession). Changes must be **explainable** ("why did trust drop?") and the
author/player must be able to **override** them. Doing all delta math at session-end is too
coarse — a betrayal must change behavior *now*, not next session.

## Decision

### Two delta channels

Every axis is moved by exactly two kinds of input, with different rules:

| | **DRIFT** (ordinary) | **RUPTURE** (high-impact, flagged) |
|---|---|---|
| Source | per-scene appraisal | betrayal, confession, profound understanding |
| Magnitude | tiny, `rates`-scaled | large, explicit |
| Clamped to | **soft** bounds | **hard** bounds |
| Can rewrite bounds / latch scars? | no | **yes** (= character development) |
| Can flip register? | no | **yes** |
| Applied | batched at scene boundary | immediately, in-scene |

This gives the desired feel: a hundred small kindnesses nudge trust up slowly and cannot
break a ceiling; one betrayal blows through the soft floor, can permanently lower the hard
floor, and can swap the register.

### Appraisal proposes; a review gate commits

The appraisal LLM never writes the edge directly. It emits **delta proposals**; a review gate
sits before commit:

```
appraisal LLM  ->  delta proposals  ->  REVIEW gate  ->  commit to edge (+ audit log)
```

```yaml
proposal:
  edge: luna-archi -> henrik
  axis: trust
  direction: down
  magnitude: -18          # LLM-judged; author/player may edit
  channel: rupture
  trigger: "Henrik repeated Luna's private remark to the group after she asked him not to."
  confidence: 0.8
```

Rulings:

- **Magnitude is LLM-judged but human-adjustable.** At the gate the player/author can
  **accept, edit, or reject** each proposal before commit. (Some triggers may carry authored
  fixed magnitudes; the LLM judges the rest.)
- **`trigger` is mandatory.** Every committed axis movement carries a human-readable reason.
  No silent deltas.
- **Committed proposals form an append-only audit log** per edge — the raw material for the
  relationship-viewer UI and for debugging a character that "feels off."

## Alternatives considered

- **Session-end-only deltas.** Rejected: ruptures must act immediately.
- **Direct writes, no review.** Rejected: no author control, no explainability.
- **Fully authored fixed magnitudes.** Rejected as the default: too rigid; kept as an option
  for specific high-impact triggers.

## Consequences

- Cost is managed by **batching drift at scene boundaries**; only ruptures run immediately.
- The mandatory `trigger` + audit log are load-bearing for UI and debugging.
- The review gate is a UX surface to design (inline accept/edit/reject).
