# 0004 — Time decay + latched scars

- **Status:** Proposed
- **Date:** 2026-06-04

## Context

Should axis values move with **time alone**, no events? Two problems:

1. **What is "time"?** The story is sliced per-beat / per-chapter, so beat *count* is not time —
   one beat may be "five minutes later," another "three weeks later."
2. **High feelings should leave permanent marks.** A fear that spikes to 80 becomes a flaw /
   trauma that does not simply fade; a trigger can re-spike it; only deliberate effort clears
   it. The same is true in reverse for commitment (affection that peaks high should not crater
   over small slights).

## Decision (proposed)

### Decay runs on the narrative clock

- **When computed:** at scene-unit / chapter boundaries (piggyback on the compression step).
- **How much:** scaled by **declared in-world elapsed time**, not by beat/chapter count. The
  narrator/beat doc declares time skips ("three weeks pass"); same-scene continuation produces
  ~no decay. This is robust to how finely the story is chunked — a one-month gap yields the
  same decay whether sliced as one chapter or twenty beats.
- Decay pulls the current value toward `baseline`, **stopping at any latched floor**.

### Latched scars unify trauma and commitment

A single mechanism: when an axis's **high-water mark** crosses a threshold, it sets a permanent
floor (a "scar" / set-point).

```yaml
# per-axis additions
peak:            { up: 82, down: 0 }    # high-water marks per direction
latch_threshold: 80                     # crossing this latches a floor
latch_retain:    0.6                    # scar floor = retain x peak
scar:
  latched: true
  floor: 49                             # decay can never cross this
  source: "kidnapping, Saga 2"
  triggers: [confinement, "Solenne's scream"]   # may RUPTURE-spike back toward peak
  overcome_by: null                     # set only when a growth-arc rupture clears it
baseline: 0
```

Consequences of the unification:

- **Commitment = a positive latch** (affection peaks high -> high floor -> won't abandon over
  small things).
- **Trauma / PTSD = a high-magnitude latch** (fear peaks high -> permanent floor -> lingering flaw).
- **Decay** fades the acute value but never crosses the latched floor — the feeling cools, the
  scar stays.
- **Triggers** stored on the scar can fire a rupture (see ADR 0003) that spikes the value back
  toward `peak` — the "something reminds her" jolt.
- **Only a deliberate growth-arc rupture** can lower/clear a latched floor ("choose to overcome
  it"). Time and ordinary drift cannot.
- **Effective floor = `max(static soft_floor, scar.floor)`.** This lets us delete the separate
  `commit` block from ADR 0002's draft — commitment is just a latch.
- Committed edges and latched floors are **decay-exempt** by construction.

## Alternatives considered

- **No decay.** Rejected: relationships should cool from neglect.
- **Decay by beat/chapter count.** Rejected: a beat may be seconds or weeks of in-world time.
- **Decay to baseline ignoring scars.** Rejected: erases character development and trauma.

## Consequences

- The narrator / beat doc must **declare time skips** as structured data (already implied by prose).
- Adds `peak`, `latch_threshold`, `latch_retain`, `scar`, `baseline` to the per-axis schema and
  removes the `commit` block (schema cleanup in ADR 0002).
