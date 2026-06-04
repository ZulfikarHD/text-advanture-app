# 0006 — Register / relational-mode system

- **Status:** Proposed
- **Date:** 2026-06-04

## Context

Axis values give intensity and direction but **cannot produce behavior** (see ADR 0002 / the
Luna Q2 finding: identical affection, opposite behavior with a classmate vs. with Vixia). The
missing layer is the **behavioral grammar** — *how* a character expresses, independent of how
much they feel. Luna's "Koakuma vs. With-Vixia Contrast" table is already a dimension-by-
dimension spec for two such grammars.

## Decision

### A register is a profile across a FIXED canonical dimension set

| Dimension | Values |
|---|---|
| `disclosure` | sealed · guarded · open · transparent |
| `proximity` | controlled · warm · unconscious-seeking · distancing |
| `flow` | clean-exit · extends-every-moment |
| `deflection` | invisible · transparent-failing · none |
| `sincerity` | direct · warm-non-answer · rerouted-through-teasing · impossible |
| `composure` | unbreakable · fragile |
| `reads_target` | accurate · crashes |
| `tells` | [authored leaks that surface] |
| `speech` | ref to the voice subset used |

The dimension vocabulary is **fixed and versioned**; new dimensions are added deliberately, so
registers stay comparable and authorable.

### Resolution pipeline (per turn)

```
1. base               edge's authored default register (may be a HARD PIN)
2. threshold selector  axis value -> register variant   (e.g. trust gradient L1..L4)
3. situational override event/context conditions         (romantic-interest -> boundary_protection)
4. emotional modulation current emotional state shifts the SURFACE, not the grammar
5. mask + awareness    suppress specific content / gate whether she can name it
```

- A **pinned base** bypasses the threshold selector (Luna->Vixia is pinned `transparent_mess`
  regardless of trust level — her reads crash with him no matter what).
- For non-pinned edges, **axis thresholds drive selection** (trust gradient opens the register
  as trust climbs).
- **Step 4 reads the per-character emotional state** (ADR 0001 internal state); it modulates
  surface intensity (happy -> more teasing; scared -> performance collapses; breaking -> single
  words) without changing the grammar.
- **Ruptures may flip the `base`** (ADR 0003) — a defining event permanently changes the grammar.

### Storage: shared archetypes + card instantiation

- **Shared archetypes** (library): reusable grammar skeletons (`one_way_mirror`,
  `romantic_deflection`, `unguarded`, `wary`).
- **Card instantiation**: a character binds a named register to an archetype + its own `speech`
  and `tells`. Bespoke registers (no archetype) are allowed (`transparent_mess` is pure Luna).

### Full behavior equation

```
behavior = resolve(register pipeline)
           -> modulated by emotional state
           -> expressing axis-values-as-language
           -> gated by mask + awareness
```

### Worked example (Luna)

```yaml
koakuma_default:   { archetype: one_way_mirror, disclosure: sealed, proximity: controlled,
                     flow: clean-exit, deflection: invisible, sincerity: warm-non-answer,
                     composure: unbreakable, reads_target: accurate, tells: [], speech: koakuma_voice }
transparent_mess:  { disclosure: transparent, proximity: unconscious-seeking,
                     flow: extends-every-moment, deflection: transparent-failing,
                     sincerity: rerouted-through-teasing, composure: fragile, reads_target: crashes,
                     tells: [pink-ears, glove-adjust, grip-tightens, stumbling], speech: vixia_voice }
boundary_protection: { archetype: romantic_deflection, disclosure: sealed, proximity: distancing,
                     flow: clean-exit, deflection: invisible, sincerity: warm-non-answer, speech: deflection_techniques }
```

## Alternatives considered

- **Derive register from axis values.** Rejected: Luna disproves it (Q2).
- **Free-form dimensions per register.** Rejected: loses comparability/authorability.
- **Authored base + situational overrides only (no axis-driven selection).** Rejected: the trust
  gradient (L1->L4) requires axis-threshold selection.
- **Card-local registers only.** Rejected: shared archetypes avoid re-authoring grammar.

## Consequences

- Need a **versioned canonical dimension vocabulary** and a register-archetype library.
- The per-character internal state must expose a **current emotional state** field for step 4.
- The context assembler (next ADR) uses the resolved register to phrase the numbers->language
  translation — the register is its instruction set.
