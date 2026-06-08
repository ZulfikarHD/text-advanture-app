# Phase 5: Psychology Depth — beyond SillyTavern
## Directed Interactive Novel Engine (DINE)

**Timeline:** ~3 Months (11–12 Sprints)
**Sprint Duration:** 1 Week
**Depends on:** Phase 4 (directed structure, nudge, boundary events + reserved drift/decay slots), Phase 2 (assembler/recorder/projection + reserved appraisal slot), Phase 1 (loop spine + the play front door E0; the fork that seeds edges is the invisible one triggered by chapter selection, E0.2).
**Governing ADRs:** 0001 (three-layer split), 0013 (bible→card compile + spoiler clamp), 0018 (creation pipeline + archetypes), 0002 (edges), 0006 (registers), 0014 (internal state `[SELF]`), 0007 (awareness fold / SNAPSHOT / MASKS / DIRECTIVES), 0005 (appraisal), 0003 (delta engine), 0004 (decay + scars), 0016 (in-loop sequencing of appraisal/drift/decay).

> **Goal — characters that *evolve*, explainably and spoiler-safely.** Phases 2–4 give characters who play themselves and a human who directs them; this phase gives them an **inner life that changes**: a relationship **mesh** (asymmetric edges with axes, bounds, registers), an **internal state** (mood, emotions, motivation, masks), the **delta engine** (appraisal proposes → review gate → commit, with drift vs. rupture), **decay + latched scars** (commitment and trauma), and the **compile pipeline** that turns a spoiler-laden bible into a per-chapter, knowledge-bounded card. This lights the rich NPC blocks `SELF`/`SNAPSHOT`/`MASKS`/`DIRECTIVES`, turns the nudge into a **register/mask/awareness-gated** delivery, and finally lights the narrator `MESH_AWARENESS` block now that the mesh exists. After this phase the engine is **more than SillyTavern**: behavior = axis value × expression mask × (card voice + relational register), evolving under explainable, reviewable deltas.

> **Blocks lit this phase:** NPC `SELF`, `SNAPSHOT`, `MASKS`, `DIRECTIVES`; narrator `MESH_AWARENESS`. **Guards activated:** `awareness_fold` (capped feelings never stated plainly), and `own_perspective_only` graduates from *structurally trivial* (Phase 2: no edges) to *actively enforced* (now edges exist, an NPC must see only its **own** outgoing edges).
> **The reserved slots from earlier phases get filled here:** the Phase-2 appraisal slot (recorder-first sequencing) and the Phase-4 `SCENE_DONE`/`CHAPTER_DONE` drift/decay slots — both wired as no-ops earlier, now activated because their data (edges + internal state) finally exists. This is integration-point ordering paying off: nothing is built detached.

> **Deferred to Phase 6:** the unified review surface, the relationship-viewer UI over the audit log, the cost dashboard, registry/tunable management. This phase keeps reviews **inline** (extending the Phase-2 inline review) and the audit log **queryable** but not yet visualized.

---

## Product Backlog Overview

### Epic Summary

| Epic ID | Epic Name | Priority | Story Points | Sprints |
|---------|-----------|----------|--------------|---------|
| E1 | Character Depth Compile (bible → card) | Critical | 34 | 1–3 |
| E2 | The Relationship Mesh (edges) | Critical | 34 | 3–5 |
| E3 | Registers, Internal State & Rich NPC Blocks | Critical | 39 | 5–8 |
| E4 | The Delta Engine (appraisal → review → commit) | Critical | 34 | 8–10 |
| E5 | Decay, Scars & the Gap Clock | Critical | 24 | 10–12 |

**Total Estimated:** ~165 Story Points

---

## EPIC E1: Character Depth Compile (bible → card)

> Phase 1 created **minimal manual** characters; this epic builds the full **compile pipeline** (ADR 0013) and the **AI/manual/hybrid creation front door** (ADR 0018) that produce the deep artifacts later epics consume — cards, registers, sensitivities, disposition priors — and the **spoiler clamp** that keeps an early-chapter card from leaking a future arc.

### E1.1 — The Compile Pipeline & Creation Front Door

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.1.1 | As an **author**, I want to compile a source bible into runtime artifacts (`character_cards`, `registers`, `sensitivities`, `disposition_priors`, `lorebook_entries`) through the review gate so that the deep data behind every later block exists, human-verified | 13 | Critical | 1–2 |
| S-1.1.2 | As an **author**, I want AI / manual / hybrid creation modes as a front door onto the same compile + review + commit so that I can draft a character from a seed, by hand, or AI-then-edit — without a parallel pipeline | 8 | High | 2–3 |

**Acceptance Criteria - S-1.1.1:**
```gherkin
Feature: Bible → Artifact Compile

Scenario: Compile produces reviewable artifacts, never auto-committed
  Given a source bible (omniscient, never injected) with chapter/reveal annotations
  When I run the compile for chapter N
  Then it drafts: character_card (folded_identity, knowledge_boundary, disposition_priors, voice, tells),
       registers (over the fixed ADR 0006 dimensions), sensitivities, and lorebook_entries
  And each artifact is enqueued on the shared review gate (producer_type card_compile) — accept / edit / reject
  And committed artifacts are immutable at runtime (re-authoring = a new compile + review)

Scenario: The behavioral contrast compiles into registers
  Given a bible whose character behaves differently across two relationships
  Then the contrast compiles into two registers over the fixed dimension set
  And reused grammars are promoted to the shared register_archetypes library; bespoke ones are allowed

Scenario: Player card is appearance-only
  Given the player character
  Then the compile produces an appearance-only card + base_opacity and no outgoing edges
```

