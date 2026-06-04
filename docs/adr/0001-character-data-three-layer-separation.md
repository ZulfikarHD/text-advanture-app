# 0001 — Character data: three-layer separation

- **Status:** Proposed
- **Date:** 2026-06-04

## Context

A "character" bundles three very different kinds of information: who they *are* (authored
identity), how they *feel about others* (evolving relationships), and what they're *going
through right now* (transient emotion). If these live in one object:

- Session-end writes that evolve emotional state risk clobbering hand-authored identity.
- "Designing the character" (authoring) constantly fights "evolving the character" (runtime).
- The full authoring bible (e.g. `luna-archi.md`) contains future-arc spoilers an NPC must
  never act on.

## Decision

Split character data into **three stores, separated by lifecycle and ownership**, plus a
distinction between the authoring source and the runtime card:

1. **Source bible** — the full authored document (e.g. `luna-archi.md`, author notes).
   Human-facing, contains the entire arc. **Never injected** into an agent.
2. **Character card** — a *compiled, spoiler-free, current-state slice* of the bible. This is
   what the NPC agent actually sees. Immutable at runtime. Must include a
   **`knowledge_boundary`**: what the character currently knows and does *not* know, so the
   agent cannot leak its own future.
3. **Relationship edges** — mutable, **per directed pair** (`from -> to`). Written at scene/
   session boundaries. See ADR 0002.
4. **Internal state** — mutable, **per character**, transient (active emotions, motivation,
   masks). Decays on its own clock, separate from relationship edges.

The **player** has an appearance-only card and **no simulated outgoing edges** — the human
supplies their own behavior; NPCs only hold edges *toward* the player.

## Alternatives considered

- **Single character object.** Rejected: clobbering risk, spoiler leakage, authoring/runtime conflict.
- **Two layers (static identity + one mutable blob).** Rejected: conflates per-pair relationship
  values with per-character transient emotion, which change on different clocks and scopes.

## Consequences

- Need a **compile step** that derives the runtime card from the source bible.
- `knowledge_boundary` becomes a first-class, must-maintain field (epistemic state per chapter).
- The same three-layer split drives how context is assembled for each agent turn.
