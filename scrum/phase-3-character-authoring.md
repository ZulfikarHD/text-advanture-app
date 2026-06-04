# Phase 3: Character Authoring & Compile — Directed Interactive Novel Engine
## Directed Interactive Novel Engine (DINE)

**Timeline:** ~2 Months (8 Sprints)
**Sprint Duration:** 1 Week
**Team Size Recommendation:** 1 Full-stack Dev (+ optional QA)
**Depends on:** Phase 1 (foundation, schema, LLM client, review-gate foundation, seeded libraries) + Phase 2 (a story must exist to own characters)
**Governing ADRs:** 0018 (creation pipeline), 0013 (authoring/compile pipeline), 0001 (three-layer character data), 0002 (relationship edges), 0005 (appraisal triggers), 0006 (register system), 0010 (recorder mechanics — `base_opacity`/legibility)

> Goal: let an author bring characters into being in three modes (AI / manual / hybrid), compile spoiler-safe per-chapter cards from a source bible through the shared review gate, and author the relationship edge schema (live axes, disposition priors, register bindings), registers, and sensitivities — all surfaced through a character management UI. After this phase a story has a committed, spoiler-bounded cast ready for outline compilation (Phase 4) and runtime (Phases 5–6).

---

## Product Backlog Overview

### Epic Summary

| Epic ID | Epic Name | Priority | Story Points | Sprints |
|---------|-----------|----------|--------------|---------|
| E1 | Character Creation Pipeline | Critical | 23 | 12–13 |
| E2 | Character Archetype Library | Medium | 8 | 12, 14 |
| E3 | Bible → Card Compile & Spoiler Safety | Critical | 21 | 14–16 |
| E4 | Relationship Edges & Disposition Priors (authoring) | Critical | 18 | 16–17 |
| E5 | Register Authoring | High | 11 | 17–18 |
| E6 | Sensitivity Authoring | High | 8 | 18 |
| E7 | Character Management UI | High | 11 | 13, 19 |

**Total Estimated:** ~100 Story Points

---

## EPIC E1: Character Creation Pipeline

### E1.1 — Three Creation Modes

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.1.1 | As an **author**, I want an AI creation mode (seed name/role/traits/optional archetype → the system drafts a source bible → compiles runtime artifacts) so that I can create a usable character without writing a 50KB bible by hand | 8 | Critical | 12 |
| S-1.1.2 | As an **author**, I want a manual creation mode (fill card fields directly, no LLM, bible optional/null) so that I can author a character with no API key | 5 | Critical | 12 |
| S-1.1.3 | As an **author**, I want a hybrid creation mode (AI drafts, I edit each artifact at the review gate) so that I get a fast start with full control | 5 | High | 13 |
| S-1.1.4 | As a **system**, I want the creation mode recorded as process metadata and the AI bible draft to pass through the same review gate (as a bible-generate producer) so that all paths share one review surface | 2 | Medium | 13 |

**Acceptance Criteria - S-1.1.1:**
```gherkin
Feature: AI Character Creation Mode

Scenario: A seed drafts a bible and compiles runtime artifacts
  Given I am authoring within an existing story
  And I have a configured LLM provider/API key
  When I provide a creation seed and choose creation_mode "ai":
    | Field               | Value                      |
    | name                | Luna                       |
    | role                | classmate / love interest  |
    | traits              | bright, guarded, precise   |
    | character_archetype | koakuma (optional)         |
  Then the system drafts a source bible and stores it at content/bibles/<slug>.md
  And the draft is enqueued to the review gate as a bible_generate proposal
  And on acceptance the system compiles the runtime artifacts (folded_identity, knowledge_boundary, disposition_priors, voice, tells) plus registers and sensitivities
  And each compiled artifact passes through the review gate before commit

Scenario: A usable character without a hand-written bible
  Given a seed of only a few fields
  When the AI mode completes through the review gate
  Then a chapter-1 character_card is committed for the new character
  And no author had to hand-write a 50KB source bible

Scenario: AI mode is unavailable without a provider
  Given I have no LLM provider/API key configured
  When I choose creation_mode "ai"
  Then I am told the AI mode is unavailable without a provider
  And I am offered the manual mode as an alternative
```

