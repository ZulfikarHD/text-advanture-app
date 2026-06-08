# Phase 6: Control & Observability — full authorial command
## Directed Interactive Novel Engine (DINE)

**Timeline:** ~2 Months (6–7 Sprints)
**Sprint Duration:** 1 Week
**Depends on:** Phases 1–5 (the complete engine: loop, isolation boundary, multi-character play, directed structure, psychology + the append-only audit log + all review-gate producers).
**Governing ADRs:** 0020 (prompt-block registry), 0003 (review gate + audit log), 0008 (cost/observability), 0017 (LLM client + call log), plus the shared-config touchpoints across 0004 / 0005 / 0014 / 0015.

> **Goal — give the human full command over the engine they've been playing.** Phases 1–5 deliberately kept control surfaces **inline and minimal** (inline review, queryable audit log, per-beat cost). This phase **surfaces all of it**: a single **unified review-gate** that finally repurposes the orphaned `/reviews` page now that real producers exist; **spin/regenerate** for any generated artifact; a **relationship viewer** over the audit log ("why did trust drop?"); a **cost/latency dashboard** with caps and a debug view; **prompt-block registry management**; and **shared tunable config**. After this phase the author can *review everything mid-play, regenerate anything, inspect every relationship, watch and cap spend, and tune the engine* — without touching code.

> **No new prompt blocks, no new leak guards, no new engine subsystems.** Everything here is **observability + control over existing data**. The one ordering rule that makes this phase legitimate: each surface here is built **last, on purpose**, because every producer/host it observes now exists. This is the inverse of the orphan-page mistake — the `/reviews` page is repurposed only now that `ReviewGateService` has many real callers.

> **Critical safety constraint:** the debug/observability surfaces may read `llm_calls.messages` (which can embed `true_state`). These are **debug-gated, owner-only, never agent-readable**, and never expose one character's `true_state` to another character's context. Registry management must **never** let a `leak_rule` be disabled in a way that breaks isolation.

---

## Product Backlog Overview

### Epic Summary

| Epic ID | Epic Name | Priority | Story Points | Sprints |
|---------|-----------|----------|--------------|---------|
| E1 | Unified Review-Gate Surface | Critical | 21 | 1–2 |
| E2 | Spin / Regenerate | High | 13 | 2–3 |
| E3 | Relationship Viewer | High | 18 | 3–4 |
| E4 | Cost / Latency Dashboard + Caps + Debug | Critical | 21 | 4–6 |
| E5 | Registry & Tunable-Config Management | Medium | 16 | 6 |

**Total Estimated:** ~89 Story Points

---

## EPIC E1: Unified Review-Gate Surface

> The orphaned `/reviews` page (Phase 0) finally gets producers. Every review-gate producer across the engine — `beat_record`, `card_compile`, `bible_generate`, delta + emotion proposals, `nudge_compile`, outline, goal-judge, elapsed-bucket — is consolidated into **one** surface, **without** removing the inline review affordances that already work in play.

### E1.1 — Consolidated Queue, History & Repurposed Page

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.1.1 | As an **author/player**, I want a single review surface listing all pending proposals across every producer type (grouped, filterable by story/session/type) so that I have one place to be the fidelity floor | 8 | Critical | 1 |
| S-1.1.2 | As an **author**, I want a review **history** (what was accepted/edited/rejected, by whom, when) over the append-only review records so that decisions are auditable | 5 | High | 1 |
| S-1.1.3 | As a **developer**, I want the orphaned standalone `/reviews` page repurposed as this unified surface (not a second page) while the inline in-play review affordances remain so that there is one review model, two entry points | 8 | Critical | 2 |

**Acceptance Criteria - S-1.1.1:**
```gherkin
Feature: Unified Pending Queue

Scenario: All producer types in one place
  Given pending proposals of types beat_record, card_compile, bible_generate, delta, emotion, nudge_compile, outline, goal_judge, elapsed_bucket
  When I open the unified review surface
  Then all pending items are listed, grouped by type, filterable by story/session/type
  And I can accept / edit / reject each (the same contract as the inline reviews)

Scenario: Private state is never exposed
  Given a review item whose payload could touch true_state
  Then the review view shows only the observable/surface content
  And no character's private true_state is rendered
```

