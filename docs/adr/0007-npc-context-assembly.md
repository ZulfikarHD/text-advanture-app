# 0007 — NPC context assembly

- **Status:** Proposed
- **Date:** 2026-06-04

## Context

All behaviour-subsystem data (card, edges, internal state, register, masks) must be compiled
into the actual NPC LLM call. This is where the **numbers -> language** translation happens,
and it is also the **context-isolation boundary**: the one place that guarantees an NPC sees
only its own data plus what it witnessed (per the brief's three-agent isolation rule).

## Decision

### The assembler is a compiler + an isolation boundary

It reads **only** this NPC's own card/edges/state + the witnessed scene + the beat nudge. It
never reads the beat doc, other characters' cards, other edges, or narrator instructions —
isolation is enforced by construction.

### Pipeline

```
INPUTS (this NPC only)                  COMPILE                    CALL
card (+ knowledge_boundary)  -------->  identity block          ┐
internal state               -------->  self block             │
edges: thisNPC -> present chars ----->  relationship snapshot   ├─> system prompt
   (value x awareness)                  (folded)                │
topic_flags + mask + states  -------->  expression masks        │
resolved register (0006)     -------->  behavioral directives   │
beat nudge (ADR 0008)        -------->  psychological nudge     │
scene config                 -------->  scene rules             ┘
recorder surface (ADR 0010)  -------->  scene excerpt  --------> user prompt + "How does X respond?"
   (witness-filtered to this NPC, then POV-projected per ADR 0009)
```

### Numbers -> language

- Each live axis renders via a **(value x awareness)** translation (extends the brief's 1-D
  table). Own-perspective only — never expose how others feel or other edges.
- **Awareness: folded in the rendered prompt, separate in the data.** `value` and
  `awareness.mode` stay distinct fields (ADR 0002); the compiler merges them into one phrase so
  the actor model never sees a capped feeling stated plainly. This is the guardrail against a
  blind-spot character (e.g. Luna's romantic axis) becoming self-aware. A capped-high axis
  renders as "feels it, cannot name it" — never as the plain feeling.

### Compilation method: LLM-compiled, two-stage turn

- A **compile** call turns structured state -> folded prose blocks; then an **act** call
  produces the response.
- To control cost/latency, **cache stable blocks** (identity, register) within a scene and
  **recompile only volatile blocks** (the snapshot, after deltas land).

### Register -> behavioral directives

The resolved register (ADR 0006) compiles to concrete behavior rules in the prompt
(`flow=extends` -> "never close without a hook", `sincerity=rerouted` -> "sincere words
fail", etc.).

### Witnessing: per-beat witness log

When the narrator records a beat, it tags **who perceived it and at what fidelity**
(full / overheard / partial). The assembler filters the scene excerpt to
`witnessed_by contains thisNPC`. Enables secrets, overheard fragments, asymmetric info.

This recording step is formalized as the **Recorder (ADR 0010)**. The scene excerpt the assembler
consumes is the recorder's `surface` layer, **POV-projected per ADR 0009**; the assembler only ever
reads `surface` — never another character's private `true_state`.

### Card depth by model tier

Carried from the brief: **full card for major NPCs (Sonnet), compressed for minor NPCs
(Haiku)**.

## Alternatives considered

- **Deterministic templating of blocks.** Cheaper and more consistent, but #2 chose LLM
  compilation for naturalness; templating remains a fallback/optimization.
- **Single-stage call with raw structured data.** Rejected: leak risk (model peeks at capped
  feelings) and weaker isolation.
- **Separate AWARENESS block in the prompt.** Rejected: leak risk for blind-spot characters.
- **Presence-flag witnessing.** Rejected: cannot represent secrets / partial perception.

## Consequences

- **Two LLM calls per NPC turn** (compile + act) -> the caching strategy above is required, not
  optional.
- The **narrator/recording step must tag beats with witnesses + fidelity** — now formalized as the
  **Recorder (ADR 0010)**, whose `surface` layer (POV-projected per **ADR 0009**) is the scene excerpt.
- The **beat/nudge system is now a named dependency** — the nudge block is a slot it must fill (the
  directed-pressure nudge, **ADR 0008**).
- The assembler is the enforcement point for context isolation; security/correctness reviews
  focus here.
