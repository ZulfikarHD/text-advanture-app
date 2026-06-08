# Phase 3: Multi-Character Play — full SillyTavern parity
## Directed Interactive Novel Engine (DINE)

**Timeline:** ~1 Month (4 Sprints)
**Sprint Duration:** 1 Week
**Depends on:** Phase 2 (assembler/isolation boundary, recorder two-layer, POV projection, single `npc_moment`, inline review) + Phase 1 (the Writing/Play page host E0.4 — all multi-character streaming mounts here).
**Governing ADRs:** 0016 (turn loop + in-loop sequencing), 0007 (assembler caching), 0008 (cost/observability — per-beat accounting), 0011 (interaction queue / inaction).

> **Goal — a scene full of people.** Phase 2 made one NPC live; this phase makes **many** live at once. It adds the **interaction queue** (who acts next, and why), the **inaction timer** (a present-but-unaddressed character eventually reacts so the scene breathes), and the **compile → act orchestration** that runs several NPC turns per beat efficiently: deterministic sequencing, stable-block caching across characters and turns, **progressive streaming** so the human watches the scene unfold, and **per-beat cost accounting**. After this phase the app reaches **full SillyTavern-style multi-character roleplay** — every character still bound by the Phase-2 isolation boundary.

> **No new prompt blocks or leak guards this phase.** The Phase-2 assembler, recorder, projection, and three guards (`knowledge_boundary` · `hedged_attribution` · `own_perspective_only`) apply unchanged to **every** character in the queue. This phase is about **orchestration and scale**, not new context surfaces.
> **Deferred:** beat intent / `NUDGE` / word-budget direction (Phase 4); relationship-aware salience and register/awareness depth (Phase 5) — this phase's salience uses addressing + presence + recency only.

---

## Product Backlog Overview

### Epic Summary

| Epic ID | Epic Name | Priority | Story Points | Sprints |
|---------|-----------|----------|--------------|---------|
| E1 | Interaction Queue & Inaction Timer | Critical | 21 | 1–2 |
| E2 | Compile → Act Orchestration | Critical | 21 | 2–4 |

**Total Estimated:** ~42 Story Points

---

## EPIC E1: Interaction Queue & Inaction Timer

> Decides **who acts next** among the present characters and keeps an un-addressed character from going silent forever. This is the conductor for multi-character scenes — owned by the Phase-1 state machine, not a new module.

### E1.1 — Turn-Taking

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.1.1 | As a **system**, I want an interaction queue that orders present characters' turns by salience (directly addressed → recently engaged → present bystander) so that conversation flows to whoever it makes sense to hear from next | 8 | Critical | 1 |
| S-1.1.2 | As a **player**, I want to address a specific character or the group (and to act without addressing anyone) so that I steer who responds | 5 | High | 1 |
| S-1.1.3 | As a **system**, I want the queue to resolve multiple NPC turns inside one beat then hand back to the player/narrator so that a single exchange can involve several characters | 5 | Critical | 2 |

**Acceptance Criteria - S-1.1.1:**
```gherkin
Feature: Interaction Queue (salience ordering)

Scenario: The directly-addressed character acts first
  Given Luna, Mara, and the player are present
  And the player addresses Luna
  When the queue computes order
  Then Luna is first; others are ordered by recent engagement then presence
  And salience this phase = addressing + recency + presence only (relationship-weighted salience arrives in Phase 5)

Scenario: Each ordered turn still passes through the isolation boundary
  Given the queue schedules Luna then Mara
  Then each turn is assembled per the Phase-2 boundary from that character's own data only
```

**Acceptance Criteria - S-1.1.2:**
```gherkin
Feature: Addressing

Scenario: Address one character
  Given several present characters
  When I direct my input at Mara
  Then Mara is prioritized to respond

Scenario: Address the group / address no one
  When I speak to the group or act without addressing anyone
  Then the queue selects responders by salience rather than a single forced responder
```

**Acceptance Criteria - S-1.1.3:**
```gherkin
Feature: Multiple NPC Turns Per Beat

Scenario: A chain of NPC turns inside one beat
  Given the player provokes a group reaction
  When the beat resolves
  Then the queue runs the relevant NPCs' turns in salience order within the same beat
  And after the chain, control returns to the player moment or the narrator per the handoff
  And every turn is recorded + witness-tagged via the Phase-2 recorder
```