**Acceptance Criteria - S-1.1.2:**
```gherkin
Feature: Manual Character Creation Mode

Scenario: Author fills the card fields directly with no model call
  Given I am authoring within an existing story
  When I choose creation_mode "manual" and fill the card fields directly:
    | Field              | Value                                  |
    | folded_identity    | compiled spoiler-free identity prose   |
    | knowledge_boundary | { knows:[…], does_not_know:[…] }       |
    | disposition_priors | { … by target trait … }                |
    | voice              | speech-pattern subset                  |
    | tells              | [authored leaks]                       |
    | base_opacity       | 80                                     |
    | live_axes          | ["affection","trust","fear"]           |
    | model_tier         | major                                  |
  Then the character is created with no LLM call made
  And bible_path may be null (a bible-less card)

Scenario: knowledge_boundary is mandatory regardless of mode
  Given creation_mode "manual"
  When I attempt to commit a card with no knowledge_boundary
  Then the commit is rejected
  And I am told knowledge_boundary is required in every creation mode

Scenario: Manual creation works with no API key
  Given no LLM provider/API key is configured
  When I create a character in manual mode
  Then creation succeeds without any model call
```

**Acceptance Criteria - S-1.1.3:**
```gherkin
Feature: Hybrid Character Creation Mode

Scenario: AI drafts, author edits each artifact at the gate
  Given I am authoring within a story and have a provider configured
  When I choose creation_mode "hybrid" and provide a seed
  Then the system drafts the source bible and pre-fills each artifact
  And the bible, folded_identity, knowledge_boundary, disposition_priors, voice, tells, registers, and sensitivities are each presented editable at the review gate

Scenario: An edited artifact commits the edit, not the draft
  Given a hybrid draft pending at the review gate
  When I edit an artifact and commit it
  Then the edited content is committed instead of the original AI draft

Scenario: Per-artifact control
  Given a hybrid draft at the review gate
  Then I may accept, edit, or reject each artifact independently before commit
```

**Acceptance Criteria - S-1.1.4:**
```gherkin
Feature: Creation-Mode Metadata & Shared Review Surface

Scenario: creation_mode is recorded as process metadata
  Given a character created in any mode
  Then the creation_mode (ai | manual | hybrid) is recorded on the review_items payload
  And it is process metadata, not runtime state stored on the character

Scenario: The AI bible draft reuses the shared review surface
  Given an AI or hybrid creation
  When the bible draft is produced
  Then it is enqueued with producer_type bible_generate
  And it passes through the same propose → review → commit surface as card_compile
```

> **Technical Notes E1.1/E1.2:**
> - **Business Logic:**
>   - Creation is a **front door onto the ADR 0013 compile pipeline**, not a parallel one; it does not invent a new compile path. The three modes differ only in *who writes the bible/artifacts* and how much the model is involved — the **commit target and the review gate are identical**.
>   - `creation_mode` (ai | manual | hybrid) is recorded as **process metadata** on the review proposal payload, not as runtime state. The AI bible draft passes through the **same review gate** via a `bible_generate` producer (alongside `card_compile`).
>   - AI/hybrid bibles are stored at `content/bibles/<slug>.md` and referenced by `bible_path`. Manual mode allows a **bible-less card** (`bible_path = null`) — a deliberate divergence from "the bible is the single source of truth", acceptable because a hand-authored card is already the spoiler-bounded slice.
>   - `knowledge_boundary` is **mandatory** on the card in every mode.
>   - AI/hybrid require a configured provider; manual needs none (creation works with no API key). On accept, creation commits the authoring-realm rows (`characters`, chapter-1 `character_cards`, `registers`, `sensitivities`); edges are **not** created here.
>   - **Player** = appearance-only card + `base_opacity`, **no outgoing edges**; NPCs hold edges *toward* the player; the player is not simulated.
> - **Reference:** ADR 0018 / 0001 / 0013.

---

### E1.2 — Player Card

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.2.1 | As an **author**, I want to author the player's appearance-only card plus base_opacity (and no outgoing edges) so that other characters can read the player's delivery but the player isn't simulated | 3 | High | 13 |

**Acceptance Criteria - S-1.2.1:**
```gherkin
Feature: Player Character Card

Scenario: Author the player's appearance-only card
  Given I am authoring within a story
  When I mark a character is_player = true
  And I author its appearance plus base_opacity
  Then the card carries appearance and base_opacity only
  And no outgoing edges are created for the player

Scenario: NPCs still read and hold edges toward the player
  Given a player with no outgoing edges
  Then other characters can read the player's delivery via base_opacity
  And NPCs hold relationship edges directed toward the player

Scenario: The player is not simulated
  Given is_player = true
  Then the player carries no simulated interiority (no disposition-driven edges of its own)
  And the human supplies the player's behavior at runtime
```

---

## EPIC E2: Character Archetype Library

### E2.1 — Archetype Use & Authoring

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.1.1 | As an **author**, I want to browse and select a character archetype to pre-fill creation (base_opacity, suggested live axes, default disposition priors, default registers, default sensitivities, voice scaffold) so that I don't start from a blank slate | 3 | Medium | 12 |
| S-2.1.2 | As an **author**, I want to create/edit my own reusable character archetypes so that common character shapes can seed future creations | 5 | Low | 14 |

