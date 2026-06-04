# 0020 — Prompt Block Registry

- **Status:** Proposed
- **Date:** 2026-06-04

## Context

The final context that reaches each agent is built from **named blocks** — `[IDENTITY]`, `[SELF]`,
`[SNAPSHOT]`, `[MASKS]`, `[DIRECTIVES]`, `[NUDGE]`, `[SCENE RULES]`, `[SCENE EXCERPT]` for the NPC
([ADR 0007](0007-npc-context-assembly.md)); `[POV CONTRACT]`, `[MESH-AWARENESS]`, `[BEAT]`, `[DIRECTOR STATE]`, `[LOREBOOK]`,
`[SCENE STATE]`, `[RESUME ANCHOR]` for the narrator ([ADR 0016](0016-narrator-agent-and-turn-loop.md) §2/§6). [ADR 0016](0016-narrator-agent-and-turn-loop.md)'s "final
context inventory" tables already enumerate **every slot → its producer**, and the assembler
([ADR 0007](0007-npc-context-assembly.md)) compiles each block while enforcing isolation + the leak guards.

Today that knowledge lives in **three disconnected places**: the ADR inventory tables (prose), the
[glossary](../guides/glossary.md) (human definitions), and — eventually — the assembler code. The developer asked for a
**rule-like definition of each block** ("something like Cursor rules to describe what `[MASKS]` is
for"), chosen as a **machine-readable registry the app consumes** (not docs-only, not `.cursor`
rule files). The risk this addresses: the leak guards and block semantics drift between the docs and
the code, and there is no single authority an author or the assembler can point to for "what is this
block, where does it come from, and what must it never leak."

## Decision

### 1. One registry is the single source of truth for every prompt block

A new authoring/config table, **`prompt_blocks`**, defines each block **once**. It is consumed by
the assembler (to know **what to compile, in what order, under which leak rules**) and rendered for
humans (the [glossary](../guides/glossary.md) / a block reference) from the **same rows** — so the definition can never
drift between code and docs. This is the engine-native analog of "rules" the developer asked for.

```
prompt_block:
  key:               IDENTITY | SELF | SNAPSHOT | MASKS | DIRECTIVES | NUDGE | SCENE_RULES |
                     SCENE_EXCERPT | POV_CONTRACT | MESH_AWARENESS | BEAT | DIRECTOR_STATE |
                     LOREBOOK | SCENE_STATE | RESUME_ANCHOR
  agent:             narrator | npc | both
  section:           system | user
  label:             "[MASKS]"                       # how it renders in the prompt
  purpose:           "<human description — what this block is for>"
  source_producers:  [ { adr, table } ]              # where the data comes from (ADR 0016 inventory)
  compile_instruction: "<the instruction the `compiler` role uses to fold this block>"
  leak_rules:        [ awareness_fold | knowledge_boundary | hedged_attribution |
                       own_perspective_only | omniscient_authoring | none ]
  order_index:       int                              # block order within its section
  is_active:         bool
```

