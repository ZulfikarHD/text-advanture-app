# Phase 2: One Live Character — SillyTavern parity + the isolation boundary
## Directed Interactive Novel Engine (DINE)

**Timeline:** ~1.5 Months (5–6 Sprints)
**Sprint Duration:** 1 Week
**Depends on:** Phase 1 (the loop spine, narrator prose call, player moment, play surface).
**Governing ADRs:** 0007 (NPC context assembly — the assembler + isolation boundary), 0010 (recorder mechanics), 0009 (POV projection), 0016 (narrator turn = prose + recorder; in-loop sequencing), 0020 (NPC blocks).

> **Goal — play a scene with one in-character NPC.** This phase builds the piece the whole engine is organized around: **the assembler that is both a compiler and the isolation boundary**, plus the **recorder** (two-layer `surface`/`true_state` record) and **POV projection** that produce the witnessed excerpt an NPC is allowed to see. A single present NPC takes a turn, reads only its own card + its witnessed, POV-projected surface, and responds in character. This is **SillyTavern-style single-character play** — and it establishes the three-agent context boundary that every later phase enriches.
>
> **The review gate becomes real here.** The recorder's `beat_record` is the **first producer** to call `ReviewGateService::propose()` — reviewed **inline in play** (not on the orphaned standalone `/reviews` page). This is exactly where the review gate belongs: in the middle of play, after the NPC session works.

> **NPC blocks lit this phase:** `IDENTITY` (system), `SCENE_RULES` (system), `SCENE_EXCERPT` (user). **Guards activated:** `knowledge_boundary`, `hedged_attribution`, `own_perspective_only` (+ POV projection = the third leak guard).
> **Deferred:** the rich NPC blocks `SELF`/`SNAPSHOT`/`MASKS`/`DIRECTIVES` and all relationship psychology (Phase 5); the multi-NPC interaction queue + orchestration (Phase 3); the `NUDGE` block + beat intent (Phase 4). A single present NPC needs no queue yet.

---

## Product Backlog Overview

### Epic Summary

| Epic ID | Epic Name | Priority | Story Points | Sprints |
|---------|-----------|----------|--------------|---------|
| E1 | NPC Assembler & Isolation Boundary (thin) | Critical | 21 | 1–2 |
| E2 | The Recorder — Two-Layer Record | Critical | 21 | 2–3 |
| E3 | POV Projection (per-NPC excerpt) | Critical | 13 | 3–4 |
| E4 | Player Input & Sourced Delivery | High | 8 | 4 |
| E5 | NPC Moment in the Loop + Inline Review | Critical | 13 | 5 |

**Total Estimated:** ~76 Story Points

---

## EPIC E1: NPC Assembler & Isolation Boundary (thin)

> The assembler is the heart of the app: **a compiler** (structured data → folded prose blocks) **and the isolation boundary** (the one place that guarantees an NPC sees only its own data + what it witnessed). It is built **thin** here (3 blocks) and **enriched in place** in Phases 4–5 — never rebuilt.