**Acceptance Criteria - S-2.1.1:**
```gherkin
Feature: Use a Character Archetype to Seed Creation

Scenario: Selecting an archetype pre-fills creation
  Given I am creating a character
  When I browse and select a character archetype (e.g. koakuma)
  Then creation pre-fills from the archetype:
    | Creation field     | Archetype source            |
    | base_opacity       | base_opacity                |
    | live_axes          | suggested_live_axes         |
    | disposition_priors | default_disposition_priors  |
    | registers          | default_registers           |
    | sensitivities      | default_sensitivities       |
    | voice / tells      | voice_scaffold              |

Scenario: An archetype is a starting point, never a constraint
  Given creation seeded from an archetype
  When I reach the review gate
  Then every pre-filled field remains editable
  And I may change any seeded field before commit
```

**Acceptance Criteria - S-2.1.2:**
```gherkin
Feature: Author Reusable Character Archetypes

Scenario: Create a reusable character archetype
  Given I am managing the character-archetype library
  When I create an archetype with base_opacity, suggested_live_axes, default_disposition_priors, default_registers, default_sensitivities, and voice_scaffold
  Then it is saved as a global, story-independent archetype
  And it can seed future character creations

Scenario: A character archetype references register archetypes
  Given an archetype's default_registers
  Then it may reference register archetypes by slug among its defaults
  And it adds the priors / sensitivities / voice / opacity a whole character needs

Scenario: Editing an archetype does not mutate existing characters
  Given an existing character archetype already used to create characters
  When I edit its fields
  Then future creations seed from the updated values
  And already-created characters are unaffected
```

> **Technical Notes E2.1:**
> - **Business Logic:**
>   - A **character archetype** seeds a WHOLE character (base_opacity, suggested live axes, default disposition priors, default registers, default sensitivities, voice scaffold) — deliberately **distinct** from the register-grammar-only **register archetypes**, which it *references* among its `default_registers`.
>   - The library is **global / story-independent** (no story scope).
>   - An archetype is a **starting point, never a constraint** — every seeded field stays editable through the review gate, and editing an archetype never mutates characters already created from it.
> - **Reference:** ADR 0018 §3.

---

## EPIC E3: Bible → Card Compile & Spoiler Safety

### E3.1 — Compile Pipeline

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.1.1 | As an **author**, I want the compile step to produce folded_identity, knowledge_boundary, disposition_priors, voice, and tells from the source bible through the review gate so that the runtime card is a spoiler-safe current-state slice | 8 | Critical | 14 |

**Acceptance Criteria - S-3.1.1:**
```gherkin
Feature: Bible → Card Compile

Scenario: Compile produces the card artifacts through the review gate
  Given a source bible plus the chapter outline / reveal ledger
  When I run the compile step for a character at a chapter
  Then it produces folded_identity, knowledge_boundary, disposition_priors, voice, and tells
  And each is proposed through the review gate with producer_type card_compile
  And the committed card is a spoiler-safe current-state slice

Scenario: Behavioral contrast compiles to registers; wounds to sensitivities
  Given a bible with a behavioral-contrast table and authored wounds/triggers
  When the compile runs
  Then reused grammars compile into registers (promotable to register archetypes) and bespoke ones are allowed
  And wounds/triggers compile into sensitivities
  And the character's disposition compiles into disposition_priors

Scenario: The human remains the fidelity floor
  Given a compiled artifact proposal
  When I accept, edit, or reject it
  Then only accepted or edited content is committed
  And the committed card is immutable at runtime (re-authoring means a new compile + review)
```

### E3.2 — Spoiler Clamp

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.2.1 | As a **system**, I want the per-chapter clamp (include a fact iff its reveal point ≤ chapter N, else an explicit does_not_know entry) so that an early-chapter card never contains a future-arc reveal | 5 | Critical | 15 |
| S-3.2.2 | As an **author**, I want the clamp driven by saga/chapter section tags plus the reveal ledger so that spoiler-safety is explicit and not left to inference | 3 | High | 15 |

**Acceptance Criteria - S-3.2.1:**
```gherkin
Feature: Per-Chapter Spoiler Clamp

Scenario: A fact at or before the chapter is included as known
  Given a card compiling for chapter N
  And a fact whose reveal_point ≤ N
  Then the fact is included in knowledge_boundary.knows

Scenario: A future-arc reveal becomes does_not_know
  Given a fact whose reveal_point > N
  Then it is not included as known
  And it appears as an explicit knowledge_boundary.does_not_know entry (or is omitted)
  And the early-chapter card never contains the future-arc reveal

Scenario: who_knows lets a holder know a secret before its reveal
  Given a reveal_ledger fact whose who_knows lists a character (e.g. ["vixia-archi"])
  When that character's card compiles for a chapter before reveal_chapter
  Then the fact may be included as known for that character only
```