`prompt_blocks` is **global** (no `story_id`); a per-story override can come later via
`stories.settings` if needed (it is config, like [ADR 0017](0017-llm-orchestration-openrouter.md)'s `model_profiles`). It is an
**authoring-realm** concern — it shapes prompts, it is not per-save state.

### 2. The registry DRIVES assembly, it does not just document it

When the assembler ([ADR 0007](0007-npc-context-assembly.md)) builds an NPC or narrator prompt it:

1. selects active blocks for that `agent`, ordered by `order_index` within `section`;
2. pulls each block's data from its `source_producers`;
3. folds it using `compile_instruction` (on the [ADR 0017](0017-llm-orchestration-openrouter.md) `compiler` role) — or a deterministic
   template fallback ([ADR 0007](0007-npc-context-assembly.md) alternatives);
4. **applies `leak_rules` as the enforcement contract** for that block before it is allowed into the
   message.

The `leak_rules` are the registry's teeth — each maps to an existing guard, so the registry does
**not** invent a new guard, it **names which guard applies where**:

| `leak_rule` | Means | Enforced per |
|-------------|-------|--------------|
| `awareness_fold` | merge value × awareness; a capped feeling is never stated plainly | [ADR 0007](0007-npc-context-assembly.md) |
| `knowledge_boundary` | clamp content to what the character knows this chapter | [ADR 0001](0001-character-data-three-layer-separation.md)/[0013](0013-authoring-and-compile-pipeline.md) |
| `hedged_attribution` | mental-state reads only as "looks/seems"; unhedged rejected | [ADR 0009](0009-pov-projection.md)/[0010](0010-recorder-mechanics.md) |
| `own_perspective_only` | only this character's own edges/state; never others' | [ADR 0007](0007-npc-context-assembly.md) |
| `omniscient_authoring` | author-side omniscient input; must be compiled before crossing | [ADR 0008](0008-psychological-nudge.md) |
| `none` | no special guard (e.g. scene tone) | — |

### 3. Seeded blocks (the ~15 the engine already uses)

The registry ships seeded from [ADR 0016](0016-narrator-agent-and-turn-loop.md)'s inventory. NPC blocks:

| key | label | section | leak_rules | source (ADR / table) |
|-----|-------|---------|-----------|----------------------|
| `IDENTITY` | `[IDENTITY]` | system | `knowledge_boundary` | card snapshot ([0013](0013-authoring-and-compile-pipeline.md) / `character_cards`) |
| `SELF` | `[SELF]` | system | `none` (own private truth) | internal state ([0014](0014-internal-state-schema.md) / `internal_states`) |
| `SNAPSHOT` | `[SNAPSHOT]` | system | `awareness_fold` + `own_perspective_only` | edges ([0002](0002-relationship-edge-schema.md) / `edge_axes`) |
| `MASKS` | `[MASKS]` | system | `own_perspective_only` | `topic_flags` ([0002](0002-relationship-edge-schema.md)) + masks ([0014](0014-internal-state-schema.md)) |
| `DIRECTIVES` | `[DIRECTIVES]` | system | `none` | registers ([0006](0006-register-relational-mode-system.md) / `registers`) |
| `NUDGE` | `[NUDGE]` | system | `omniscient_authoring` + `knowledge_boundary` | nudge ([0015](0015-beat-document-and-boundaries.md)/[0008](0008-psychological-nudge.md) / `nudges`) |
| `SCENE_RULES` | `[SCENE RULES]` | system | `none` | scene ([0009](0009-pov-projection.md) / `scenes`) |
| `LOREBOOK` (npc) | `[LOREBOOK]` | system | `knowledge_boundary` | keyword match ([0013](0013-authoring-and-compile-pipeline.md)) |
| `SCENE_EXCERPT` | `[SCENE EXCERPT]` | user | `hedged_attribution` + `knowledge_boundary` | recorder `surface` ([0010](0010-recorder-mechanics.md)), projected ([0009](0009-pov-projection.md)), decoded `reads_target` ([0006](0006-register-relational-mode-system.md)) |

Narrator blocks:

| key | label | section | leak_rules | source (ADR / table) |
|-----|-------|---------|-----------|----------------------|
| `POV_CONTRACT` | `[POV CONTRACT]` | system | `none` | scene ([0009](0009-pov-projection.md) / `scenes`) |
| `MESH_AWARENESS` | `[MESH-AWARENESS]` | system | `hedged_attribution` | rule ([0016](0016-narrator-agent-and-turn-loop.md)) + edges ([0002](0002-relationship-edge-schema.md)) |
| `BEAT` | `[BEAT]` | system | `omniscient_authoring` | beat doc ([0015](0015-beat-document-and-boundaries.md) / `beats`) |
| `DIRECTOR_STATE` | `[DIRECTOR STATE]` | system | `none` | engine clock ([0008](0008-psychological-nudge.md)/[0015](0015-beat-document-and-boundaries.md)) |
| `LOREBOOK` (narrator) | `[LOREBOOK]` | system | `none` | keyword match ([0013](0013-authoring-and-compile-pipeline.md)) |
| `SCENE_STATE` | `[SCENE STATE]` | system | `none` | context-memory ([0012](0012-persistence-schema.md)/[0015](0015-beat-document-and-boundaries.md)) |
| `RESUME_ANCHOR` | `[RESUME ANCHOR]` | user | `none` | `sessions.resume_anchor` ([0012](0012-persistence-schema.md)/[0016](0016-narrator-agent-and-turn-loop.md)) |

(`MESH_AWARENESS` carries the full mesh but its `hedged_attribution` rule + the [ADR 0016](0016-narrator-agent-and-turn-loop.md)
"never state what a present character would not know" directive bound its output — the narrator is
omniscient but its prose is hedged; the recorder is what structurally strips truth before any NPC
sees it.)

### 4. Why a table and not just docs or `.cursor` rules

The registry is **executable config**: the assembler reads `order_index`, `compile_instruction`,
and `leak_rules` at build time. Docs-only could not drive compilation; `.cursor` rule files guide a
coding agent, not the runtime. A single table that the **engine consumes** and the **docs render
from** is the only form that keeps "what `[MASKS]` is" identical in code and in prose.

## Alternatives considered

- **Docs-only block reference (extend the glossary).** Rejected per the developer's choice: it
  cannot drive the assembler, so code and docs drift.
- **`.cursor/rules/*.mdc` files.** Rejected as the mechanism: those guide an AI coding assistant,
  not the running engine; out of scope for the app's behavior.
- **Hard-code block order + leak rules in the assembler.** Rejected: buries the contract in code,
  un-auditable by an author, and re-creates the drift this ADR removes.
- **A new leak guard per block.** Rejected: the registry **names which of the three existing
  guards** applies; it adds zero new guards (safety stays exactly the [ADR 0007](0007-npc-context-assembly.md)/[0008](0008-psychological-nudge.md)/[0009](0009-pov-projection.md)-[0010](0010-recorder-mechanics.md) set).
- **Per-story block sets from the start.** Deferred: global now; per-story override via
  `stories.settings` later if a story needs a bespoke block.

## Consequences

- A new global **`prompt_blocks`** table lands in [DATABASE.md](../architecture/DATABASE.md), seeded with the ~15 blocks above; a
  `block_agent` enum (`narrator|npc|both`) is added.
- The assembler ([ADR 0007](0007-npc-context-assembly.md)) and narrator prompt builder ([ADR 0016](0016-narrator-agent-and-turn-loop.md)) become **registry-driven**:
  block selection, order, fold instruction, and leak-rule enforcement are read from rows, not
  hard-coded. (Implementation, not this design round.)
- The [glossary](../guides/glossary.md) / a block-reference doc render from the registry — one definition, two surfaces.
- The [ADR 0016](0016-narrator-agent-and-turn-loop.md) "final context inventory" is now **backed by data**; that table becomes the seed.
- Feature spec [O7](../features/session/O7-prompt-block-registry.md) carries the editing-UI + render detail.