> **Technical Notes E1.1:**
> - **Preconditions:** Phase 2 `npc_moment` branch, assembler, recorder.
> - **Integrates-into:** the Phase-1 `SessionStateMachine` gains an `InteractionQueue` it consults on `npc_moment`; the existing single-NPC path becomes the one-element queue. No separate orchestrator module — the state machine stays the conductor.
> - **Leak-guards:** none new; each queued turn reuses the Phase-2 boundary verbatim. Salience inputs are presence/addressing/recency only — relationship edges are not consulted (they arrive in Phase 5). ADR 0011 / 0016.

---

### E1.2 — Inaction Timer

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.2.1 | As a **system**, I want an inaction timer so that a present, un-addressed character reacts or interjects after enough turns/time so that the scene breathes and silent characters do not vanish | 3 | High | 2 |

**Acceptance Criteria - S-1.2.1:**
```gherkin
Feature: Inaction Timer

Scenario: A silent present character eventually reacts
  Given Mara has been present but un-addressed for several exchanges
  When her inaction threshold is reached
  Then the queue may insert a reaction/interjection turn for her
  And the interjection is recorded + witness-tagged like any other turn

Scenario: The timer never breaks the boundary or the POV
  Given an inaction-triggered turn
  Then it is assembled from Mara's own data + her witnessed surface only
  And it respects the scene POV contract
```

> **Technical Notes E1.2:**
> - **Preconditions:** S-1.1.1 (queue).
> - **Integrates-into:** the `InteractionQueue` tracks per-character turns/time-since-action and may enqueue a low-priority reaction. Threshold is a tunable (the shared tunable-config surface arrives in Phase 6; until then it is a sensible default constant).
> - **Leak-guards:** none new; inaction turns pass the Phase-2 boundary unchanged. ADR 0011.

---

## EPIC E2: Compile → Act Orchestration

> Runs many compile→act turns per beat **efficiently and legibly**: deterministic sequencing, caching, streaming, and cost accounting — the engineering that makes a crowded scene feel live without runaway latency or cost.