**Acceptance Criteria - S-3.2.2:**
```gherkin
Feature: Explicit Spoiler-Safety Inputs

Scenario: Section tags clamp the bulk of the bible
  Given a bible annotated with saga/chapter section tags
  When the card compiles for chapter N
  Then sections tagged after chapter N are excluded from the card by their tag

Scenario: The reveal ledger backstops load-bearing secrets
  Given a reveal_ledger of { fact, reveal_chapter, who_knows } for load-bearing secrets
  When the card compiles
  Then those facts are clamped explicitly by the ledger
  And spoiler-safety never rests on the model inferring a reveal point
```

### E3.3 — Lifecycle & Recompile

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.3.1 | As a **system**, I want a card keyed per (character, chapter) with full recompile on bible/outline/ledger change and stale detection via a source hash so that epistemic state advances per chapter deterministically | 5 | High | 16 |

**Acceptance Criteria - S-3.3.1:**
```gherkin
Feature: Card Lifecycle & Recompile

Scenario: One card snapshot per (character, chapter)
  Given a character and a chapter
  Then exactly one card snapshot exists for that (character, chapter)
  And epistemic state advances per chapter

Scenario: Full recompile on a source change
  Given the bible, chapter outline, or reveal ledger changes (or a new chapter is added)
  When I recompile
  Then the card is fully recompiled at that chapter's reveal state (not a forward-diff of the previous card)
  And the recompile re-runs through the review gate

Scenario: Stale detection via the compiled source hash
  Given a committed card carrying a compiled_source_hash
  When the bible/ledger no longer matches that hash
  Then the card is flagged stale and a recompile is offered
```

> **Technical Notes E3.1/E3.2/E3.3:**
> - **Business Logic:**
>   - Compile is **LLM-assisted then human-reviewed**; output is **immutable at runtime** (re-authoring = a new compile + review, not an in-place runtime edit).
>   - `knowledge_boundary` records **both** what the character knows and what it does *not* know, so the assembler and recorder can structurally block hidden facts.
>   - **Clamp rule** at chapter N: include a fact **iff** `reveal_point ≤ N`; otherwise it becomes an explicit `does_not_know` entry (or is omitted). `who_knows` lists characters who hold a secret *before* its reveal.
>   - Two clamp inputs: **saga/chapter section tags** (coarse, covers the bulk) + the **reveal ledger** (explicit, for load-bearing secrets) — spoiler-safety is explicit, never left to inference.
>   - A card is keyed **per (character, chapter)**; **full recompile** per chapter (not forward-diff); recompile triggers = bible / outline / reveal-ledger change or a new chapter; **stale detection** via `compiled_source_hash`.
>   - The compiler is **omniscient at authoring time** but its **output is spoiler-bounded** — this is the **authoring-time enforcement the runtime guards depend on**, NOT a fourth leak guard.
> - **Reference:** ADR 0013 / 0001.

---

## EPIC E4: Relationship Edges & Disposition Priors (authoring)

### E4.1 — Live Axes & Per-Axis Config

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.1.1 | As an **author**, I want to declare a character's live axes and per-axis config (value, awareness mode auto/capped, soft/hard bounds, gain/loss rates, baseline, latch threshold) so that the edge schema can be instantiated correctly | 8 | Critical | 16 |

**Acceptance Criteria - S-4.1.1:**
```gherkin
Feature: Live Axes & Per-Axis Configuration

Scenario: Declare which axes are live
  Given a character
  When I declare its live_axes (e.g. ["affection","trust","fear","romantic"])
  Then only the declared live axes can be instantiated on its edges

Scenario: Configure a live axis
  Given a declared live axis
  When I set its per-axis config:
    | Field                 | Value     |
    | value                 | 96        |
    | awareness_mode        | auto      |
    | soft_floor            | 88        |
    | soft_cap              | 100       |
    | hard_floor            | 40        |
    | hard_cap              | 100       |
    | gain_rate             | 0.4       |
    | loss_rate             | 0.2       |
    | baseline              | 0         |
    | latch_threshold       | 80        |
  Then the edge schema can be instantiated with that config

Scenario: Awareness auto vs capped
  Given awareness_mode "auto"
  Then the tier is computed from |value| at read time (0–39 none, 40–59 vague, 60–79 subconscious, 80+ conscious)
  Given awareness_mode "capped"
  Then the character feels the axis but cannot consciously access it (a blind spot)

Scenario: Bounds are authorial and invisible to the character
  Given configured soft and hard bounds
  Then the bounds are invisible to the character (authorial)
  And the effective floor = max(soft_floor, scar.floor)
```

