# Agent context isolation

How the three narrative agents + the Director see different slices of truth. The NPC context assembler (ADR 0007) is the mechanical enforcement point. Source: [../../../adr/0007-npc-context-assembly.md](../../../adr/0007-npc-context-assembly.md), brief "three agents".

```mermaid
flowchart TB
  subgraph truth [Engine-held truth]
    beatDoc[Beat doc - authorial intent]
    mesh[Full relationship mesh]
    history[Scene history + true_state]
  end

  beatDoc --> narrator
  mesh --> narrator
  history --> narrator

  narrator[NARRATOR agent]
  narrator -->|"prose (POV contract)"| prose[Rendered prose]
  narrator -->|"records beat: surface + true_state + witnessed_by"| recorder[RECORDER step]

  prose --> player[PLAYER - reads prose only]
  player -->|"input + delivery/tone"| narrator

  recorder -->|"surface only, witness-filtered, POV-projected, decoded"| assembler[NPC ASSEMBLER - isolation boundary]
  ownData["NPC own card + own-perspective edges + own true_state via SELF"] --> assembler
  nudge["Leak-checked nudge (only authorial channel)"] --> assembler
  assembler --> npc[NPC agent]
  npc -->|"in-character response"| narrator

  director[DIRECTOR / Engine]
  director -.->|"stall flag - read by engine only, injected into NO narrative agent"| director
  director -->|"word budget / escalation level"| nudge
```

## Read this as

- The **NPC** never receives `beat doc`, the full `mesh`, other characters' cards/edges, or any `true_state` but its own (delivered via the SELF block, not the excerpt).
- The **narrator** holds omniscient truth but is bound by the mesh-awareness rule (atmosphere / body-language only) and the POV contract.
- The **Director** is out-of-context: the stall flag never enters a narrative prompt.

See also: [Context-memory + leak guards in ARCHITECTURE.md](../../ARCHITECTURE.md#7-three-leak-guards--one-review-gate).