**Acceptance Criteria - S-1.1.2:**
```gherkin
Feature: Creation Modes (front door)

Scenario: AI mode drafts a bible then compiles
  Given a seed (name, role, traits, optional archetype)
  When I choose AI mode
  Then the LLM drafts a bible to content/bibles/<slug>.md (producer_type bible_generate)
  And it then runs the SAME compile + review + commit as an existing bible

Scenario: Manual mode needs no LLM
  Given no API key configured
  When I choose manual mode
  Then I fill card / register / sensitivity forms directly and commit (knowledge_boundary still mandatory)
  And a bible-less card (bible_path null) is allowed

Scenario: Hybrid mode is AI-drafted, human-edited
  Given AI-drafted artifacts
  Then every artifact is editable at the review gate before commit
  And the commit target + review gate are identical across all three modes
```

> **Technical Notes E1.1:**
> - **Preconditions:** Phase 0 review gate (`card_compile`/`bible_generate` producer types) + `compiler` LLM role + `content/bibles/` home + authoring tables; Phase 1 minimal character (this epic supersedes the manual minimal path with the full pipeline — minimal cards remain valid input).
> - **Integrates-into:** an `CharacterCompiler` (review-gate producer) + the per-story `characters` surface (extend the Phase-1 minimal editor with the seed/forms/archetype-picker). Offline authoring tooling (compile command), **never** in the runtime loop.
> - **Leak-guards:** authoring-time only — the compiler is omniscient but its **output** is the spoiler-bounded card. This is **not** a fourth runtime guard; it is the authoring-time enforcement the three runtime guards depend on. ADR 0013 §8 / 0018 §6.

---

### E1.2 — Spoiler Clamp & Per-Chapter Recompile

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.2.1 | As an **author**, I want the spoiler clamp (section tags + reveal ledger → `knowledge_boundary`) applied at compile so that a chapter-N card includes a fact **iff** its reveal point ≤ N, recording both what the character knows and does not know | 8 | Critical | 3 |
| S-1.2.2 | As a **system**, I want a full deterministic **recompile per (character, chapter)** (not a forward-diff) on bible/outline/ledger change so that epistemic progression is represented and auditable | 5 | High | 3 |

**Acceptance Criteria - S-1.2.1:**
```gherkin
Feature: Spoiler Clamp

Scenario: A future-arc fact is clamped out of an early card
  Given a reveal ledger entry { fact: "the diagnosis", reveal_chapter: late }
  When I compile a card for an early chapter
  Then the card does NOT contain "the diagnosis"
  And knowledge_boundary records it as an explicit "does NOT know" entry (or omits it)

Scenario: knowledge_boundary records both sides
  Given a compiled card
  Then knowledge_boundary lists both what the character knows and what it does not know
  So the assembler and recorder can structurally block hidden facts (Phase 2 guards)
```

**Acceptance Criteria - S-1.2.2:**
```gherkin
Feature: Per-Chapter Recompile

Scenario: Each chapter is a full recompile at that reveal state
  Given a bible and a chapter outline / reveal ledger
  When chapter N's card is compiled
  Then it is a full deterministic recompile at chapter N's reveal state, not a diff of chapter N-1
  And advancing chapters at runtime swaps in the next card snapshot (relationship/internal state carries via the save realm)

Scenario: Recompile re-runs the review gate
  Given the bible / outline / ledger changes (or a chapter is added)
  Then the affected card recompiles and re-runs through the review gate
```

> **Technical Notes E1.2:**
> - **Preconditions:** S-1.1.1; Phase 0 reveal-ledger surface; `character_cards` keyed per (character, chapter).
> - **Integrates-into:** the `CharacterCompiler` clamp step; the Phase-4 `CHAPTER_DONE` card-swap already consumes per-chapter snapshots (now they carry the clamped boundary).
> - **Leak-guards:** the `knowledge_boundary` clamp is the **authoring-time source** the runtime `knowledge_boundary` guard (Phase 2) relies on; a card that leaked a future arc would defeat every runtime guard. ADR 0013 §3–4.

---

### E1.3 — The Archetype Libraries

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.3.1 | As an **author**, I want a global **character-archetype** library (seeds a whole character: base_opacity, suggested axes, disposition priors, default registers, default sensitivities, voice scaffold) distinct from the register-archetype library so that common character shapes don't start from blank | 5 | Medium | 3 |

**Acceptance Criteria - S-1.3.1:**
```gherkin
Feature: Character Archetype Library

Scenario: Seed creation from an archetype
  Given a character archetype (e.g. koakuma)
  When I start creation from it
  Then it pre-fills base_opacity, suggested live_axes, disposition priors, default registers, default sensitivities, and a voice scaffold
  And every pre-filled field stays editable through the review gate (a starting point, never a constraint)

Scenario: Distinct from register archetypes
  Given the existing register_archetypes (grammar only)
  Then character_archetypes is a separate global library that references register archetypes among its default_registers
```

> **Technical Notes E1.3:**
> - **Preconditions:** S-1.1.2 (creation modes), Phase 0 `register_archetypes`.
> - **Integrates-into:** a new global `character_archetypes` library + the creation archetype-picker; no new runtime tables.
> - **Leak-guards:** none (authoring seed data). ADR 0018 §3.

---

## EPIC E2: The Relationship Mesh (edges)