### E2.1 — Sequencing, Caching, Streaming & Cost

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.1.1 | As a **system**, I want deterministic in-beat sequencing (queue order → each character's compile→act → recorder, in a fixed order) so that multi-character beats are reproducible and debuggable | 5 | Critical | 2 |
| S-2.1.2 | As a **system**, I want stable-block caching (cache the unchanged `IDENTITY`/`SCENE_RULES` system blocks across a character's turns and across the beat; recompute only the volatile `SCENE_EXCERPT`) so that orchestrating many turns stays affordable | 8 | Critical | 3 |
| S-2.1.3 | As a **player**, I want progressive streaming (each character's output streams as it generates, in order) so that a crowded scene feels live instead of stalling on a long batch | 5 | High | 3 |
| S-2.1.4 | As an **author/operator**, I want per-beat cost accounting (tokens + cost aggregated per beat across every compile/act/recorder call, attributed by role/character) so that the cost of a busy scene is visible | 3 | High | 4 |

**Acceptance Criteria - S-2.1.1:**
```gherkin
Feature: Deterministic In-Beat Sequencing

Scenario: A multi-character beat runs in a fixed, reproducible order
  Given the queue ordered Luna then Mara
  When the beat orchestrates
  Then it runs Luna(compile -> act) then Mara(compile -> act) then the recorder, in that fixed order
  And given identical inputs the sequence and recorded result are reproducible

Scenario: A failed character turn is isolated
  Given Mara's act call fails
  Then Luna's committed turn is unaffected, the failure is surfaced, and the beat can be retried/resumed without corrupting the record
```

**Acceptance Criteria - S-2.1.2:**
```gherkin
Feature: Stable-Block Caching

Scenario: System blocks are reused; only the excerpt recomputes
  Given Luna acts twice in a beat
  Then her [IDENTITY] / [SCENE RULES] system blocks are assembled once and reused
  And only the volatile [SCENE EXCERPT] is recomputed per turn

Scenario: Caching never widens the boundary
  Given cached blocks are reused
  Then a cached block is reused only for the same character whose data produced it
  And cache reuse never causes one character's block to appear in another's prompt
```

**Acceptance Criteria - S-2.1.3:**
```gherkin
Feature: Progressive Streaming

Scenario: Output streams in queue order
  Given a beat with several NPC turns
  When orchestration runs
  Then each character's prose streams to the Writing/Play page (E0.4 host) as it generates, in queue order
  And the human is not blocked on the whole batch before seeing anything

Scenario: Streaming degrades gracefully
  Given a stream interrupts mid-turn
  Then prior streamed turns remain readable and the interrupted turn is retryable
```

**Acceptance Criteria - S-2.1.4:**
```gherkin
Feature: Per-Beat Cost Accounting

Scenario: Aggregate every call in the beat
  Given a beat ran prose + several compile/act pairs + the recorder
  When the beat closes
  Then tokens and cost are aggregated per beat from llm_calls
  And attributed by role/character (narrator, npc_major/minor per character, recorder)

Scenario: Visible to the operator
  Given a completed beat
  Then its aggregated token/cost figure is queryable for display
  (the full cost/latency dashboard + caps arrive in Phase 6)
```

> **Technical Notes E2.1:**
> - **Preconditions:** E1 queue; Phase 2 compile→act + recorder; Phase 0 `llm_calls` table + `LlmClient` streaming.
> - **Integrates-into:** an `NpcTurnOrchestrator` invoked by the state machine on `npc_moment`; it drives the Phase-2 `NpcTurnService` per queued character, reuses the Phase-2 assembler's stable system blocks, streams into the **Writing/Play page host (E0.4)**, and reads `llm_calls` for aggregation.
> - **Leak-guards:** caching is **per-character keyed** — a reuse test proves a cached block never crosses into another character's prompt (the boundary must survive the optimization). Cost accounting is the data source the Phase-6 dashboard + caps consume. ADR 0007 §caching / 0008 / 0016.

---

## Sprint Roadmap

### Sprint 1: The Queue (E1.1 start)
```
├── S-1.1.1: Interaction queue (salience: addressing + recency + presence)
├── S-1.1.2: Addressing (one character / group / no one)
└── Test (leak guard): each queued turn still assembled from own data only
```

### Sprint 2: Multi-Turn Beats, Inaction & Sequencing (E1 + E2.1 start)
```
├── S-1.1.3: Multiple NPC turns per beat, then hand back
├── S-1.2.1: Inaction timer (silent present character reacts)
├── S-2.1.1: Deterministic in-beat sequencing (reproducible; failures isolated)
└── Test: a failed character turn does not corrupt the beat record
```

### Sprint 3: Caching & Streaming (E2.1)
```
├── S-2.1.2: Stable-block caching (system blocks reused; excerpt recomputed)
├── S-2.1.3: Progressive streaming in queue order
└── Test (leak guard): cached block never crosses into another character's prompt
```

### Sprint 4: Cost Accounting & Hardening (E2.1)
```
├── S-2.1.4: Per-beat cost accounting (aggregated, attributed by role/character)
└── Phase 3 end-to-end: a crowded scene plays live, streamed, reproducible, cost-visible
```

---

## Definition of Done (DoD)

See the [program DoD](./README.md#9-global-definition-of-done-dod). Phase-3 emphasis:

- [ ] A scene with **multiple present characters** plays live: the queue orders turns by salience, addressing steers responders, and silent characters react via the inaction timer.
- [ ] **Every queued turn passes the Phase-2 isolation boundary unchanged**; the caching reuse test proves a cached block **never** crosses into another character's prompt.
- [ ] Multi-character beats are **deterministic and reproducible**; a failed character turn is **isolated** and retryable without corrupting the record.
- [ ] Output **streams in queue order**; per-beat **cost is aggregated and attributed** by role/character.
- [ ] No new detached UI: streaming + addressing live in the **Writing/Play page host (E0.4)**. `pnpm lint` clean; UX states covered; responsive + keyboard-accessible.

---

## Success Metrics — Phase 3

| Metric | Target | Measurement |
|--------|--------|-------------|
| Multi-character live play | Achieved | A crowded scene plays end to end (SillyTavern parity) |
| Boundary survives optimization | 0 cross-character leaks | Caching reuse test: cached block never crosses prompts |
| Sequencing determinism | 100% reproducible | Identical inputs → identical sequence + record |
| Failure isolation | 100% | A failed turn never corrupts a committed beat |
| Cost visibility | Per-beat | Tokens/cost aggregated + attributed by role/character |

---

## Risk Register — Phase 3

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Caching leaks a block across characters | Critical | Medium | Per-character cache keys + a leak-guard reuse test that fails the build on cross-talk |
| Crowded-scene latency/cost blows up | High | Medium | Stable-block caching + model tiering + streaming; per-beat cost accounting to see it early |
| Queue feels unnatural (wrong character speaks) | Medium | Medium | Salience = addressing + recency + presence now; relationship-weighted salience tuned in Phase 5 |
| A failed mid-beat turn corrupts the record | High | Low | Deterministic sequencing + per-turn isolation + retry/resume without partial commits |
| Building a separate multi-char UI | Medium | Low | Streaming + addressing integrate into the Writing/Play page host (E0.4), not a new area |

---

*Document Version: 2.0 · Author: Zulfikar Hidayatullah · Created: June 2026*
