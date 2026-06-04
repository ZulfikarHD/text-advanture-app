# Feature domain — NPC behaviour

How a character **is**, **feels**, **changes**, **scars**, and **expresses** — and how all of it compiles into one in-character LLM call.

> **Status: designed & accepted.** This subsystem is fully specified by **ADR 0001–0007**. Feature docs here are thin and **reference the ADRs** rather than restate them (single source of truth).

## Map of decisions

| Concern | ADR |
|---------|-----|
| Three-layer character data (bible → card → edges → internal state) | [ADR 0001](../../adr/0001-character-data-three-layer-separation.md) |
| Directed relationship-edge schema (axes, awareness, bounds, register) | [ADR 0002](../../adr/0002-relationship-edge-schema.md) |
| Delta engine: drift vs rupture + propose→review→commit | [ADR 0003](../../adr/0003-delta-engine-two-channels-and-appraisal-review.md) |
| Narrative-time decay + latched scars | [ADR 0004](../../adr/0004-time-decay-and-latched-scars.md) |
| Appraisal trigger taxonomy (priors + sensitivities) | [ADR 0005](../../adr/0005-appraisal-trigger-taxonomy.md) |
| Register / relational-mode system | [ADR 0006](../../adr/0006-register-relational-mode-system.md) |
| NPC context assembly (compiler + isolation boundary) | [ADR 0007](../../adr/0007-npc-context-assembly.md) |

## When to add a feature doc here

Only when implementing a concrete slice (e.g. "edge decay job", "appraisal proposal endpoint"). Use the template and link the ADR it realizes. Terms are defined in [../../guides/glossary.md](../../guides/glossary.md).