> The single most behavior-determining structure (ADR 0002): directed, **owner-perspective** edges carrying live **axes** (value · awareness · bounds · rates · baseline), **topic_flags**, and a **register** binding. Seeded at fork from disposition priors; read (own-perspective only) into the NPC `SNAPSHOT`, and (full, hedged) into the narrator `MESH_AWARENESS`.

### E2.1 — Edge Schema & Seeding

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.1.1 | As a **system**, I want directed owner-perspective edges with live axes (`value` −100..+100, `bounds` soft/hard, `rates` asymmetric gain/loss, `baseline`), `topic_flags`, and a `register` (`base` + conditional `overrides`) so that relationships are asymmetric, bounded, and style-bearing | 13 | Critical | 3–4 |
| S-2.1.2 | As a **system**, I want disposition priors to seed new edges at **session fork** (by target traits: gender/demeanor/faction/shows-interest) so that new edges are not born neutral | 5 | Critical | 4 |
| S-2.1.3 | As a **system**, I want **awareness computed from value at read time** (`0-39 none · 40-59 vague · 60-79 subconscious · 80+ conscious`), with `mode: capped` to model blind spots, so that a character can feel strongly yet not consciously reach it | 8 | Critical | 5 |

**Acceptance Criteria - S-2.1.1:**
```gherkin
Feature: Relationship Edge Schema

Scenario: Edges are asymmetric and owner-perspective
  Given Luna and Vixia
  Then edge(Luna→Vixia) is independent of edge(Vixia→Luna)
  And each edge is Luna's self-perceived view (can be self-deceived)
  And only the live axes for that edge are instantiated

Scenario: Axes carry bounds, rates, baseline
  Given an affection axis
  Then it carries value (−100..+100), soft/hard floor+cap, asymmetric gain/loss rates, and a baseline
  And bounds are invisible to the character (authorial); drift is clamped to SOFT bounds (ruptures reach the soft↔hard band, Epic E4)

Scenario: Register binding + topic flags
  Given an edge
  Then it carries a register { base, overrides[] } and edge-scoped topic_flags
  And register is AUTHORED, never derived from the numbers
```

**Acceptance Criteria - S-2.1.2:**
```gherkin
Feature: Disposition-Prior Edge Seeding (at fork)

Scenario: New edges seeded from target traits at session fork
  Given a session is forked (Phase 1) and characters carry disposition_priors
  When edges are seeded
  Then each new edge's initial axis values, bounds, and register are seeded from the target's traits
  And edges are seeded at fork-time, not at character-creation time (creation only produces the priors)

Scenario: Phase-1 fork is now extended, not replaced
  Given the Phase-1 atomic fork (which seeded no edges)
  Then edge seeding is added to the same fork transaction (still atomic; template never mutated)
```

**Acceptance Criteria - S-2.1.3:**
```gherkin
Feature: Awareness From Value (read time)

Scenario: Tier derived at read time, not stored
  Given an axis value
  Then its awareness tier is computed at read time: 0-39 none, 40-59 vague, 60-79 subconscious, 80+ conscious

Scenario: Capped awareness models a blind spot
  Given an axis with awareness mode: capped
  Then the character feels the value's intensity but cannot consciously access/name it
  And this capped feeling must never be stated plainly downstream (the awareness_fold guard, Epic E3)
```

> **Technical Notes E2.1:**
> - **Preconditions:** E1 (disposition priors on cards); the Phase-1 session fork — now the **invisible fork triggered by chapter selection (E0.2)** — extended to seed edges; Phase 0 `relationship_edges` + `axes` tables.
> - **Integrates-into:** the Phase-1 `SessionService` fork (add edge seeding to the same atomic transaction); a `RelationshipMesh`/edge model in the save realm.
> - **Leak-guards:** edges are **owner-perspective** — the basis of `own_perspective_only`, now actively enforced (Phase 2 had no edges to leak). `capped` awareness sets up `awareness_fold` (Epic E3). ADR 0002 / 0004 (bounds).

---

### E2.2 — Narrator MESH_AWARENESS (now the mesh exists)

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.2.1 | As a **system**, I want the narrator `MESH_AWARENESS` block (full mesh → atmosphere / body-language / room-dynamics only; perceived reads hedged; never state a fact a present character would not know) so that the narrator uses relationships for texture without leaking them | 8 | Critical | 5 |

**Acceptance Criteria - S-2.2.1:**
```gherkin
Feature: Narrator MESH_AWARENESS

Scenario: The narrator sees the full mesh, bounded by a directive
  Given edges now exist (E2.1)
  When the narrator prompt assembles
  Then [MESH_AWARENESS] carries the full relationship mesh
  And the directive constrains it to atmosphere / body-language / room-dynamics ONLY
  And every perceived read is hedged ("looks / seems"); it never states a fact a present character would not know

Scenario: It feeds the recorder's hedged-attribution rule
  Given the narrator used the mesh for body-language
  Then the resulting surface still passes the Phase-2 hedged-attribution validator
  And MESH_AWARENESS is a directive, NOT a fourth leak guard

Scenario: It was correctly absent before the mesh existed
  Given Phases 1–4 had no edges
  Then MESH_AWARENESS was not assembled then (it lights up here, with its data)
```

