# 0002 — Relationship-edge schema

- **Status:** Proposed
- **Date:** 2026-06-04

## Context

Relationships must be **asymmetric** (`A->B != B->A`), **bounded** (different characters
open up to different ceilings), and **style-bearing** (the same feeling is expressed
differently per relationship). A flat affection scalar cannot do this: e.g. Luna shows the
*same* warmth to a classmate and to Vixia but with opposite *behavior* (one-way-mirror vs.
transparent mess). The number gives intensity/direction; it cannot give style.

Core principle:

```
rendered behavior = axis value  x  expression mask  x  (card voice + relational register)
```

## Decision

Model each relationship as a **directed, owner-perspective edge** (`from`'s self-perceived
view of `to`; can be self-deceived). Only the **live axes** for that edge are instantiated.

```yaml
edge:
  from: luna-archi
  to:   vixia-archi
  register:                     # behavioral-grammar selector
    base: transparent_mess
    overrides:                  # conditional swaps
      - { when: target_shows_romantic_interest, use: boundary_protection }
  axes:
    affection:
      value: 96                 # -100..+100, owner self-perceived
      awareness: { mode: auto } # auto (derive tier from |value|) | capped (feels, can't access)
      bounds: { soft_floor: 88, soft_cap: 100, hard_floor: 40, hard_cap: 100 }
      rates: { gain: 0.4, loss: 0.2 }   # asymmetric per-axis (trust: gain low / loss high)
      baseline: 0
    # ... other live axes
  topic_flags:                  # edge-scoped expression masks
    - { topic: the_diagnosis, effect: knows_but_wont_admit }
  meta:
    seeded_from: vixia_sibling_bond   # provenance (disposition prior, see below)
    pending_drift: {}                 # accumulates during scene, applied at boundary
```

Key rulings:

- **Awareness is computed from value at read time** (no stored tier). Tiers: `0-39 none`,
  `40-59 vague`, `60-79 subconscious`, `80+ conscious`. Override with `mode: capped` to model
  blind spots (feels strongly, can't consciously reach it).
- **Bounds are invisible to the character** — caps/floors are authorial; the character just
  lives inside them. Drift is clamped to **soft** bounds; only ruptures reach the **soft<->hard**
  band (see ADR 0003). A floor may also be created dynamically by a latch (see ADR 0004);
  effective floor = `max(static soft_floor, latched floor)`.
- **Register is authored, not derived** from the numbers. Registers are reusable
  behavioral-grammar definitions (e.g. `koakuma_default`, `transparent_mess`,
  `boundary_protection`) referenced by id; an edge picks a `base` + conditional `overrides`.
- **Topic flags** are narrow, edge-scoped masks (e.g. mutual-deception on one topic), distinct
  from the card's global mask.
- **Disposition priors** (defined on the card) seed a new edge's initial axis values, bounds,
  and register from *traits of the target* (gender, demeanor, faction, shows-interest). New
  edges are not born neutral.

## Alternatives considered

- **Single affection scalar.** Rejected: cannot represent "warm but guarded" (high affection +
  low trust), which is the whole point of the multi-axis model.
- **Symmetric relationships.** Rejected: NPC-to-NPC realism requires asymmetry.
- **Deriving register from axis values.** Rejected: Luna disproves it — identical values,
  opposite behavior. Register must be authored data.

## Consequences

- Registers live in a shared library, referenced by id from edges.
- Cards need disposition-prior functions to seed new edges.
- `awareness.mode: capped` is the mechanism for self-deception / blind spots.
- The edge is the single most behavior-determining structure; gets the most schema attention.