### E1.1 — Assemble & Act (thin NPC)

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.1.1 | As a **system**, I want to assemble a thin NPC prompt from the registry (`IDENTITY`, `SCENE_RULES` + user `SCENE_EXCERPT`) reading only this NPC's own card + its witnessed surface so that isolation is enforced by construction from the first NPC | 8 | Critical | 1 |
| S-1.1.2 | As a **system**, I want the two-stage turn (compile → act) with model tiering (major = full card/strong, minor = compressed/cheap) so that the NPC acts within what it knows and cost is controllable | 5 | Critical | 2 |
| S-1.1.3 | As a **system**, I want explicit isolation negative tests (the beat doc, other cards, other edges, another character's true_state, and narrator instructions never reach an NPC prompt — at any model tier) so that the boundary is verified, not assumed | 8 | Critical | 2 |

**Acceptance Criteria - S-1.1.1:**
```gherkin
Feature: Thin NPC Prompt Assembly (own data only)

Scenario: Assemble an NPC prompt from this NPC's own data
  Given a scene where Luna (NPC) and the player are present
  And it is Luna's turn to act
  When the assembler compiles Luna's prompt from prompt_blocks for agent "npc"
  Then the system prompt contains, in registry order: [IDENTITY], [SCENE RULES]
  And the user prompt contains [SCENE EXCERPT] + "How does Luna respond?"
  And every block is sourced only from Luna's own card (+ knowledge_boundary), the scene config, and the witnessed surface

Scenario: Blocks with no producer are omitted, not invented
  Given SELF/SNAPSHOT/MASKS/DIRECTIVES/NUDGE producers do not exist yet
  When Luna's prompt is assembled
  Then those blocks are absent (no placeholder filler occupies their slot)

Scenario: The assembler shares the registry-driven selection with the narrator assembler
  Given the Phase-1 narrator assembler
  Then the NPC assembler reuses the same prompt_blocks-driven selection/order/fold logic for agent "npc"
```

**Acceptance Criteria - S-1.1.2:**
```gherkin
Feature: Two-Stage Compile -> Act with Model Tiering

Scenario: Each NPC turn is compile then act
  Given any NPC takes a turn
  Then the turn comprises a compile call (structured state -> folded blocks) and an act call (in-character response)
  And both are logged to llm_calls with role, tokens, cost, latency

Scenario: The NPC acts within the limits of what it knows
  Given Luna's prompt embeds only her witnessed surface and her card
  And a fact she did not witness exists in the scene's true_state
  When the act call runs
  Then her response never references the unwitnessed fact

Scenario: Model tier follows the character
  Given a major NPC and a minor NPC
  Then the major resolves to npc_major (full card) and the minor to npc_minor (compressed card)
```

**Acceptance Criteria - S-1.1.3:**
```gherkin
Feature: Isolation Boundary Negative Tests

Scenario: Forbidden inputs never reach an NPC prompt
  Given a beat with omniscient intent, another character's card/edges, another character's true_state, and narrator-side notes
  When Luna's prompt is assembled
  Then none of: the raw beat intent, any other character's card, any edge Luna does not own, any other character's true_state, or any narrator instruction appears in her prompt

Scenario: A "read surface only" path cannot reach true_state
  Given the committed two-layer record
  When the assembler builds [SCENE EXCERPT]
  Then it reads only the surface (separate table) and cannot return another character's true_state

Scenario: Isolation holds at the cheapest tier
  Given a minor NPC assembled on npc_minor
  Then the same forbidden inputs are absent — safety does not depend on the model tier
```

> **Technical Notes E1.1:**
> - **Preconditions:** Phase 1 narrator assembler (registry-driven selection), Phase 0 `character_cards` + `prompt_blocks` + `LlmClient` (`npc_major`/`npc_minor` roles).
> - **Integrates-into:** extend the Phase-1 assembler into a shared `PromptAssembler` serving both `agent=narrator` and `agent=npc`; add a `NpcTurnService` (compile→act). This is ADR 0007's assembler, built thin; Phases 4–5 add blocks to the **same** assembler.
> - **Leak-guards:** `IDENTITY` = `knowledge_boundary`; `SCENE_RULES` = `none`; `SCENE_EXCERPT` = `hedged_attribution` + `knowledge_boundary`. `own_perspective_only` is enforced structurally now (the NPC has only its own card; no foreign data path exists) and tested so it stays true when edges arrive in Phase 5.
> - Two LLM calls per NPC turn → stable-block caching matters; basic caching here, full orchestration in Phase 3. ADR 0007.

---

## EPIC E2: The Recorder — Two-Layer Record

> The narrator turn becomes **two calls** (prose, then recorder), per ADR 0016 §1. The recorder produces the **isolation data structure** the whole engine depends on: a public `surface` that crosses agents and a per-character private `true_state` that never does.

### E2.1 — Two-Layer Record, Validation & Legibility

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.1.1 | As a **system**, I want the recorder sub-call to commit the two-layer record (`surface` + per-character `true_state` + `witnessed_by{full\|overheard\|partial}` + `pov_anchor`) so that downstream agents read only the public surface and never another character's private state | 8 | Critical | 2 |
| S-2.1.2 | As a **system**, I want hedged-attribution validation (reject unhedged "is sad"/"is lying"; block hidden facts via `knowledge_boundary`) before the record commits through the review gate so that safety holds at any model tier | 5 | Critical | 3 |
| S-2.1.3 | As a **system**, I want legibility derived for the committed surface read (this phase: driven by card `base_opacity`; register/awareness factors arrive in Phase 5) so that a poker-faced character is harder to read than an open one | 5 | High | 3 |
| S-2.1.4 | As a **system**, I want recorder-first in-loop sequencing (the record commits before any later appraisal slot) so that anything reading the beat reasons over witnessed evidence, never omniscient truth | 3 | Critical | 3 |

**Acceptance Criteria - S-2.1.1:**
```gherkin
Feature: Two-Layer Beat Record

Scenario: The recorder commits surface and true_state separately
  Given a beat has played out (prose + player input + NPC action)
  When the recorder sub-call runs on the recorder model
  Then it commits:
    | Layer        | Content                                                 | Crosses agents? |
    | surface      | observable behavior + dialogue + HEDGED perceived reads | yes             |
    | true_state   | per-character private feeling/intent                    | no, never       |
    | witnessed_by | per character: full | overheard | partial               | tags only       |
    | pov_anchor   | the scene-contract anchor                               | display only    |
  And true_state is written to the separate beat_true_states child table

Scenario: Downstream reads only the surface
  Given the committed record
  When any downstream consumer reads it
  Then it can read only surface and never another character's true_state
```

**Acceptance Criteria - S-2.1.2:**
```gherkin
Feature: Hedged-Attribution Validation via the Review Gate

Scenario: Unhedged mental-state assertions are rejected
  Given a proposed surface asserting "is sad" or "is lying"
  When the recorder validates before commit
  Then the unhedged assertion is rejected; only "looks/seems/appears" forms pass

Scenario: Hidden facts blocked by knowledge_boundary
  Given a proposed surface that would expose a fact behind a character's knowledge_boundary
  Then that fact is blocked before commit

Scenario: The record commits through the review gate
  Given a validated proposed record
  Then it is enqueued via ReviewGateService.propose() with producer_type beat_record
  And committed on accept; the validator is structural and holds even on a weak model
```

**Acceptance Criteria - S-2.1.3:**
```gherkin
Feature: Legibility Derivation (this-phase subset)

Scenario: A poker-faced character dampens to "hard to read"
  Given a target with high base_opacity whose true_state is turmoil
  When the recorder commits the surface read
  Then the surface dampens to a composed / hard-to-read read
  And legibility this phase = base_opacity-driven (register composure/tells + awareness/mask factors are layered in at Phase 5)
```

**Acceptance Criteria - S-2.1.4:**
```gherkin
Feature: Recorder-First Sequencing

Scenario: The record commits before any appraisal slot runs
  Given a beat has played out
  Then the recorder commits surface + true_state + witnesses first
  And the appraisal slot (built in Phase 5) is positioned to read only the committed, projected surface
  And nothing downstream can peek at omniscient truth
```

> **Technical Notes E2.1:**
> - **Preconditions:** Phase 1 narrator turn (prose call) + state machine; Phase 0 `beat_records`/`beat_true_states`/`beat_witnesses` tables + `ReviewGateService` (`propose()` exists, uncalled) + `recorder` role.
> - **Integrates-into:** add the **recorder sub-call** as the second half of the narrator turn (ADR 0016 §1); call the existing `ReviewGateService::propose()` — **this is the first producer wired to it.** The review *surface* for it is inline (E5), not the standalone page.
> - **Leak-guards:** `hedged_attribution` + `knowledge_boundary` enforced by a **structural validator** that does not trust the model; the review gate is the human fidelity floor. The recorder runs on the strong model; minor NPCs never record. ADR 0010 / 0016.

---

## EPIC E3: POV Projection (per-NPC excerpt) — the third leak guard

### E3.1 — Per-NPC Projection & Display

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.1.1 | As a **system**, I want each NPC's excerpt projected to its own limited POV (witness-filter → fidelity-degrade → surface-only → decode → validate vs `knowledge_boundary`) so that no NPC receives another character's interiority or hidden facts | 8 | Critical | 3 |
| S-3.1.2 | As a **player**, I want the display rendered in the scene's POV contract (`pov_anchor` interiority allowed in display only) so that I read coherent prose while the NPC agent gets only the projected surface | 5 | High | 4 |

**Acceptance Criteria - S-3.1.1:**
```gherkin
Feature: Per-NPC POV Projection

Scenario: An NPC excerpt is projected to that NPC's limited POV
  Given a committed surface and an NPC present at the beat
  When the NPC's excerpt is built
  Then the pipeline runs in order:
    | Step               | Effect                                                  |
    | witness-filter     | keep only what witnessed_by includes for this NPC       |
    | fidelity-degrade   | degrade per the NPC's full / overheard / partial tag    |
    | surface-only       | reduce to the public surface layer                      |
    | decode             | apply the NPC's read quality (default faithful; reads_target tuning arrives Phase 5) |
    | knowledge_boundary | validate against the NPC's knowledge_boundary           |

Scenario: No NPC receives another character's interiority or hidden facts
  Given the projected excerpt
  Then it never contains any other character's true_state
  And it never contains a fact outside that NPC's knowledge_boundary
```

**Acceptance Criteria - S-3.1.2:**
```gherkin
Feature: Display in the POV Contract

Scenario: The human reads coherent prose in the scene's POV
  Given the scene's POV contract with its pov_anchor
  When prose is displayed to the player
  Then it renders in that contract and pov_anchor interiority is allowed in DISPLAY only

Scenario: The display path never feeds an NPC the anchor's interiority
  Given the same beat
  Then NPC agents receive only their own projected surface, never the display's anchor interiority
```

> **Technical Notes E3.1:**
> - **Preconditions:** S-2.1.1 (committed surface + witnesses), Phase 1 play surface.
> - **Integrates-into:** a `PovProjector` that sits between the recorder's `surface` and the assembler's `SCENE_EXCERPT`; the display path reuses the Phase-1 play surface.
> - **Leak-guards:** this is the **third leak guard** (others' hidden truth + narrator omniscience). Two per-edge directional dials (legibility at the recorder, decode at projection) — decode is **faithful by default** this phase because `reads_target` lives on edges/registers built in Phase 5. Safety is structural and model-independent; fidelity is best-effort + human-backstopped. ADR 0009.

---

## EPIC E4: Player Input & Sourced Delivery

### E4.1 — Sourced Delivery into the Record

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.1.1 | As a **player**, I want my input's delivery sourced (prose → optional tone tag → narrator infer + ask-when-ambiguous) and recorded into surface/true_state so that the NPC witnesses my delivery rather than the engine decoding bare text | 8 | High | 4 |

**Acceptance Criteria - S-4.1.1:**
```gherkin
Feature: Player Input with Sourced Delivery

Scenario: Delivery sourced from prose
  Given I write "'Fine,' I say, forcing a smile"
  When I submit
  Then the recorder commits my surface + true_state from the sourced delivery
  And the NPC witnesses my surface, not bare text

Scenario: Optional tone tag
  Given I attach a tone tag instead of writing delivery into prose
  Then the delivery is taken from the tag

Scenario: Infer with ask-when-ambiguous
  Given bare dialogue with a genuinely ambiguous read
  When the narrator cannot resolve the delivery
  Then it proposes a surface and only interrupts to confirm when it cannot resolve it
```

> **Technical Notes E4.1:**
> - **Preconditions:** Phase 1 player input (S-5.1.1), the recorder (S-2.1.1).
> - **Integrates-into:** the Phase-1 play input control + the recorder. **Player delivery is SOURCED, never decoded from bare text** (prose → tone tag → infer/ask). NPC delivery needs no decode (the NPC reports its own intent).
> - **Leak-guards:** the player's committed surface is the witnessed input feeding the NPC's `SCENE_EXCERPT` (subject to projection). ADR 0010.

---

## EPIC E5: NPC Moment in the Loop + Inline Review

### E5.1 — The npc_moment Branch & Inline Review Gate

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.1.1 | As a **system**, I want the `npc_moment` handoff branch to run the single present NPC's compile→act turn, recorded and witness-tagged, then resume the narrator so that the narrator → me → NPC loop is complete for one character | 8 | Critical | 5 |
| S-5.1.2 | As an **author/player**, I want pending `beat_record` proposals reviewed **inline in the play screen** (accept / edit / reject) so that I am the fidelity floor mid-play — using the existing `ReviewGateService`, not a detached page | 5 | Critical | 5 |

**Acceptance Criteria - S-5.1.1:**
```gherkin
Feature: NPC Moment (single character)

Scenario: npc_moment runs the present NPC's turn
  Given the prose call hands off npc_moment and exactly one NPC is present
  When the loop advances
  Then that NPC's two-stage compile->act turn runs
  And its output is recorded (witness-tagged) and handed back, then the narrator resumes
  And the act call itself commits no state beyond the record

Scenario: Handoff vocabulary now includes npc_moment
  Given the Phase-1 spine (player_moment | beat_complete)
  Then npc_moment is added as a reachable branch (the multi-NPC queue arrives in Phase 3)
```

**Acceptance Criteria - S-5.1.2:**
```gherkin
Feature: Inline Beat-Record Review

Scenario: Review a pending record without leaving play
  Given the recorder enqueued a beat_record proposal
  When I review it inline on the play screen
  Then I can accept, edit, or reject it
  And only accepted/edited content becomes the canonical record
  And the reviewer + timestamp are recorded

Scenario: The review surface lives in play, not the standalone page
  Given the orphaned standalone /reviews page from Phase 0
  Then beat_record review is surfaced inline in the play context (the standalone page is unified later in Phase 6)

Scenario: The private layer is never exposed in review
  Given a beat_record review view
  Then it shows the observable surface + hedged-attribution result
  And no character's private true_state is exposed in that view
```

> **Technical Notes E5.1:**
> - **Preconditions:** E1 (assembler/act), E2 (recorder + `propose()` wired), E3 (projection), Phase 1 spine + play surface.
> - **Integrates-into:** add the `npc_moment` branch to the Phase-1 `SessionStateMachine`; add an **inline review panel** to the Phase-1 `Play.vue` that calls the existing `ReviewGateService` accept/edit/reject. **Do not** build a new review page — that is the orphan mistake; the unified surface comes in Phase 6.
> - **Leak-guards:** recorder-first sequencing (S-2.1.4) guarantees the inline review reasons over the committed projected surface; the review view never renders `true_state`. ADR 0010 / 0016 / 0012 §5.

---

## Sprint Roadmap

### Sprint 1: Thin Assembler (E1.1 start)
```
├── S-1.1.1: Assemble thin NPC prompt (IDENTITY + SCENE_RULES + SCENE_EXCERPT), own data only
└── Test: deferred blocks absent; assembler shares registry-driven selection with the narrator
```

### Sprint 2: Act, Tiering, Isolation Tests & Recorder (E1.1 + E2.1 start)
```
├── S-1.1.2: Two-stage compile->act + model tiering
├── S-1.1.3: Isolation negative tests (no beat doc/other cards/edges/true_state/narrator; any tier)
├── S-2.1.1: Two-layer record (surface + true_state + witnessed_by + pov_anchor)
└── Test (leak guard): a "read surface only" path cannot reach true_state
```

### Sprint 3: Recorder Safety, Legibility, Sequencing & Projection (E2.1 + E3.1 start)
```
├── S-2.1.2: Hedged-attribution validation + knowledge_boundary block, via review gate
├── S-2.1.3: Legibility derivation (base_opacity-driven this phase)
├── S-2.1.4: Recorder-first in-loop sequencing
├── S-3.1.1: Per-NPC POV projection pipeline
└── Test (leak guard): unhedged "is sad"/"is lying" rejected at any tier
```

### Sprint 4: Display & Sourced Delivery (E3.1 + E4.1)
```
├── S-3.1.2: Display in the scene POV contract (anchor interiority display-only)
├── S-4.1.1: Player input with sourced delivery (prose -> tone tag -> infer/ask)
└── Test (leak guard): no NPC excerpt carries another's interiority or hidden facts
```

### Sprint 5: NPC Moment & Inline Review (E5.1)
```
├── S-5.1.1: npc_moment branch (single present NPC; recorded, witness-tagged)
├── S-5.1.2: Inline beat_record review (first review-gate producer; in play, not standalone)
└── Phase 2 end-to-end: play narrator -> me -> one in-character NPC, with inline review
```

---

## Definition of Done (DoD)

See the [program DoD](./README.md#9-global-definition-of-done-dod). Phase-2 emphasis:

- [ ] **Isolation negative tests pass** on every assembly path and at the cheapest tier: the beat doc, other cards/edges, other characters' `true_state`, and narrator instructions never reach an NPC prompt.
- [ ] The recorder commits the **two-layer record**; a "read `surface` only" path **physically cannot** reach `true_state`; unhedged "is sad"/"is lying" is **rejected before commit** at any model tier.
- [ ] **POV projection** delivers each NPC only its witnessed, boundary-validated surface; no NPC excerpt carries another character's interiority or out-of-boundary facts.
- [ ] Player **delivery is sourced** (prose → tone tag → infer/ask), never decoded from bare text.
- [ ] The **`beat_record` producer is wired to the existing `ReviewGateService`** and reviewed **inline in play** — no new detached review page is created.
- [ ] A human can play **narrator → me → one in-character NPC** end to end. `pnpm lint` clean; UX states covered; responsive + keyboard-accessible.

---

## Success Metrics — Phase 2

| Metric | Target | Measurement |
|--------|--------|-------------|
| Single-character live play | Achieved | A human plays a scene with one in-character NPC |
| NPC prompt isolation | 0 forbidden inputs | Negative tests pass on every path and at the cheapest tier |
| true_state containment | 0 leaks | Surface-only path cannot reach true_state; no NPC excerpt carries it |
| Hedged-attribution enforcement | 100% | Every unhedged mental-state assertion rejected before commit |
| Review gate integration | Inline, no orphan | beat_record reviewed in play via existing ReviewGateService; no new page |

---

## Risk Register — Phase 2

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Forbidden data reaches an NPC prompt | Critical | Medium | Assembler is the single boundary; registry-driven blocks; explicit negative tests on every path; separate true_state table |
| A weak model emits an unhedged mental-state claim | High | Medium | Hedged-attribution is a structural validator (model-independent) + review gate as the floor; minor NPCs never record |
| Review gate rebuilt as a new detached page (repeating the orphan mistake) | High | Medium | Story convention: beat_record review integrates inline into Play.vue via the existing ReviewGateService; unified surface deferred to Phase 6 |
| Projection leaks interiority / hidden facts | Critical | Medium | witness-filter → surface-only → knowledge_boundary pipeline; structural separation of true_state; leak-guard negative tests |
| Two-call narrator + NPC turns drive cost up | Medium | Medium | Stable-block caching (basic here), model tiering; full orchestration/streaming in Phase 3 |

---

*Document Version: 2.0 · Author: Zulfikar Hidayatullah · Created: June 2026*