> **Technical Notes E2.2:**
> - **Preconditions:** S-2.1.1 (edges exist); Phase 1 narrator assembler; Phase 2 hedged-attribution validator.
> - **Integrates-into:** add the `MESH_AWARENESS` block to the Phase-1 narrator assembler — this is the block deliberately deferred from Phase 4 (integration-point ordering: it reads the mesh, which only now exists).
> - **Leak-guards:** `MESH_AWARENESS` `leak_rule` = `hedged_attribution` (narrator-side soft anti-leak, feeding the recorder's structural rule). ADR 0016 §3 / §6.

---

## EPIC E3: Registers, Internal State & the Rich NPC Blocks

> The behavior engine: **behavior = axis value × expression mask × (card voice + relational register)**, modulated by the live emotional state. This epic lights the NPC `SELF`, `SNAPSHOT`, `MASKS`, `DIRECTIVES` blocks, activates `awareness_fold`, and makes the Phase-4 nudge **register/mask/awareness-gated**.

### E3.1 — Internal State `[SELF]`

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.1.1 | As a **system**, I want per-(session × character) internal state — `active_emotions` (label, intensity, baseline, source), `mood` (derived rollup + optional override), structured `motivation` — assembled into the NPC `SELF` block so that an NPC acts from its current inner state | 8 | Critical | 5–6 |
| S-3.1.2 | As a **system**, I want `masks` (global card-trait + emotion-driven/state masks) assembled into the NPC `MASKS` block (with edge `topic_flags`) so that what a character cannot or will not voice is gated | 5 | Critical | 6 |

**Acceptance Criteria - S-3.1.1:**
```gherkin
Feature: Internal State [SELF]

Scenario: SELF carries mood + emotions + motivation
  Given a character's internal state in a session
  When its prompt assembles
  Then [SELF] carries mood (derived from active_emotions, or a pinned mood_override), the active emotions, and the motivation
  And active_emotions carry a baseline (0 for acute, non-zero for chronic e.g. low-grade guilt) seeded from the card

Scenario: Internal state is per-character and transient
  Given internal state
  Then it is distinct from the per-pair edges and from edge decay; emotions never latch (Epic E5)
  And motivation is a short structured drive read by the interaction queue (Phase 3)
```

**Acceptance Criteria - S-3.1.2:**
```gherkin
Feature: Masks [MASKS]

Scenario: Global + state masks gate expression
  Given a character with a global mask ("cannot voice sincere gratitude") and a guilt-driven state mask
  When its prompt assembles
  Then [MASKS] carries global + emotion-driven masks, plus the edge topic_flags for the present relationship
  And the mask gates expression (it suppresses content) without changing what the character feels
```

> **Technical Notes E3.1:**
> - **Preconditions:** E1 (card seeds chronic baselines + global masks), E2 (edges for topic_flags); Phase 2 assembler; Phase 0 `internal_states` + `active_emotions` tables.
> - **Integrates-into:** add `SELF` + `MASKS` blocks to the Phase-2 NPC assembler; an `InternalStateService` in the save realm. (Appraisal writes emotions — Epic E4.)
> - **Leak-guards:** `SELF` = `own_perspective_only` (own state only); `MASKS` = `own_perspective_only`. ADR 0014 / 0007 §6.

---

### E3.2 — The SNAPSHOT Fold & `awareness_fold`

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.2.1 | As a **system**, I want the NPC `SNAPSHOT` block to fold the character's **own** edges (value × awareness) into prose so that the NPC acts on how it feels without seeing raw numbers or anyone else's edges | 8 | Critical | 6–7 |
| S-3.2.2 | As a **system**, I want `awareness_fold` enforced (a `capped` or below-threshold feeling is folded as a vague/subconscious pull, **never stated plainly**) so that blind spots and self-deception survive into the prompt | 5 | Critical | 7 |

**Acceptance Criteria - S-3.2.1:**
```gherkin
Feature: SNAPSHOT Fold (own edges)

Scenario: Own edges folded to prose
  Given a character with outgoing edges
  When its prompt assembles
  Then [SNAPSHOT] folds value × awareness into prose ("a strong, conscious fondness"; "an uneasy pull she can't name")
  And it contains only this character's OWN outgoing edges — never another character's edges

Scenario: own_perspective_only actively enforced
  Given multiple characters with edges
  Then a negative test confirms no other character's edge appears in this NPC's SNAPSHOT
  (in Phase 2 this was structurally trivial — no edges existed; now it is actively enforced)
```

**Acceptance Criteria - S-3.2.2:**
```gherkin
Feature: awareness_fold Guard

Scenario: A capped feeling is never stated plainly
  Given an axis with awareness mode: capped (or value below the conscious threshold)
  When SNAPSHOT folds it
  Then it appears as a vague/subconscious pull the character cannot name
  And it is NEVER folded as a plain conscious statement ("she knows she loves him")

Scenario: The guard holds at any model tier
  Given a minor NPC on the cheapest tier
  Then awareness_fold still suppresses the plain statement (structural, model-independent)
```

> **Technical Notes E3.2:**
> - **Preconditions:** E2.1 (edges + awareness tiers), E3.1 (assembler extended).
> - **Integrates-into:** the Phase-2 assembler's fold step gains `SNAPSHOT`; the awareness-tier computation from E2.1 drives the fold language.
> - **Leak-guards:** `SNAPSHOT` `leak_rule` = `awareness_fold` + `own_perspective_only` — **this is where `awareness_fold` first turns on** (its data, capped awareness, exists only now). ADR 0007 (fold) / 0002 (awareness).

---

### E3.3 — Registers (DIRECTIVES) & Register-Gated Nudge

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.3.1 | As a **system**, I want the register resolution pipeline (base/pin → axis-threshold selector → situational override → emotional modulation → mask+awareness gate) resolved into the NPC `DIRECTIVES` block so that *how* a character expresses is correct per relationship and state | 13 | Critical | 7–8 |
| S-3.3.2 | As a **system**, I want the Phase-4 nudge **register/mask/awareness-gated** at delivery (the same pipeline shapes *how hard/softly* the nudge lands; a `mood`-kind nudge colors the surface without being stored) so that direction respects the character's grammar | 8 | High | 8 |

**Acceptance Criteria - S-3.3.1:**
```gherkin
Feature: Register Resolution → DIRECTIVES

Scenario: The resolution pipeline runs per turn
  Given an edge with a base register and the character's current emotional state
  When DIRECTIVES resolves
  Then it runs: 1 base (may be a HARD PIN) → 2 axis-threshold variant → 3 situational override → 4 emotional modulation of the SURFACE → 5 mask + awareness gate
  And a pinned base bypasses the threshold selector
  And emotional modulation changes the surface intensity, never the grammar

Scenario: Behavior equation realized
  Given the resolved register
  Then behavior = resolve(register) → modulated by emotion → expressing axis-values-as-language → gated by mask + awareness
```

**Acceptance Criteria - S-3.3.2:**
```gherkin
Feature: Register-Gated Nudge

Scenario: The nudge lands through the register
  Given a leak-checked nudge (Phase 4) targeting a character
  When it is delivered
  Then the register/mask/awareness pipeline shapes HOW it lands (a guarded character resists a blunt nudge)
  And the leak-check from Phase 4 is unchanged — gating shapes delivery, never widens the boundary

Scenario: A mood-kind nudge is not stored
  Given a nudge of kind mood
  Then it colors the surface for the beat as the character's own coloring
  And it is NOT written into active_emotions (stored emotions are simulated state; the nudge is direction)
```

> **Technical Notes E3.3:**
> - **Preconditions:** E1 (registers compiled), E2 (edges + axes for threshold selection), E3.1 (emotional state for modulation), Phase 4 (the nudge).
> - **Integrates-into:** a `RegisterResolver` feeding the `DIRECTIVES` block; the Phase-4 `NUDGE` delivery now passes through it.
> - **Leak-guards:** `DIRECTIVES` = `own_perspective_only`; register-gating of the nudge keeps `omniscient_authoring` intact (it shapes delivery only). ADR 0006 / 0008 / 0014 §9.

---

## EPIC E4: The Delta Engine (appraisal → review → commit)

> The mover: after each beat, **appraisal** (match-only salience over universal priors + card sensitivities) proposes **edge-axis deltas** and **emotion deltas** — never writing directly. A **review gate** commits them with a mandatory `trigger` into an **append-only audit log**. Drift batches at `SCENE_DONE`; ruptures apply immediately. This fills the Phase-2 appraisal slot and the Phase-4 drift slot.

### E4.1 — Appraisal & Proposals

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.1.1 | As a **system**, I want per-beat appraisal that reads the character's **own projected surface** (witnessed evidence, never omniscient truth) and matches it against universal priors + card sensitivities (match-only salience) so that a character reacts only to what it cares about and witnessed | 13 | Critical | 8–9 |
| S-4.1.2 | As a **system**, I want appraisal to emit **multiple** proposals when multiple sensitivities fire (with `targets`: actor / beneficiary / witnessed-third-party) so that vicarious shifts and meaningful contradictions are manufactured, not resolved away | 5 | High | 9 |
| S-4.1.3 | As a **system**, I want appraisal to **also** emit emotion proposals (install/raise `active_emotions`) through the **same** review gate so that an event that moves an edge can also move an internal feeling | 5 | Critical | 9 |

**Acceptance Criteria - S-4.1.1:**
```gherkin
Feature: Appraisal (match-only, witnessed)

Scenario: Appraisal reasons over the projected surface only
  Given a committed beat record (Phase 2 recorder)
  When per-present-character appraisal runs
  Then it reads ONLY that character's witness-filtered, POV-projected surface (decoded via reads_target)
  And it never reads omniscient truth or another character's true_state (recorder-first sequencing, Phase 2 slot now filled)

Scenario: Match-only salience
  Given an event with no matched sensitivity (universal or card)
  Then no delta is proposed — the character is numb to what it doesn't care about

Scenario: Magnitude from weight × severity
  Given a matched sensitivity
  Then magnitude ≈ weight × LLM-judged severity against the shared severity rubric
  And the matched sensitivity names itself as the proposal's mandatory trigger string
```

**Acceptance Criteria - S-4.1.2:**
```gherkin
Feature: Multiple & Vicarious Proposals

Scenario: Multiple sensitivities → multiple proposals (unresolved)
  Given praise that hits both genuine_acknowledgment (respect up) and pitied_as_fragile (affection down)
  Then BOTH proposals are emitted, not reconciled — the engine manufactures the contradiction

Scenario: Vicarious targeting
  Given a witnessed event where A protects B
  And an observer with a third-party sensitivity
  Then the observer's edge toward A may shift even though A never acted on the observer
```

**Acceptance Criteria - S-4.1.3:**
```gherkin
Feature: Emotion Proposals

Scenario: Appraisal also proposes emotions
  Given an event ("he dodged the question")
  When appraisal runs
  Then it may propose an edge delta AND an emotion delta (install/raise "anxious")
  And both are proposals with a mandatory trigger through the same review gate (accept / edit / reject)
```

> **Technical Notes E4.1:**
> - **Preconditions:** Phase 2 recorder + POV projection + the **reserved appraisal slot** (recorder-first sequencing); E2 (edges to move), E3 (internal state to move); Phase 0 universal-priors library + severity rubric.
> - **Integrates-into:** an `AppraisalService` invoked in the Phase-2 in-loop sequence right after the recorder commits (the slot reserved in Phase 2 is now filled); proposals go to the existing review gate.
> - **Leak-guards:** appraisal reads only the **projected surface** (witnessed evidence) — the isolation constraint made concrete. `own_perspective_only` on the read. ADR 0005 / 0003 / 0014 §3 / 0016 §4.

---

### E4.2 — Two Channels, Review & Audit

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.2.1 | As a **system**, I want two delta channels — **drift** (tiny, rates-scaled, clamped to **soft** bounds, batched at `SCENE_DONE`) and **rupture** (large, clamped to **hard** bounds, applied **immediately in-scene**, may rewrite bounds / flip register / latch a scar) so that a hundred kindnesses nudge slowly while one betrayal blows through | 8 | Critical | 9–10 |
| S-4.2.2 | As an **author/player**, I want every delta to carry a mandatory `trigger` and commit to an **append-only audit log** (inline accept / edit / reject, extending the Phase-2 inline review) so that "why did trust drop?" is always answerable | 5 | Critical | 10 |

**Acceptance Criteria - S-4.2.1:**
```gherkin
Feature: Two Delta Channels

Scenario: Drift is slow, soft-clamped, batched
  Given ordinary positive events
  Then each proposes a tiny rates-scaled drift, clamped to SOFT bounds
  And drift is applied BATCHED at SCENE_DONE (filling the Phase-4 reserved drift slot)
  And drift can never break a ceiling

Scenario: Rupture is immediate and powerful
  Given a betrayal / confession
  Then the rupture is applied IMMEDIATELY in-scene, clamped to HARD bounds
  And it may rewrite bounds, flip the register base, or latch a scar (Epic E5)

Scenario: Some triggers are categorically rupture
  Given betrayal / confession / abandonment
  Then they are rupture_only regardless of magnitude
```

**Acceptance Criteria - S-4.2.2:**
```gherkin
Feature: Trigger + Audit Log

Scenario: No silent deltas
  Given any committed axis movement
  Then it carries a mandatory human-readable trigger
  And it is written to an append-only audit log per edge (no UPDATE/DELETE)

Scenario: Inline review extends Phase 2
  Given pending delta proposals
  Then I accept / edit / reject them inline in play (extending the Phase-2 inline beat-record review)
  And only accepted/edited deltas commit (the relationship-VIEWER over this log is Phase 6)
```

> **Technical Notes E4.2:**
> - **Preconditions:** S-4.1.x; E2 (bounds soft/hard); Phase 2 inline review surface + recorder-first sequencing; Phase 4 `SCENE_DONE` reserved drift slot.
> - **Integrates-into:** a `DeltaEngine` committing through the existing review gate to the append-only `relationship_events` (audit) log; ruptures apply in-scene, drift batches at the Phase-4 `SCENE_DONE` (slot now filled).
> - **Leak-guards:** none new; the audit log is the raw material the Phase-6 relationship viewer renders. Append-only invariant (no UPDATE/DELETE). ADR 0003 / 0016 §4.

---

## EPIC E5: Decay, Scars & the Gap Clock

> Time and trauma. **Decay** runs on the narrative clock (keyed to the Phase-4 elapsed bucket), pulling values toward baseline but **stopping at any latched floor**. **Latched scars** unify commitment (positive latch) and trauma (high-magnitude latch). Emotions run their **own gentle, bounded** clock. This fills the Phase-4 `SCENE_DONE`/`CHAPTER_DONE` decay slots.

### E5.1 — Latched Scars & Narrative-Time Decay

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.1.1 | As a **system**, I want latched scars (`peak` high-water marks → `latch_threshold` → `scar.floor = retain × peak`, with `triggers` and `overcome_by`) so that a peaked fear becomes lasting trauma and a peaked affection becomes commitment — effective floor = `max(soft_floor, scar.floor)` | 8 | Critical | 10–11 |
| S-5.1.2 | As a **system**, I want narrative-time **decay** at scene/chapter boundaries (scaled by the declared elapsed bucket; pulls toward baseline; stops at latched floors; committed/latched edges decay-exempt) so that relationships cool from neglect without erasing development | 5 | Critical | 11 |

**Acceptance Criteria - S-5.1.1:**
```gherkin
Feature: Latched Scars (commitment & trauma)

Scenario: A peak latches a permanent floor
  Given an axis whose high-water mark crosses latch_threshold
  Then a scar latches a floor = latch_retain × peak that decay can never cross
  And effective floor = max(static soft_floor, scar.floor)

Scenario: Commitment vs trauma, one mechanism
  Given affection peaks high → positive latch (won't abandon over small things)
  And fear peaks high → high-magnitude latch (lingering flaw)
  Then both are the same latch mechanism

Scenario: Scar triggers and overcoming
  Given a fear scar with triggers
  Then a matching event may RUPTURE-spike the value back toward peak (Epic E4)
  And only a deliberate growth-arc rupture can lower/clear the floor (overcome_by); time/drift cannot
```

**Acceptance Criteria - S-5.1.2:**
```gherkin
Feature: Narrative-Time Decay

Scenario: Decay scales with declared elapsed time, not beat count
  Given a boundary that declared a gap of days+ (the Phase-4 elapsed bucket)
  When SCENE_DONE / CHAPTER_DONE fires
  Then decay pulls each value toward baseline scaled by the bucket (a one-month gap decays the same whether 1 chapter or 20 beats)
  And a continuous / filler boundary applies NO time decay

Scenario: Decay stops at latched floors and skips committed edges
  Given a latched floor or a committed edge
  Then decay stops at the floor and never crosses it; committed/latched edges are decay-exempt
  And this fills the Phase-4 reserved decay slot (no-op until now)
```

> **Technical Notes E5.1:**
> - **Preconditions:** E2 (edges + bounds + baseline), E4 (ruptures latch; peaks tracked), Phase 4 elapsed bucket + reserved `SCENE_DONE`/`CHAPTER_DONE` decay slots.
> - **Integrates-into:** a `DecayService` fired by the Phase-4 boundary events (the reserved slots now execute); scar fields on the axis schema.
> - **Leak-guards:** none. Ruptures still apply in-scene; only drift + decay are boundary-batched. ADR 0004 / 0015 §5–6.

---

### E5.2 — The Emotion Own-Clock & Runtime Sensitivities

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.2.1 | As a **system**, I want emotions to move on their **own gentle, bounded** clock (on-screen: small reversion toward baseline; off-screen gap: small **random** bounded step ≈±3, mean-reverting & clamped; explicit narration overrides deterministically) so that acute feelings ease off honestly without runaway swings | 5 | High | 11–12 |
| S-5.2.2 | As a **system**, I want **runtime-installed** sensitivities (ruptures write new re-spike triggers into mutable state) so that the effective sensitivity set = card sensitivities + acquired ones | 3 | Medium | 12 |

**Acceptance Criteria - S-5.2.1:**
```gherkin
Feature: Emotion Own-Clock

Scenario: On-screen reversion toward baseline
  Given an acute emotion and no sustaining events
  When a boundary applies
  Then it eases toward baseline by a small bounded step (cap ≈±3, tunable)

Scenario: Off-screen gap is a bounded random wobble
  Given a timeskip where what happened is UNKNOWN
  Then a small random up/down step within the cap is applied, mean-reverting and clamped
  And a long gap can never swing an emotion beyond the cap (the baseline stays true)

Scenario: Explicit narration overrides the roll
  Given the continuation narrates the gap ("three weeks, they barely spoke")
  Then that explicit signal sets the value deterministically and NO random roll is taken

Scenario: Emotions never latch
  Given any emotion
  Then it reverts toward baseline and leaves no permanent floor (scars are an edge mechanism, E5.1)
```

**Acceptance Criteria - S-5.2.2:**
```gherkin
Feature: Runtime-Installed Sensitivities

Scenario: A rupture installs a new trigger
  Given a defining rupture (e.g. a fear scar)
  Then it installs its own re-spike triggers into mutable state
  And appraisal's effective sensitivity set = authored card sensitivities + runtime-installed ones
```

> **Technical Notes E5.2:**
> - **Preconditions:** E3.1 (internal state + emotions), E4 (appraisal/ruptures), Phase 4 elapsed bucket + explicit-narration override seam.
> - **Integrates-into:** the `InternalStateService` own-clock fired at boundaries; runtime sensitivities stored in the save realm and read by `AppraisalService`.
> - **Leak-guards:** none. The ≈±3 cap is shared tunable config (the management surface is Phase 6; a default until then). ADR 0014 §5 / 0005 (runtime-installed).

---

## Sprint Roadmap

### Sprint 1–2: Compile Pipeline (E1.1)
```
├── S-1.1.1: Bible → artifacts compile through review gate (card/registers/sensitivities/priors/lorebook)
├── S-1.1.2: AI / manual / hybrid creation front door
└── Test: nothing auto-commits; player card appearance-only
```

### Sprint 3: Spoiler Clamp, Recompile, Archetypes & Edge Schema start (E1.2 + E1.3 + E2.1 start)
```
├── S-1.2.1: Spoiler clamp (section tags + reveal ledger → knowledge_boundary, both sides)
├── S-1.2.2: Full deterministic per-(character,chapter) recompile
├── S-1.3.1: Character-archetype library
├── S-2.1.1: Edge schema (begin) — axes/bounds/rates/baseline/register/topic_flags
└── Test (leak guard): a future-arc fact never enters an early card
```

### Sprint 4: Edges & Seeding (E2.1)
```
├── S-2.1.1: Edge schema (finish)
├── S-2.1.2: Disposition-prior edge seeding at fork (extend the Phase-1 atomic fork)
└── Test: fork still atomic; template never mutated
```

### Sprint 5: Awareness, MESH_AWARENESS & SELF start (E2.1 + E2.2 + E3.1 start)
```
├── S-2.1.3: Awareness from value at read time (+ capped blind spots)
├── S-2.2.1: Narrator MESH_AWARENESS block (deferred from Phase 4; mesh now exists)
├── S-3.1.1: Internal state [SELF] (begin)
└── Test: MESH_AWARENESS reads hedged; was correctly absent in Phases 1–4
```

### Sprint 6: SELF, MASKS & SNAPSHOT start (E3.1 + E3.2 start)
```
├── S-3.1.1: [SELF] (finish — mood/emotions/motivation)
├── S-3.1.2: [MASKS] (global + state masks + topic_flags)
├── S-3.2.1: [SNAPSHOT] fold (begin — own edges only)
└── Test (leak guard): no other character's edge appears in this NPC's SNAPSHOT
```

### Sprint 7: SNAPSHOT, awareness_fold & DIRECTIVES start (E3.2 + E3.3 start)
```
├── S-3.2.1: [SNAPSHOT] (finish)
├── S-3.2.2: awareness_fold guard (capped feeling never stated plainly; any tier)
├── S-3.3.1: Register resolution → [DIRECTIVES] (begin)
└── Test (leak guard): awareness_fold holds on the cheapest model tier
```

### Sprint 8: DIRECTIVES, Register-Gated Nudge & Appraisal start (E3.3 + E4.1 start)
```
├── S-3.3.1: Register resolution → [DIRECTIVES] (finish 5-step pipeline)
├── S-3.3.2: Register/mask/awareness-gated nudge delivery (leak-check unchanged)
├── S-4.1.1: Appraisal over the projected surface (begin — fills the Phase-2 slot)
└── Test: emotional modulation changes surface, never grammar
```

### Sprint 9: Appraisal & Channels start (E4.1 + E4.2 start)
```
├── S-4.1.1: Appraisal (finish — match-only salience, witnessed only)
├── S-4.1.2: Multiple + vicarious proposals (unresolved contradictions)
├── S-4.1.3: Emotion proposals (same review gate)
├── S-4.2.1: Two channels (begin — drift soft/batched, rupture hard/immediate)
└── Test (leak guard): appraisal never reads omniscient truth / others' true_state
```

### Sprint 10: Channels, Audit & Scars start (E4.2 + E5.1 start)
```
├── S-4.2.1: Two channels (finish — rupture may rewrite bounds / flip register / latch)
├── S-4.2.2: Mandatory trigger + append-only audit log + inline review
├── S-5.1.1: Latched scars (begin — peak/threshold/floor)
└── Test: no silent deltas; audit log append-only
```

### Sprint 11: Scars, Decay & Emotion Clock start (E5.1 + E5.2 start)
```
├── S-5.1.1: Latched scars (finish — triggers / overcome_by; effective floor = max)
├── S-5.1.2: Narrative-time decay (fills the Phase-4 decay slot; stops at floors)
├── S-5.2.1: Emotion own-clock (begin — on-screen reversion)
└── Test: continuous boundary applies no decay; latched floor never crossed
```

### Sprint 12: Emotion Clock & Runtime Sensitivities (E5.2)
```
├── S-5.2.1: Emotion own-clock (finish — off-screen bounded random + narration override)
├── S-5.2.2: Runtime-installed sensitivities (effective set = authored + acquired)
└── Phase 5 end-to-end: a character evolves across a betrayal + a timeskip, every delta explainable
```

---

## Definition of Done (DoD)

See the [program DoD](./README.md#9-global-definition-of-done-dod). Phase-5 emphasis:

- [ ] A character **evolves** across a scene (drift), a betrayal (rupture), and a timeskip (decay) — and **every** axis movement is explainable from a mandatory `trigger` in the append-only audit log.
- [ ] The **compile pipeline** produces deep artifacts through the review gate; the **spoiler clamp** keeps a future-arc fact out of an early-chapter card (negative test).
- [ ] The rich NPC blocks (`SELF`, `SNAPSHOT`, `MASKS`, `DIRECTIVES`) assemble correctly; **`awareness_fold`** is enforced (capped feelings never stated plainly) and **`own_perspective_only`** is actively tested (no other character's edge in an NPC's `SNAPSHOT`) — both at the cheapest model tier.
- [ ] **`MESH_AWARENESS`** lights up now (its data exists) and stays hedged; it was correctly **absent** in Phases 1–4.
- [ ] Appraisal reasons **only over the witnessed, projected surface** (negative test: never omniscient truth or another's `true_state`); **drift** batches at `SCENE_DONE`, **ruptures** apply in-scene — filling the Phase-2 appraisal slot and the Phase-4 drift/decay slots.
- [ ] **Latched scars** unify commitment + trauma; decay stops at floors; emotions revert (never latch). Append-only invariants respected; `pnpm lint` clean; UX states covered.

---

## Success Metrics — Phase 5

| Metric | Target | Measurement |
|--------|--------|-------------|
| Explainable evolution | 100% | Every committed delta carries a trigger; "why did trust drop?" always answerable |
| Spoiler safety | 0 leaks | No future-arc fact enters an early-chapter card (clamp negative test) |
| awareness_fold enforcement | 100% | Capped/below-threshold feelings never stated plainly, any tier |
| own_perspective_only | 0 cross-edge leaks | No other character's edge appears in an NPC's SNAPSHOT |
| Appraisal isolation | 0 leaks | Appraisal reads only the projected surface, never omniscient truth |
| Decay correctness | Bucket-keyed | Continuous boundary = no decay; latched floors never crossed |

---

## Risk Register — Phase 5

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Spoiler leak from an early-chapter card | Critical | Medium | Reveal ledger + section tags → knowledge_boundary clamp at compile, behind the review gate; negative test |
| `awareness_fold` fails on a weak model (states a capped feeling plainly) | High | Medium | Structural fold rule + negative test at the cheapest tier; review gate as the floor |
| An NPC's SNAPSHOT leaks another character's edge | Critical | Medium | own_perspective_only actively enforced + negative test (trivial in Phase 2, real now) |
| Appraisal peeks at omniscient truth | Critical | Low | Recorder-first sequencing (Phase 2 slot) — appraisal reads only the projected surface; negative test |
| Numbers feel mechanical / behavior reads flat | High | Medium | Register pipeline + emotional modulation + awareness fold turn numbers into voice; tune the severity rubric |
| Runaway emotion swings over long gaps | Medium | Medium | Bounded ≈±3 cap, mean-reverting, narration-override; emotions never latch |
| Phase complexity overruns (largest phase) | High | High | Strict per-epic ordering (compile → edges → blocks → deltas → decay); reserved slots from Phases 2/4 keep work integrated, not detached |

---

*Document Version: 2.0 · Author: Zulfikar Hidayatullah · Created: June 2026*