**Acceptance Criteria - S-1.1.2:**
```gherkin
Feature: Review History

Scenario: Auditable decisions
  Given past review decisions
  Then I can see what was accepted/edited/rejected, by whom, and when
  And the history is read over append-only records (no rewriting of past decisions)
```

**Acceptance Criteria - S-1.1.3:**
```gherkin
Feature: Repurpose the Orphan Page

Scenario: One review model, two entry points
  Given the standalone /reviews page that rendered an empty teaching state in Phase 0
  When this epic ships
  Then /reviews becomes the unified surface backed by real producers
  And the inline in-play review (Phase 2+) still works — both call the same ReviewGateService
  And no second/duplicate review page is created
```

> **Technical Notes E1.1:**
> - **Preconditions:** all producers wired across Phases 2–5; Phase 0 `ReviewGateService` + `review_items` + the orphaned `reviews/Index.vue`.
> - **Integrates-into:** **repurpose** `resources/js/pages/reviews/Index.vue` + `ReviewController`; reuse the existing `ReviewGateService` accept/edit/reject. This is the deliberate "build the unified surface last" payoff.
> - **Leak-guards:** review views render surface/observable content only — **never** `true_state`. `own_perspective_only` on what each item exposes. ADR 0003 / 0012 §5.

---

## EPIC E2: Spin / Regenerate

> "Spin" — regenerate any generated artifact with variation, in-play, without losing the session. The player-facing escape hatch when a turn or proposal isn't right.

### E2.1 — Regenerate Generated Artifacts

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.1.1 | As a **player**, I want to "spin" (regenerate) the latest narrator prose / NPC turn so that I can get a different take without rewinding the whole beat | 8 | High | 2 |
| S-2.1.2 | As an **author**, I want to regenerate a pending proposal (a delta, a compiled artifact, a nudge) so that I can ask for another draft before accepting | 5 | High | 3 |

**Acceptance Criteria - S-2.1.1:**
```gherkin
Feature: Spin a Turn

Scenario: Regenerate the latest prose/NPC turn
  Given the latest narrator prose or NPC turn
  When I spin it
  Then a fresh generation replaces the candidate (the prior one is not yet committed to canonical history, or is superseded cleanly)
  And the session/loop state is preserved (no rewind of earlier committed beats)
  And each spin is logged to llm_calls (cost is visible)

Scenario: Spin respects isolation
  Given a spun NPC turn
  Then it re-assembles through the same Phase-2 boundary (own data + witnessed surface only)
```

**Acceptance Criteria - S-2.1.2:**
```gherkin
Feature: Spin a Proposal

Scenario: Re-draft a pending proposal
  Given a pending proposal at the review gate
  When I regenerate it
  Then a new draft replaces the pending one (still uncommitted, still reviewable)
  And the regeneration is logged; accepting commits the chosen draft
```

