# O7 — Prompt block registry

> **Status:** Proposed · **Domain:** session · **Owning ADR(s):** [ADR 0020](../../adr/0020-prompt-block-registry.md) (registry), depends-on [ADR 0007](../../adr/0007-npc-context-assembly.md) (assembler), [ADR 0016](../../adr/0016-narrator-agent-and-turn-loop.md) (context inventory) · **Last Updated:** 2026-06-04

## Summary

A single machine-readable registry (`prompt_blocks`) defining every prompt block — `[IDENTITY]`,
`[SELF]`, `[MASKS]`, `[NUDGE]`, `[SCENE EXCERPT]`, the narrator's `[POV CONTRACT]` /
`[MESH-AWARENESS]` / `[BEAT]`, etc. Each row carries the block's **purpose**, its **source
producers**, the **compile instruction** the assembler uses to fold it, the **leak rules** that gate
it, and its **order**. The app consumes the registry to build prompts; the human block reference /
glossary renders from the same rows. One definition, two surfaces — code and docs cannot drift.

## Goal & non-goals

- **Goal:** make "what each block is, where it comes from, and what it must never leak" a single
  authoritative, app-consumed record that also documents itself.
- **Non-goals:** inventing new leak guards (the registry only *names which* of the three existing
  guards applies); per-story bespoke blocks (deferred to a `stories.settings` override later).

## Behavior

When the assembler ([ADR 0007](../../adr/0007-npc-context-assembly.md)) / narrator builder ([ADR 0016](../../adr/0016-narrator-agent-and-turn-loop.md)) build a prompt: select active
blocks for the `agent`, order by `order_index` within `section`, pull each block's data from its
`source_producers`, fold via `compile_instruction` (`compiler` role, or a deterministic template
fallback), then **enforce `leak_rules`** before the block enters the message. The glossary / block
reference render `label` + `purpose` + `source_producers` + `leak_rules` from the same rows. Seeded
with the ~15 blocks from the [ADR 0016](../../adr/0016-narrator-agent-and-turn-loop.md) context inventory.

## Data touched

New: `prompt_blocks` (global) + `block_agent` / `block_section` enums. See
[../../architecture/DATABASE.md](../../architecture/DATABASE.md) §3.14. Read by the assembler at prompt-build time; rendered by the
docs/glossary surface.

## Agent / isolation impact

The registry is the **declaration** of where each leak guard applies — it does not relax any guard.
`leak_rules` map 1:1 to existing guards: `awareness_fold` ([ADR 0007](../../adr/0007-npc-context-assembly.md)), `knowledge_boundary`
([ADR 0001](../../adr/0001-character-data-three-layer-separation.md)/[0013](../../adr/0013-authoring-and-compile-pipeline.md)), `hedged_attribution` ([ADR 0009](../../adr/0009-pov-projection.md)/[0010](../../adr/0010-recorder-mechanics.md)), `own_perspective_only`
([ADR 0007](../../adr/0007-npc-context-assembly.md)), `omniscient_authoring` ([ADR 0008](../../adr/0008-psychological-nudge.md)). Safety is unchanged.

## Acceptance criteria

- [ ] `prompt_blocks` is seeded with the ~15 NPC + narrator blocks, each with `leak_rules` matching
  the owning ADR.
- [ ] The assembler derives block selection, order, fold instruction, and enforced leak rules from
  the registry (no hard-coded block list).
- [ ] The human block reference renders from the same rows (no second source).
- [ ] Disabling `is_active` on a non-required block removes it from prompts without code changes.

## Open questions

- Does the assembler always LLM-fold via `compile_instruction`, or template-fold cheap blocks
  (tone, scene rules) and LLM-fold only the rich ones?
- Where does the rendered block reference live — `guides/` page generated from the registry, or
  inline in the glossary?

## Related Documentation

- ADR: [0020](../../adr/0020-prompt-block-registry.md) · [0007](../../adr/0007-npc-context-assembly.md) · [0016](../../adr/0016-narrator-agent-and-turn-loop.md) · [0017](../../adr/0017-llm-orchestration-openrouter.md)
- Architecture: [DATABASE.md](../../architecture/DATABASE.md) (§3.14 `prompt_blocks`) · [ARCHITECTURE.md §6.5](../../architecture/ARCHITECTURE.md)
- Guide: [glossary.md](../../guides/glossary.md)
- Open items: [GAPS O7](../../adr/GAPS.md)
