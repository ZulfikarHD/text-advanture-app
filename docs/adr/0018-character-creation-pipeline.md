# 0018 — Character creation pipeline (AI / manual / hybrid) + archetype library

- **Status:** Proposed
- **Date:** 2026-06-04

## Context

[ADR 0013](0013-authoring-and-compile-pipeline.md) specifies the **compile step** that turns an existing **source bible** into the
runtime artifacts (`character_cards`, `registers`, `sensitivities`, `disposition_priors`,
`lorebook_entries`) through the shared review gate. But it **assumes a bible already exists** —
`luna-archi.md` is the only one in the repo. It says nothing about how a *new* character is brought
into being, nor how an author who does **not** want to write a 50 KB bible by hand can still
produce a usable card.

The developer wants a **character-creation feature** with three intents:

- **AI-generated** — describe a character in a prompt and have the engine draft it end-to-end.
- **Manual** — fill the card fields directly, no AI.
- **Hybrid** — AI drafts, human edits.

…and a reusable **archetype library** (e.g. *koakuma*) to seed creation, so common character
shapes do not start from a blank slate. The existing `register_archetypes`
([DATABASE.md](../architecture/DATABASE.md) §3.5) is **register-grammar only** — it does not carry disposition priors,
sensitivities, voice, or `base_opacity`, so it cannot seed a whole character. A second library is
needed.

The end result, in the developer's words, must be **"ready to use or even already registered in the
database."**

## Decision

### 1. Creation is a front door onto the ADR 0013 pipeline — not a parallel one

Character creation does **not** invent a new compile path. It produces (or lets the human author) a
**source bible** + optional overrides, then runs the **same [ADR 0013](0013-authoring-and-compile-pipeline.md) compile + review +
commit**. The three modes differ only in *who writes the bible/artifacts* and *how much AI is
involved*; the **commit target and the review gate are identical**.

```
                        ┌──────────── AI ───────────┐
seed (name, role,       │  LLM drafts BIBLE markdown │
traits, archetype?) ───►│  (content/bibles/<slug>.md)│──┐
                        └────────────────────────────┘  │
                        ┌────────── MANUAL ──────────┐   ├─► ADR 0013 COMPILE ─► REVIEW GATE ─► COMMIT
author writes the   ───►│  fills card/register/sens. │──┤    (LLM-assisted or         (accept/      (authoring rows:
fields directly         │  forms directly (bible opt)│   │     skipped in manual)       edit/        characters,
                        └────────────────────────────┘   │                              reject)      character_cards,
                        ┌────────── HYBRID ──────────┐   │                                            registers,
AI drafts, then     ───►│  every artifact is editable│──┘                                            sensitivities)
human edits             │  at the review gate         │
                        └────────────────────────────┘
```

### 2. The three modes

| Mode | Bible | Artifacts | LLM | Review gate |
|------|-------|-----------|-----|-------------|
| **`ai`** | LLM-drafted from a seed, written to `content/bibles/<slug>.md` | LLM-compiled ([ADR 0013](0013-authoring-and-compile-pipeline.md)) | `compiler` + `nudge_compiler`-class roles ([ADR 0017](0017-llm-orchestration-openrouter.md)) | bible draft + each compiled artifact |
| **`manual`** | optional (may be null) | human fills `folded_identity`, `knowledge_boundary`, `disposition_priors`, `voice`, `tells`, `live_axes`, `base_opacity`, `model_tier`, registers, sensitivities via forms | none | trivial / self-accepted |
| **`hybrid`** | LLM-drafted, human-edited | LLM-drafted, human-edited at each artifact | yes | the [ADR 0013](0013-authoring-and-compile-pipeline.md) flow with AI pre-fill |

The mode is recorded as a `creation_mode` enum on the produced `review_items` payload (it is process
metadata, not runtime state). A new `bible_generate` `producer_type` covers the AI bible draft so it
passes through the **same** review surface as `card_compile`.

### 3. The character-archetype library — seeds a whole character, not just grammar

A new **global** `character_archetypes` library (no `story_id`) carries the seedable shape of a
common character type:

```
character_archetype: koakuma
  base_opacity:            high        # seeds composure / legibility (ADR 0010)
  suggested_live_axes:     [affection, trust, romantic, fear]
  default_disposition_priors: { … by target trait … }          # ADR 0002 seeds
  default_registers:       [ one_way_mirror→koakuma_default, bespoke→transparent_mess ]   # ADR 0006
  default_sensitivities:   [ fear_of_abandonment, pitied_as_fragile ]                     # ADR 0005
  voice_scaffold:          { speech subset, tells }              # ADR 0006 speech/tells
  description:             "genuine brightness used with conscious precision; one-way mirror"
```