> **Technical Notes E2.1:**
> - **Preconditions:** Phase 1 prose call, Phase 2 NPC turn + recorder, Phase 5 producers; Phase 0 `llm_calls`.
> - **Integrates-into:** add a spin action to the **Writing/Play page host (E0.4)** + the unified review surface; reuse the existing generation services (re-invoke, don't fork new pipelines).
> - **Leak-guards:** a spun NPC turn re-runs the Phase-2 assembler boundary unchanged. ADR 0016 / 0007.

---

## EPIC E3: Relationship Viewer

> Make the append-only audit log (Phase 5) **legible**: see each edge's axes over time, its scars, and the `trigger` behind every movement — the answer to "why does this character feel that way?"

### E3.1 — Visualize the Audit Log

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.1.1 | As an **author**, I want to view a character's outgoing edges (axes, values, awareness tiers, bounds, active register, scars) for a session so that I can see the current relationship mesh at a glance | 8 | High | 3 |
| S-3.1.2 | As an **author**, I want an edge's timeline (every committed delta with its `trigger`, channel, magnitude, timestamp) so that "why did trust drop?" is answered from the audit log | 8 | High | 4 |
| S-3.1.3 | As an **author**, I want to see internal state over a session (emotions vs. baselines, mood, motivation, active masks) so that I can debug a character that "feels off" | 2 | Medium | 4 |

**Acceptance Criteria - S-3.1.1:**
```gherkin
Feature: Mesh-at-a-Glance

Scenario: View a character's edges in a session
  Given a session with a seeded + evolved mesh
  When I open the relationship viewer for a character
  Then I see its OUTGOING edges with axis values, derived awareness tiers, soft/hard bounds, active register, and any latched scars
  And owner-perspective is respected (this character's self-perceived view)
```

**Acceptance Criteria - S-3.1.2:**
```gherkin
Feature: Edge Timeline (why did trust drop?)

Scenario: Every movement is explained
  Given an edge with a history of deltas
  When I open its timeline
  Then each committed delta shows its mandatory trigger, channel (drift/rupture), magnitude, and timestamp
  And the timeline is read from the append-only audit log (no gaps, no silent deltas)

Scenario: Scars are visible
  Given a latched scar on an axis
  Then the timeline shows the latching event, the floor it set, its triggers, and whether it was overcome
```

**Acceptance Criteria - S-3.1.3:**
```gherkin
Feature: Internal-State View

Scenario: Emotions, mood, motivation, masks
  Given a session
  Then I can see active emotions vs. their baselines, the derived mood, the motivation, and active masks for a character
  And no other character's private state is shown alongside it
```

> **Technical Notes E3.1:**
> - **Preconditions:** Phase 5 edges + append-only audit log + internal state.
> - **Integrates-into:** the relationship viewer is a **panel within the Writing/Play page host (E0.4)** (alongside the branches/history panel), reached in-context during play; reads the save realm + audit log (read-only).
> - **Leak-guards:** read-only over committed data; renders one character's own perspective; never juxtaposes another character's `true_state`. Append-only log is never mutated by the viewer. ADR 0003 / 0002.

---

## EPIC E4: Cost / Latency Dashboard + Caps + Debug

> Make spend and performance visible and bounded. Per-beat cost existed in Phase 3; this aggregates it to session/story, adds **caps**, and a **debug** view of the full call log (owner-only, debug-gated).

### E4.1 — Dashboard, Caps & Debug

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.1.1 | As an **operator**, I want a cost/latency dashboard (per beat → scene → chapter → session → story, attributed by role/model/character, cost in Rupiah) so that I understand and compare spend | 8 | Critical | 4–5 |
| S-4.1.2 | As an **operator**, I want spend **caps** (per session/story budget with warn + hard stop) so that a runaway loop cannot burn unbounded cost | 8 | Critical | 5 |
| S-4.1.3 | As a **developer**, I want a debug-gated call inspector (the full `llm_calls` record incl. assembled messages, status, retries) so that I can diagnose a bad generation — owner-only, never agent-readable | 5 | High | 6 |

**Acceptance Criteria - S-4.1.1:**
```gherkin
Feature: Cost / Latency Dashboard

Scenario: Aggregated, attributed, localized
  Given logged llm_calls across a story
  When I open the dashboard
  Then I see tokens/cost/latency aggregated per beat → scene → chapter → session → story
  And attributed by role / model / character
  And cost is rendered in Rupiah and times in Asia/Jakarta (WIB)
```

**Acceptance Criteria - S-4.1.2:**
```gherkin
Feature: Spend Caps

Scenario: Warn then hard-stop
  Given a configured per-session (or per-story) spend cap
  When spend approaches the cap
  Then a warning surfaces
  And when the cap is reached, further generation is hard-stopped with a clear message (the session is not corrupted; play can resume after raising the cap)
```

**Acceptance Criteria - S-4.1.3:**
```gherkin
Feature: Debug Call Inspector

Scenario: Owner-only, debug-gated, never agent-readable
  Given a logged call
  When I open the debug inspector (debug-gated, owner-only)
  Then I can see the assembled messages, model, tokens, status, retries
  And true_state that may appear in messages is shown ONLY in this owner debug context
  And it is never fed back into any agent prompt
```

> **Technical Notes E4.1:**
> - **Preconditions:** Phase 0 `llm_calls`; Phase 3 per-beat aggregation; the cost NFR.
> - **Integrates-into:** a new dashboard surface; caps enforced in the `LlmClient`/orchestrator before dispatch; the debug inspector behind an owner+debug gate.
> - **Leak-guards:** `llm_calls.messages` is debug-gated, owner-only, never agent-readable (program NFR). Caps are an engine guardrail, not an isolation guard. ADR 0008 / 0017.

---

## EPIC E5: Registry & Tunable-Config Management

> Expose the two "knobs" the engine already runs on: the **`prompt_blocks` registry** that drives assembly, and the **shared tunable config** (severity rubric, decay caps, word-budget thresholds, inaction timer, emotion ±3 cap, bucket→magnitude mapping).

### E5.1 — Prompt-Block Registry & Shared Config

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.1.1 | As a **developer**, I want to view/manage the `prompt_blocks` registry (agent, section, order_index, compile_instruction, is_active, leak_rules) so that I can tune assembly without code — guarded so a `leak_rule` cannot be disabled into an isolation break | 8 | Medium | 6 |
| S-5.1.2 | As an **operator**, I want to manage the shared tunable config (severity rubric, decay/gap caps, word-budget thresholds, inaction threshold, emotion ±3 cap, bucket→magnitude) in one place so that engine feel is adjustable without code | 8 | Medium | 6 |

**Acceptance Criteria - S-5.1.1:**
```gherkin
Feature: Prompt-Block Registry Management

Scenario: Tune assembly from the registry
  Given the seeded prompt_blocks rows
  When I edit order_index / compile_instruction / is_active for a block
  Then the assembler reflects the change on the next assembly (registry-driven, Phase 2)

Scenario: Isolation cannot be turned off by accident
  Given a block carrying a leak_rule (e.g. awareness_fold, knowledge_boundary)
  When I attempt to disable/weaken that guard
  Then the system refuses or hard-warns that it breaks an isolation invariant
  And the negative leak tests still gate the build
```

**Acceptance Criteria - S-5.1.2:**
```gherkin
Feature: Shared Tunable Config

Scenario: One place for engine tunables
  Given the tunables referenced across ADRs (severity rubric, decay caps, word-budget thresholds, inaction timer, emotion ±3 cap, bucket→magnitude)
  When I edit one
  Then the relevant subsystem reads the new value at runtime
  And defaults remain if unset (the engine ran on sensible defaults through Phases 3–5)
```

> **Technical Notes E5.1:**
> - **Preconditions:** Phase 0 seeded `prompt_blocks`; the tunables introduced across Phases 3–5 (as defaults).
> - **Integrates-into:** a settings surface over `prompt_blocks` + a shared-config store the engine already reads; no new engine behavior.
> - **Leak-guards:** registry management **protects** the guards — it must never allow disabling a `leak_rule` into an isolation break; the leak negative-tests remain the build gate. ADR 0020 / 0007.

---

## Sprint Roadmap

### Sprint 1: Unified Queue & History (E1.1 start)
```
├── S-1.1.1: Unified pending queue (all producer types, filterable)
├── S-1.1.2: Review history over append-only records
└── Test (leak guard): no review view renders true_state
```

### Sprint 2: Repurpose /reviews & Spin a Turn (E1.1 + E2.1 start)
```
├── S-1.1.3: Repurpose the orphaned /reviews page (one model, two entry points)
├── S-2.1.1: Spin the latest prose / NPC turn (state preserved; logged)
└── Test: a spun NPC turn re-runs the Phase-2 boundary
```

### Sprint 3: Spin a Proposal & Mesh-at-a-Glance (E2.1 + E3.1 start)
```
├── S-2.1.2: Regenerate a pending proposal
├── S-3.1.1: Relationship viewer — edges at a glance (owner-perspective)
└── Test: viewer is read-only; never mutates the audit log
```

### Sprint 4: Edge Timeline, Internal State & Dashboard start (E3.1 + E4.1 start)
```
├── S-3.1.2: Edge timeline (every delta + trigger; scars visible)
├── S-3.1.3: Internal-state view
├── S-4.1.1: Cost/latency dashboard (aggregated, attributed, Rupiah/WIB) — begin
└── Test: timeline reads append-only log; no silent deltas
```

### Sprint 5: Dashboard & Caps (E4.1)
```
├── S-4.1.1: Cost/latency dashboard (finish)
├── S-4.1.2: Spend caps (warn + hard stop; session not corrupted)
└── Test: cap hard-stops generation cleanly; resumes after raise
```

### Sprint 6: Debug Inspector, Registry & Config (E4.1 + E5.1)
```
├── S-4.1.3: Debug call inspector (owner-only, debug-gated, never agent-readable)
├── S-5.1.1: Prompt-block registry management (guards cannot be disabled into a break)
├── S-5.1.2: Shared tunable-config management
└── Phase 6 end-to-end: review anything, spin anything, inspect relationships, watch+cap spend, tune the engine
```

---

## Definition of Done (DoD)

See the [program DoD](./README.md#9-global-definition-of-done-dod). Phase-6 emphasis:

- [ ] The **orphaned `/reviews` page is repurposed** into a unified review surface backed by real producers; inline in-play review still works; **one** `ReviewGateService`, two entry points — no duplicate page.
- [ ] **Spin** regenerates prose / NPC turns / proposals without rewinding committed history; spun NPC turns re-run the **Phase-2 boundary**; every spin is logged.
- [ ] The **relationship viewer** answers "why did trust drop?" from the **append-only audit log**; it is read-only and never juxtaposes another character's `true_state`.
- [ ] The **cost dashboard** aggregates + attributes spend (Rupiah, WIB); **spend caps** warn then hard-stop without corrupting the session; the **debug inspector** is owner-only/debug-gated and **never agent-readable**.
- [ ] Registry/config management is code-free **and cannot disable a `leak_rule` into an isolation break**; leak negative-tests still gate the build.
- [ ] `pnpm lint` clean; UX states covered; responsive + keyboard-accessible; append-only invariants respected.

---

## Success Metrics — Phase 6

| Metric | Target | Measurement |
|--------|--------|-------------|
| Orphan resolved | 1 review model | /reviews repurposed; ReviewGateService has many callers; no duplicate page |
| Explainability surfaced | 100% | Every edge movement traceable to a trigger in the viewer |
| Spend bounded | 0 runaway sessions | Caps warn + hard-stop; cost visible per beat→story |
| Debug safety | 0 agent leaks | true_state visible only in owner debug; never re-enters a prompt |
| Guard integrity | 0 disable-able guards | Registry management cannot break isolation; leak tests green |

---

## Risk Register — Phase 6

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| A control surface re-leaks private state (true_state in a view/debug) | Critical | Medium | Surface-only review/viewer; debug inspector owner-only + debug-gated + never agent-readable; explicit tests |
| Registry management disables a leak guard | Critical | Low | Guard-protection rule (refuse/hard-warn); leak negative-tests remain the build gate |
| Spin corrupts committed history or loop state | High | Medium | Spin replaces only the uncommitted candidate; loop state preserved; covered by tests |
| Dashboard cost figures inaccurate/misleading | Medium | Medium | Aggregate from the single llm_calls source; reconcile against provider where possible |
| Building yet another detached page (relationship/dashboard) | Medium | Low | All surfaces hang off the existing per-session/per-story hosts; the repurposed /reviews proves the pattern |

---

*Document Version: 2.0 · Author: Zulfikar Hidayatullah · Created: June 2026*