### E4.2 — Disposition Priors

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.2.1 | As an **author**, I want to author disposition priors keyed by target traits (gender, demeanor, faction, shows-interest) so that new edges are seeded from disposition, not born neutral | 5 | High | 17 |

**Acceptance Criteria - S-4.2.1:**
```gherkin
Feature: Disposition Priors

Scenario: Priors keyed by target traits
  Given a character card
  When I author disposition_priors keyed by target traits:
    | Trait          | Example seed                                   |
    | gender         | initial affection / romantic offset            |
    | demeanor       | initial trust offset + register hint           |
    | faction        | initial rivalry / respect offset               |
    | shows_interest | romantic/affection seed + register override    |
  Then a new edge is seeded from disposition (initial axis values, bounds, register), not born neutral

Scenario: Priors seed edges at session fork, not at authoring time
  Given authored disposition_priors
  Then no relationship edges are created during authoring
  And edges are seeded from these priors at session fork (Phase 6 runtime)
```

### E4.3 — Edge Register Binding & Topic Flags

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.3.1 | As an **author**, I want to author an edge's base register, situational overrides, and topic flags (edge-scoped masks) so that the same feeling expresses differently per relationship | 5 | High | 17 |

**Acceptance Criteria - S-4.3.1:**
```gherkin
Feature: Edge Register Binding & Topic Flags

Scenario: Author the base register and situational overrides
  Given a directed edge
  When I author its register binding:
    | Field              | Value                                                              |
    | register_base      | transparent_mess                                                   |
    | register_overrides | [{ when: target_shows_romantic_interest, use: boundary_protection }] |
  Then the same feeling expresses differently per relationship

Scenario: Author topic flags as edge-scoped masks
  Given a directed edge
  When I author topic_flags:
    | topic         | effect                |
    | the_diagnosis | knows_but_wont_admit  |
  Then the topic flag is an edge-scoped mask, distinct from the card's global mask

Scenario: An edge is directed and owner-perspective
  Given an edge from character A to character B
  Then it is owner-perspective (A→B ≠ B→A)
  And it represents A's self-perceived view of B (can be self-deceived)
```