Creation may **start from** an archetype: its fields pre-fill the seed (AI mode), the forms (manual
mode), or the draft (hybrid). It is a **starting point, never a constraint** — every field stays
editable through the review gate. This is deliberately **distinct** from `register_archetypes`
(which remains the [ADR 0006](0006-register-relational-mode-system.md) register-grammar library); a character archetype *references*
register archetypes among its `default_registers` but adds the priors / sensitivities / voice /
opacity a whole character needs.

### 4. "Ready to use / registered" = the committed authoring rows

On accept, creation commits exactly the [ADR 0013](0013-authoring-and-compile-pipeline.md) / [ADR 0012](0012-persistence-schema.md) authoring-realm rows:

- `characters` (slug, name, bible_path, base_opacity, live_axes, model_tier, is_player)
- `character_cards` — the **chapter-1** snapshot to start (later chapters recompile per [ADR 0013](0013-authoring-and-compile-pipeline.md) §4)
- `registers` + (promoted, if reused) `register_archetypes`
- `sensitivities`

No new runtime tables. Edges are **not** created here — they are seeded at **session fork** from
`disposition_priors` ([ADR 0002](0002-relationship-edge-schema.md) / [0012](0012-persistence-schema.md)), exactly as [ADR 0013](0013-authoring-and-compile-pipeline.md) §6 states.

### 5. Bible storage + the manual no-bible divergence

- **AI/hybrid bibles** are written to **`content/bibles/<slug>.md`** and referenced by
  `characters.bible_path` — resolving the [PLACEHOLDER_TRACKING](../guides/PLACEHOLDER_TRACKING.md) PH-6 "bibles live at repo root, location
  not standardized" by giving them a home. `luna-archi.md` may migrate there later.
- **Manual mode allows a bible-less card** (`bible_path = null`): the human *is* the source of
  truth and authors the card directly. This is a **deliberate divergence** from [ADR 0001](0001-character-data-three-layer-separation.md)'s "bible is
  the single source of truth" — acceptable because [ADR 0001](0001-character-data-three-layer-separation.md)'s concern is *runtime injection of
  spoilers*, and a manually authored card is already the spoiler-bounded slice. Logged in
  PLACEHOLDER_TRACKING. (`knowledge_boundary` is still mandatory on the card regardless of mode.)

### 6. Spoiler safety is inherited, not re-solved

AI and hybrid modes run the [ADR 0013](0013-authoring-and-compile-pipeline.md) §3 clamp (section tags + reveal ledger →
`knowledge_boundary`); a generated bible is annotated with reveal points as part of the draft, and
those feed `reveal_ledger`. The compiler is omniscient at authoring time; its **output** is the
bounded card ([ADR 0013](0013-authoring-and-compile-pipeline.md) §8). Creation adds **no fourth leak guard** — it relies on the same
authoring-time enforcement.

## Alternatives considered

- **A standalone creation pipeline separate from ADR 0013.** Rejected: duplicates the compile +
  review + clamp machinery and risks divergence; creation is a *front door*, not a second engine.
- **Extend `register_archetypes` to carry priors/sensitivities/voice.** Rejected: overloads a
  grammar-only library and breaks its [ADR 0006](0006-register-relational-mode-system.md) meaning; a separate `character_archetypes` keeps
  each library single-purpose.
- **Drafts in their own table (`character_drafts`).** Rejected: the shared review gate
  (`review_items.payload` while `pending`) already is the draft store; a `bible_generate`
  producer_type is the only addition.
- **AI-only or manual-only.** Rejected: the developer explicitly wants all three; manual is also the
  fallback when no LLM key is configured.
- **Forbid bible-less cards (force a bible always).** Rejected as too rigid for a manual author;
  allowed as a logged divergence with `knowledge_boundary` still mandatory.

## Consequences

- A new global **`character_archetypes`** library + a **`bible_generate`** `producer_type` + a
  **`creation_mode`** enum land in [DATABASE.md](../architecture/DATABASE.md). No new runtime tables.
- **`content/bibles/`** becomes the standard bible home (resolves [PH-6](../guides/PLACEHOLDER_TRACKING.md)); manual bible-less cards
  are a logged divergence.
- Creation **reuses** the [ADR 0013](0013-authoring-and-compile-pipeline.md) compile + the shared review gate end-to-end; the only new
  surface is the **seed/forms UI** and the **archetype picker** (an O4/UI concern → feature spec
  [O5](../features/npc-behaviour/O5-character-creation.md)).
- The LLM drafting/compiling runs through the [ADR 0017](0017-llm-orchestration-openrouter.md) `LlmClient` (`compiler` role); manual mode
  needs **no** LLM, so creation works with no API key.
- Feeds [ADR 0019](0019-outline-compilation.md): a story needs characters before its outline's beats can name a `nudge_target`.