> **Technical Notes E4.1/E4.2/E4.3:**
> - **Business Logic:**
>   - Edges are **directed, owner-perspective** (`A→B ≠ B→A`); only declared **live axes** are instantiated.
>   - **Awareness** is computed from value at read time (`auto`: 0–39 none, 40–59 vague, 60–79 subconscious, 80+ conscious) or `capped` (a blind spot — feels strongly, can't consciously access).
>   - **Bounds are invisible to the character** (authorial). Drift clamps to **soft** bounds; only ruptures reach the **soft↔hard** band. Effective floor = `max(soft_floor, scar.floor)`.
>   - **Disposition priors** seed new edges at **session fork** (not here), keyed by target traits (gender, demeanor, faction, shows-interest). New edges are not born neutral.
>   - Behavior equation: **rendered behaviour = axis value × expression mask × (card voice + relational register).** Register is **authored, not derived** from numbers; an edge picks a `base` + conditional `overrides`; **topic flags** are narrow edge-scoped masks.
> - **Reference:** ADR 0002.

---

## EPIC E5: Register Authoring

### E5.1 — Register Instantiation

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.1.1 | As an **author**, I want to instantiate a register from an archetype or bespoke over the fixed canonical dimension set, bind a voice subset + tells, and optionally hard-pin the base, so that the behavioral grammar is authorable | 8 | High | 17 |

**Acceptance Criteria - S-5.1.1:**
```gherkin
Feature: Register Instantiation

Scenario: Instantiate over the fixed canonical dimension set
  Given the fixed dimension set (disclosure, proximity, flow, deflection, sincerity, composure, reads_target, tells, speech)
  When I instantiate a register from a register archetype or as bespoke
  And I set its dimension profile and bind a voice subset (speech_ref) plus tells:
    | Dimension    | Value                  |
    | disclosure   | transparent            |
    | proximity    | unconscious-seeking    |
    | flow         | extends-every-moment   |
    | deflection   | transparent-failing    |
    | sincerity    | rerouted-through-teasing |
    | composure    | fragile                |
    | reads_target | crashes                |
    | speech_ref   | vixia_voice            |
    | tells        | [pink-ears, glove-adjust] |
  Then the register is authored as behavioral grammar

Scenario: Hard-pin the base
  Given a register
  When I mark it is_pinned (hard-pin the base)
  Then it bypasses the threshold selector and is used regardless of axis value (e.g. Luna→Vixia transparent_mess)

Scenario: Bespoke registers are allowed
  Given a register with no register-archetype reference
  Then it is allowed as a bespoke register (e.g. transparent_mess)

Scenario: Register is authored, not derived from numbers
  Given two edges with identical axis values
  Then they may carry different registers
  And behavior is authored data, not derived from the numbers
```

### E5.2 — Register Archetype Promotion

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.2.1 | As an **author**, I want to promote a reused bespoke grammar into the shared register-archetype library so that grammar isn't re-authored across characters | 3 | Low | 18 |

**Acceptance Criteria - S-5.2.1:**
```gherkin
Feature: Promote a Register to the Shared Library

Scenario: Promote a reused bespoke grammar
  Given a bespoke register whose grammar is reused across characters
  When I promote it to the register-archetype library
  Then it becomes a shared, global register archetype
  And other characters can bind to it without re-authoring the grammar

Scenario: Only the grammar is promoted
  Given a promotion
  Then only the dimension profile (the grammar) is promoted
  And character-specific speech and tells are not carried into the shared archetype
```

> **Technical Notes E5.1/E5.2:**
> - **Business Logic:**
>   - A **register** is a profile across a **FIXED, versioned** canonical dimension set (disclosure, proximity, flow, deflection, sincerity, composure, reads_target, tells, speech); new dimensions are added deliberately so registers stay comparable and authorable.
>   - A **pinned base** bypasses the threshold selector; non-pinned edges select a register variant by axis threshold (trust gradient).
>   - **Bespoke** registers (no archetype) are allowed; reused grammars are **promotable** to the shared register-archetype library (grammar only).
>   - A register is **authored data, NOT derived** from numbers (identical axis values may render opposite behavior).
> - **Reference:** ADR 0006.

---

## EPIC E6: Sensitivity Authoring

### E6.1 — Sensitivity CRUD

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-6.1.1 | As an **author**, I want to author character sensitivities (detect natural-language matcher, target actor/beneficiary/witnessed_third_party, axes+direction, weight, channel) so that appraisal reacts in-character | 5 | High | 18 |
| S-6.1.2 | As an **author**, I want to see how a sensitivity layers on the universal priors (amplify/dampen/special-case) so that I understand the effective reaction set | 3 | Medium | 18 |

**Acceptance Criteria - S-6.1.1:**
```gherkin
Feature: Author Character Sensitivities

Scenario: Author a sensitivity
  Given a character card
  When I author a sensitivity:
    | Field   | Value                                                     |
    | detect  | "anyone harms, threatens, demeans, or endangers Vixia"    |
    | target  | actor                                                     |
    | axes    | { affection: down, trust: down }                          |
    | weight  | high                                                      |
    | channel | scales_with_severity                                      |
  Then appraisal can react in-character to matching events

Scenario: detect is a natural-language matcher
  Given a sensitivity's detect string
  Then it is a natural-language matcher (matched by the model at runtime, not a code rule)

Scenario: Some categories rupture regardless of severity
  Given a sensitivity for betrayal, confession, or abandonment
  When I set channel "rupture_only"
  Then it ruptures categorically regardless of judged severity
```

**Acceptance Criteria - S-6.1.2:**
```gherkin
Feature: Sensitivity Layering Over Universal Priors

Scenario: See how a sensitivity layers on the universal priors
  Given a character's sensitivities and the shared universal priors
  When I view the effective reaction set
  Then I see how each sensitivity amplifies, dampens, or special-cases the universal priors

Scenario: Match-only salience
  Given an event that matches no sensitivity (universal or card)
  Then no delta is proposed (the character is numb to what it doesn't care about)

Scenario: Multiple matches produce multiple proposals
  Given an event that matches several sensitivities (e.g. genuine_acknowledgment and pitied_as_fragile)
  Then multiple proposals are emitted and not resolved
  And the engine deliberately manufactures the contradiction
```

> **Technical Notes E6.1:**
> - **Business Logic:**
>   - Two layers: **universal priors** (shared baseline humanity) + **card sensitivities** (this character's amplifiers, dampeners, and special cases). The card layer overrides/amplifies the universal one.
>   - **Match-only salience:** no matched sensitivity → no delta. **Multiple matches → multiple proposals** (manufactures meaningful contradictions; do not resolve them).
>   - `channel ∈ { drift_only, rupture_only, scales_with_severity }`; **betrayal / confession / abandonment categorically rupture** (a *kind* of event, not a magnitude).
>   - `target ∈ { actor, beneficiary, witnessed_third_party }` supports vicarious shifts (watching A protect B can move the observer's edge toward A).
> - **Reference:** ADR 0005.

---

## EPIC E7: Character Management UI

### E7.1 — Manage & Inspect Characters

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-7.1.1 | As an **author**, I want to list, open, edit, and delete characters within a story and set model tier (major/minor) and the is_player flag so that I can manage a story's cast | 5 | High | 13 |
| S-7.1.2 | As an **author**, I want to view a character's per-chapter card snapshots side by side so that I can verify epistemic progression (what they know per chapter) | 3 | Medium | 19 |
| S-7.1.3 | As an **author**, I want to see compile status/errors and trigger a recompile so that I can keep cards in sync with the bible/ledger | 3 | Medium | 19 |

**Acceptance Criteria - S-7.1.1:**
```gherkin
Feature: Manage a Story's Cast

Scenario: List, open, edit, and delete characters within a story
  Given I am authoring a story
  Then I can list the story's characters, open one, edit its fields, and delete it
  And an empty cast guides me toward creating the first character

Scenario: Set the model tier
  Given a character
  When I set its model_tier:
    | Value | Meaning                  |
    | major | full card / strong model |
    | minor | compressed / cheap model |
  Then the tier is saved for that character

Scenario: Set the is_player flag
  Given a character
  When I set is_player = true
  Then the player constraints apply (appearance-only card, no outgoing edges)

Scenario: Deletion is confirmed
  Given I trigger deletion of a character
  Then I am asked to confirm before it proceeds
  And I have a clear way to cancel with no change made
```

**Acceptance Criteria - S-7.1.2:**
```gherkin
Feature: Inspect Per-Chapter Card Snapshots

Scenario: Compare card snapshots across chapters
  Given a character with multiple per-chapter card snapshots
  When I view the snapshots side by side
  Then I can compare folded_identity and knowledge_boundary (knows / does_not_know) across chapters
  And I can verify epistemic progression — what the character knows per chapter
```

**Acceptance Criteria - S-7.1.3:**
```gherkin
Feature: Compile Status & Recompile

Scenario: See compile status and errors
  Given a character's cards
  Then I can see each card's compile status and any compile errors
  And a card whose compiled_source_hash is stale is clearly indicated

Scenario: Trigger a recompile
  Given a stale or out-of-date card
  When I trigger a recompile
  Then the compile re-runs through the review gate
  And accepted output keeps the card in sync with the bible/ledger
```

> **Technical Notes E7.1:**
> - **Business Logic:**
>   - `model_tier` **major** = full card / strong model; **minor** = compressed / cheap model — cost tiering by character importance.
>   - Cards are **immutable at runtime**; per-chapter snapshots exist to show **epistemic progression** (what is known per chapter).
>   - **Recompile re-runs the review gate**; stale cards are detected via `compiled_source_hash`. Deletion is a destructive action (confirm first).
> - **Reference:** ADR 0007 / 0013.

---

## Sprint Roadmap

### Sprint 12: Creation Modes & Archetype Use (E1.1 + E2.1)
```
Sprint 12 (Week 12):
├── S-1.1.1: AI creation mode (seed → bible → compile)
├── S-1.1.2: Manual creation mode (no LLM, bible optional)
├── S-2.1.1: Use a character archetype to seed creation
└── Integration testing: creation as a front door onto the compile pipeline
```

### Sprint 13: Hybrid, Metadata, Player & Cast Management (E1.1 + E1.2 + E7.1)
```
Sprint 13 (Week 13):
├── S-1.1.3: Hybrid creation mode (AI drafts, author edits)
├── S-1.1.4: creation_mode metadata + bible_generate review surface
├── S-1.2.1: Player appearance-only card (base_opacity, no outgoing edges)
├── S-7.1.1: List/open/edit/delete + model tier + is_player
└── Test: knowledge_boundary mandatory in every mode
```

### Sprint 14: Compile Pipeline & Archetype Authoring (E3.1 + E2.1)
```
Sprint 14 (Week 14):
├── S-3.1.1: Bible → card compile through the review gate
├── S-2.1.2: Create/edit reusable character archetypes
└── Integration testing: compile producers + shared review gate
```

### Sprint 15: Spoiler Clamp (E3.2)
```
Sprint 15 (Week 15):
├── S-3.2.1: Per-chapter clamp (reveal_point ≤ N else does_not_know)
├── S-3.2.2: Section tags + reveal ledger drive the clamp
└── Test: an early-chapter card never contains a future-arc reveal
```

### Sprint 16: Card Lifecycle & Live Axes (E3.3 + E4.1)
```
Sprint 16 (Week 16):
├── S-3.3.1: Per-(character,chapter) card, full recompile, stale detection
├── S-4.1.1: Live axes + per-axis config (bounds/rates/awareness/latch)
└── Schema review: edge_axes correctness + effective-floor rule
```

### Sprint 17: Priors, Edge Binding & Register Instantiation (E4.2 + E4.3 + E5.1)
```
Sprint 17 (Week 17):
├── S-4.2.1: Disposition priors keyed by target traits
├── S-4.3.1: Edge register binding + situational overrides + topic flags
├── S-5.1.1: Register instantiation over the fixed dimension set
└── Test: priors seed edges at session fork (not at authoring time)
```

### Sprint 18: Register Promotion & Sensitivities (E5.2 + E6.1)
```
Sprint 18 (Week 18):
├── S-5.2.1: Promote a bespoke grammar into the shared archetype library
├── S-6.1.1: Author character sensitivities (detect/target/axes/weight/channel)
├── S-6.1.2: Sensitivity layering over universal priors
└── Test: match-only salience; categorical (betrayal/confession/abandonment) ruptures
```

### Sprint 19: Inspection, Recompile UI & Hardening (E7.1)
```
Sprint 19 (Week 19):
├── S-7.1.2: Per-chapter card snapshots side by side
├── S-7.1.3: Compile status/errors + trigger recompile
└── Phase 3 regression + spoiler-clamp hardening
```

---

## Definition of Done (DoD)

See the [program DoD](./README.md#7-global-definition-of-done-dod). Phase-3 emphasis:

- [ ] Creation works in all three modes; **manual mode runs with no API key**; `knowledge_boundary` mandatory in every mode (explicit negative test).
- [ ] Every compile producer (`card_compile`, `bible_generate`) enqueues to the shared review gate; accept/edit/reject committed correctly; committed cards immutable at runtime.
- [ ] **Spoiler-clamp tests**: a chapter-N card excludes any fact with `reveal_point > N` (explicit `does_not_know`), honors `who_knows`, and is driven by section tags + reveal ledger — not inference.
- [ ] **Compile review**: LLM-assisted output never committed unreviewed; recompile re-runs the gate; stale detection via `compiled_source_hash` verified.
- [ ] **Edge schema correctness**: only declared live axes instantiable; soft/hard bounds, gain/loss rates, awareness auto/capped, latch threshold, and effective floor = `max(soft_floor, scar.floor)` covered by tests.
- [ ] Disposition priors seed edges **at session fork**, not at authoring time (verified by absence of authoring-time edges).
- [ ] Registers authored over the fixed canonical dimension set; pinned base bypasses the threshold selector; promotion carries grammar only.
- [ ] Sensitivities: match-only salience, multiple-match multiple proposals, categorical ruptures verified.
- [ ] Character management UX states covered (loading, empty, error, success, unauthorized); destructive delete confirmed; responsive + keyboard-accessible.

---

## Success Metrics — Phase 3

| Metric | Target | Measurement |
|--------|--------|-------------|
| Spoiler leakage from compiled cards | 0 occurrences | Negative tests: no fact with `reveal_point > N` on a chapter-N card |
| Manual mode without provider | 100% | Manual creation completes with no LLM call / no API key |
| Creation completion rate | > 95% | Characters created through the review gate / creation attempts |
| Compile review coverage | 100% | No card/bible artifact committed without passing the review gate |
| Recompile staleness accuracy | 100% | Stale cards correctly flagged when `compiled_source_hash` mismatches |
| Edge-schema validity | 0 invalid edges | Only declared live axes instantiated; bounds/rates/awareness within constraints |
| Authoring time to a usable character (AI mode) | < 10 min | Median seed-to-committed-card time |

---

## Risk Register — Phase 3

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Spoiler leak from an early-chapter card (future-arc reveal) | Critical | Medium | Section tags + reveal ledger → `knowledge_boundary` clamp at compile; explicit `does_not_know`; review gate; negative tests |
| LLM-drafted bible/card committed unreviewed | Critical | Low | All AI/hybrid output routes through the shared review gate; human is the fidelity floor; no auto-commit path |
| Manual bible-less card diverges from the source-of-truth model | Medium | Medium | Deliberate, logged divergence; `knowledge_boundary` still mandatory; manual card *is* the spoiler-bounded slice |
| Edge schema mis-instantiated (wrong bounds/awareness/effective floor) | High | Medium | Constrain to declared live axes; test soft/hard bounds, awareness auto/capped, `max(soft_floor, scar.floor)` |
| Disposition priors mistakenly create edges at authoring time | Medium | Medium | Priors produced here, applied at session fork; test that authoring creates no edges |
| Register treated as derived from axis values | Medium | Low | Register is authored data; fixed versioned dimension set; pinned-base bypass test |
| Stale cards not recompiled after a bible/ledger change | High | Medium | `compiled_source_hash` stale detection + visible compile status + one-click recompile through the gate |
| Authoring burden too high for a dense character model | Medium | High | AI/hybrid modes, character-archetype seeding, sensible seeded defaults from Phase 1 libraries |

---

*Document Version: 1.0 · Author: Zulfikar Hidayatullah · Created: June 2026*
