# Phase 7: Player Experience, Spin & Review-Gate UI — Directed Interactive Novel Engine
## Directed Interactive Novel Engine (DINE)

**Timeline:** ~2 Months (8 Sprints)
**Sprint Duration:** 1 Week
**Team Size Recommendation:** 1 Full-stack Dev (+ optional QA)
**Depends on:** Phases 5–6 (the runtime — narrator loop, recorder, NPC behaviour, delta engine — must exist to be surfaced to the human)
**Governing ADRs:** 0010 (recorder mechanics / player delivery channel), 0003 (delta engine → relationship viewer audit log), 0008 (psychological nudge / break-glass "Direct a character"), 0017 (LLM orchestration / cost-latency log) + O4 (persistence-and-UI)

> Goal: surface the running engine to the human. This phase delivers the player-facing play UI, the player input + sourced delivery channel, the spin/regenerate mode (alternative takes), the shared review-gate surface (full UI for every producer type), the relationship viewer over the append-only audit log, and the cost/latency/observability surface including the break-glass "Direct a character" directive. After this phase the engine built in Phases 5–6 is actually playable, reviewable, inspectable, and budgetable by a human.

---

## Product Backlog Overview

### Epic Summary

| Epic ID | Epic Name | Priority | Story Points | Sprints |
|---------|-----------|----------|--------------|---------|
| E1 | Play UI | Critical | 11 | 46–47 |
| E2 | Player Input UX & Delivery | Critical | 8 | 47 |
| E3 | Spin / Regenerate | High | 18 | 48–49 |
| E4 | Shared Review-Gate Surface (full UI) | Critical | 26 | 50–52 |
| E5 | Relationship Viewer | High | 11 | 52 |
| E6 | Cost/Latency Dashboard & Debug | Medium | 13 | 53 |

**Total Estimated:** ~87 Story Points

---

## EPIC E1: Play UI

### E1.1 — Prose Display

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.1.1 | As a **player**, I want prose rendered in the scene's POV contract with progressive streaming as the narrator generates so that I read coherent prose and am never blocked on a frozen screen | 5 | Critical | 46 |
| S-1.1.2 | As a **player**, I want a readable scrollback/history of the playthrough so that I can re-read what happened with comfortable typography | 3 | High | 46 |

**Acceptance Criteria - S-1.1.1:**
```gherkin
Feature: Prose Rendering with POV Contract and Progressive Streaming

Scenario: Prose renders in the scene's POV contract
  Given I am playing a session and the narrator is generating a beat
  When prose for the current beat is delivered
  Then the prose is presented in the scene's POV contract (its pov_anchor)
  And no other character's private interiority appears in the rendered prose

Scenario: Prose streams progressively as it is generated
  Given a beat that requires many model calls to complete
  When the narrator begins producing prose
  Then partial prose appears as it is generated rather than only after the whole beat completes
  And an indication that generation is still in progress is shown
  And I am never blocked on a frozen screen while the beat continues

Scenario: Generation failure mid-stream is recoverable
  Given prose is streaming for the current beat
  When generation fails partway through
  Then I am told generation was interrupted
  And the prose already delivered remains readable
  And I am offered a way to retry without losing the session
```

**Acceptance Criteria - S-1.1.2:**
```gherkin
Feature: Playthrough Scrollback and History

Scenario: Re-read earlier prose in the playthrough
  Given I have played several beats in the current session
  When I move back through the playthrough history
  Then I can re-read previously delivered prose in order
  And the presentation supports comfortable extended reading (line length, spacing, scalable text)

Scenario: History reflects only committed prose
  Given a beat whose output was regenerated before being kept
  When I view the playthrough history
  Then only the prose that entered the playthrough as canonical is shown
  And discarded alternatives do not appear in the history
```

### E1.2 — Play Controls

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.2.1 | As a **player**, I want controls to advance, pause/save, and view the current beat/scene status so that I stay oriented in the session | 3 | High | 47 |

**Acceptance Criteria - S-1.2.1:**
```gherkin
Feature: Play Controls and Session Orientation

Scenario: Advance the session
  Given I am at a point where the session can continue
  When I choose to advance
  Then the next narrator turn or beat proceeds

Scenario: Pause and save the session
  Given I am mid-session
  When I pause and save
  Then my current playthrough state is persisted
  And I can resume later from where I left off

Scenario: View current beat and scene status
  Given I am in an active session
  Then I can see which beat and scene are currently active
  And I remain oriented about my progress through the chapter
```

> **Technical Notes E1.1/E1.2:**
> - **Business Logic:**
>   - The display renders the recorder `surface` in the scene's POV contract; `pov_anchor` interiority is permitted in **DISPLAY only** (the player's own anchor) and is never cross-fed to another agent.
>   - A beat is **~10+ model calls**, so prose must **stream progressively** — partial prose plus a progress indication — rather than block on a fully assembled beat.
>   - Scrollback/history shows only **committed (canonical)** prose; regenerated-but-discarded output never appears.
>   - Pause/save persists save-realm playthrough state so the session can resume from the same point; the player stays oriented via the current beat/scene status.
> - **Reference:** ADR 0009/0016/0017.

---

## EPIC E2: Player Input UX & Delivery

### E2.1 — Input + Delivery

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.1.1 | As a **player**, I want a player-moment input where I can write prose and optionally attach a tone/delivery tag, with an ambiguity confirmation when the narrator cannot resolve my delivery, so that my emotional delivery is sourced rather than guessed from bare text | 5 | Critical | 47 |

**Acceptance Criteria - S-2.1.1:**
```gherkin
Feature: Player Moment Input with Sourced Delivery

Scenario: Submit a player moment as prose
  Given it is my moment to act in the scene
  When I write my action and dialogue as prose
  Then my input is accepted as the player moment
  And my emotional delivery is taken from what I wrote, not guessed from bare text

Scenario: Attach an optional tone/delivery tag
  Given I want to signal delivery without writing it into prose
  When I submit my input together with an optional tone/delivery tag
  Then the tag is used as the source of my delivery

Scenario: Ambiguity confirmation when delivery cannot be resolved
  Given I submit bare dialogue whose delivery is genuinely ambiguous
  When the narrator cannot resolve how it was delivered
  Then I am asked to confirm the intended delivery before the beat is recorded
  And my confirmation becomes the sourced delivery rather than an inferred guess

Scenario: Delivery is never decoded from text alone
  Given any player moment is committed
  Then the committed delivery derives from prose, an explicit tone/delivery tag, or a confirmed prompt
  And it is never silently decoded from the raw text
```

### E2.2 — Inaction Prompt

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.2.1 | As a **player**, I want a clear set of choices when I'm inactive (Continue / Skip to next beat / Direct a character) so that the session can move forward on my terms | 3 | High | 47 |

**Acceptance Criteria - S-2.2.1:**
```gherkin
Feature: Player Inaction Choices

Scenario: Inaction surfaces forward-movement choices
  Given it is my moment to act and I remain inactive past the waiting threshold
  When the inaction escalation triggers
  Then I am offered a clear set of choices to move the session forward:
    | Choice             |
    | Continue           |
    | Skip to next beat  |
    | Direct a character |

Scenario: Choosing to continue resumes the session
  Given the inaction choices are presented
  When I choose "Continue"
  Then the session proceeds without forcing my hand

Scenario: Choosing to skip advances the beat
  Given the inaction choices are presented
  When I choose "Skip to next beat"
  Then the current beat is wrapped and the session advances

Scenario: Choosing to direct invokes the break-glass path
  Given the inaction choices are presented
  When I choose "Direct a character"
  Then I can issue a player-invoked directive to a character
  And the directive is recorded to the audit trail
```

> **Technical Notes E2.1/E2.2:**
> - **Business Logic:**
>   - Delivery is **SOURCED**, in order of preference: prose → optional tone/delivery tag → narrator infer with **ask-when-ambiguous**. It is **never decoded** from bare text.
>   - The **ambiguity prompt** only interrupts when the read cannot be resolved; otherwise the narrator proposes a surface and play continues.
>   - The **inaction prompt is the long-timer escalation** that hands control to the human (Continue / Skip to next beat / Direct a character).
>   - "Direct a character" is the **player-invoked break-glass directive**; it is recorded like a delta to the audit trail.
> - **Reference:** ADR 0010/0008.

---

## EPIC E3: Spin / Regenerate

> In DINE, **"spin"** means **regenerate / alternative take** — the player can re-roll a narrator or NPC output to get an alternative, browse between the generated alternatives, pick one to keep as canonical, or edit it in place before accepting.

### E3.1 — Regenerate & Choose

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.1.1 | As a **player**, I want to regenerate ("spin") a narrator or NPC output to get an alternative take so that I can steer away from a result I don't like | 5 | High | 48 |
| S-3.1.2 | As a **player**, I want to browse between generated alternatives and pick one to keep as canonical so that I control which version enters the playthrough | 5 | High | 48 |
| S-3.1.3 | As a **player**, I want to edit a generated output in place before accepting it so that I can make small fixes without a full regenerate | 3 | Medium | 49 |

**Acceptance Criteria - S-3.1.1:**
```gherkin
Feature: Regenerate (Spin) an Output

Scenario: Spin a narrator output for an alternative take
  Given a narrator output I do not want to keep
  When I spin (regenerate) it
  Then an alternative take is generated for the same moment
  And the original output is not yet committed to the playthrough

Scenario: Spin an NPC output for an alternative take
  Given an NPC output I want to steer away from
  When I spin (regenerate) it
  Then an alternative take is generated for that NPC moment
  And the alternative is generated against the same context the original had
```

**Acceptance Criteria - S-3.1.2:**
```gherkin
Feature: Browse Alternatives and Choose the Canonical Take

Scenario: Browse between generated alternatives
  Given I have generated more than one alternative for the same moment
  When I move between the alternatives
  Then I can read each generated take
  And it is clear which one is currently selected

Scenario: Keep one alternative as canonical
  Given several alternatives exist for a moment
  When I pick one to keep
  Then that take becomes the canonical output that enters the playthrough
  And the unchosen alternatives are discarded and do not affect committed state
```

**Acceptance Criteria - S-3.1.3:**
```gherkin
Feature: Edit a Generated Output Before Accepting

Scenario: Make a small edit before accepting
  Given a generated output that is almost right
  When I edit it in place and accept
  Then my edited version becomes the canonical output
  And I did not need a full regenerate to make the fix

Scenario: Edited output is still subject to the same guards
  Given I edit a generated output before accepting
  When I accept the edited version
  Then it passes the same isolation/leak guards as a generated output before it is committed
```

### E3.2 — Spin Safety & Cost

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.2.1 | As a **system**, I want spins to respect the same isolation/leak guards, discarded alternatives to never pollute committed state, and each spin to be logged and counted toward cost so that regeneration is safe and accountable | 5 | High | 49 |

**Acceptance Criteria - S-3.2.1:**
```gherkin
Feature: Spin Safety, Isolation, and Cost Accounting

Scenario: Discarded alternatives never pollute committed state
  Given I generate several alternatives and keep only one
  When the chosen take is committed
  Then only the chosen take produces deltas, records, and downstream state
  And the discarded alternatives leave no trace in committed playthrough state

Scenario: Spins respect the same isolation and leak guards
  Given any regenerated narrator or NPC output
  When it is produced
  Then the same isolation/leak guards that apply to a first-pass output apply identically
  And a spin cannot surface data that a first-pass output could not

Scenario: Every spin is logged and counted toward cost
  Given I spin an output any number of times
  Then each spin is recorded as a model call in the call log
  And each spin counts toward the session's cost and latency totals
```

> **Technical Notes E3.1/E3.2:**
> - **Business Logic:**
>   - "Spin" = **regenerate / alternative take**: re-roll a narrator or NPC output, **browse between alternatives**, **pick one to keep as canonical**, or **edit in place** before accepting.
>   - **Only the chosen alternative is committed** — deltas, beat records, and all downstream state derive from the **canonical pick**; discarded alternatives **never pollute committed state**.
>   - **Isolation/leak guards** (awareness-fold, nudge-compile, POV projection) apply **identically** to regenerated and edited output before commit.
>   - **Every spin is a logged call**, counted toward session cost/latency totals — regeneration is accountable.
> - **Reference:** ADR 0007/0009/0010/0017.

---

## EPIC E4: Shared Review-Gate Surface — full UI

### E4.1 — Unified Review Surface

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.1.1 | As an **author/player**, I want one review surface listing all pending items across producer types (delta, emotion_delta, nudge_compile, beat_record, card_compile, bible_generate, outline_compile) with accept/edit/reject so that I am the single fidelity floor for everything the engine generates | 8 | Critical | 50 |
| S-4.1.2 | As an **author/player**, I want each item type rendered with the right context so that I can decide quickly and correctly | 8 | Critical | 51 |
| S-4.1.3 | As an **author/player**, I want inline edit of a payload before commit, safe batch-accept, and reviewer + timestamp recorded so that review is efficient and auditable | 5 | High | 51 |

**Acceptance Criteria - S-4.1.1:**
```gherkin
Feature: Unified Pending-Review Surface

Scenario: One surface lists all pending items across producer types
  Given pending items exist from multiple producers
  When I open the review surface
  Then I see one combined list of pending items spanning the producer types:
    | Producer type   |
    | delta           |
    | emotion_delta   |
    | nudge_compile   |
    | beat_record     |
    | card_compile    |
    | bible_generate  |
    | outline_compile |
  And for each item I can accept, edit, or reject it

Scenario: I am the single fidelity floor for safety-critical output
  Given any item the engine generated
  When it is pending
  Then a safety-critical item does not commit until I decide on it
  And nothing the engine generates bypasses my decision for safety-critical producers

Scenario: Authoring-time compiles appear without a session
  Given an authoring-time compile enqueues a proposal with no save context
  When I open the review surface
  Then the item is listed and reviewable just like a runtime item
```

**Acceptance Criteria - S-4.1.2:**
```gherkin
Feature: Per-Producer-Type Review Context

Scenario: A delta item shows its relationship context
  Given a pending delta item
  When I view it
  Then I see the edge, the axis, the mandatory trigger, and the before→after values

Scenario: A beat record shows its surface and hedged attribution
  Given a pending beat_record item
  When I view it
  Then I see the observable surface and the hedged-attribution result
  And no character's private true-state is exposed in that view

Scenario: A nudge compile shows the bounded internal impulse
  Given a pending nudge_compile item
  When I view it
  Then I see the bounded internal-impulse text that would reach the character
  And the authorial/omniscient intent behind it is not leaked into the impulse

Scenario: Each producer type renders the context relevant to it
  Given pending items of different producer types
  When I review each
  Then each is rendered with the context appropriate to its type so a correct decision can be made quickly
```

**Acceptance Criteria - S-4.1.3:**
```gherkin
Feature: Efficient and Auditable Review

Scenario: Inline edit a payload before commit
  Given a pending item whose payload I want to adjust
  When I edit the payload inline and commit
  Then the edited content is committed and the item is marked as edited
  And the edit is captured separately from the original proposed payload

Scenario: Safe batch-accept
  Given several pending items I am confident about
  When I batch-accept them
  Then each accepted item commits
  And safety-critical items that should not be auto-accepted are not swept in unsafely

Scenario: Reviewer and timestamp are recorded
  Given I decide on any item
  Then the decision records who reviewed it and when
  And the record is available for audit
```

### E4.2 — Gate Modes

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.2.1 | As an **author**, I want to choose review intensity (review-everything vs auto-accept low-confidence) so that play isn't constantly interrupted while safety-critical items still surface | 5 | Medium | 52 |

**Acceptance Criteria - S-4.2.1:**
```gherkin
Feature: Review Intensity Modes

Scenario: Review-everything mode
  Given I choose the review-everything intensity
  When any producer enqueues an item
  Then every item waits for my explicit decision before committing

Scenario: Auto-accept low-confidence while protecting safety-critical producers
  Given I choose to auto-accept low-confidence items
  When items are enqueued
  Then non-safety-critical items may auto-accept to avoid constant interruption
  And safety-critical producers (beat_record, nudge_compile, card_compile) still surface for my explicit decision and are never auto-accepted away
```

> **Technical Notes E4.1/E4.2:**
> - **Business Logic:**
>   - **One shared queue**, polymorphic by `producer_type`: delta, emotion_delta, nudge_compile, beat_record, card_compile, bible_generate, outline_compile.
>   - States: **pending → accepted | edited | rejected**; edits are **captured separately** from the original payload; **reviewer + timestamp** recorded on every decision.
>   - Each item renders with **type-appropriate context** — a delta shows edge/axis/trigger/before→after; a beat_record shows the surface + hedged-attribution result; a nudge_compile shows the bounded internal-impulse text — without exposing any character's private true-state.
>   - **Batch-accept must be safe**: safety-critical producers (`beat_record`, `nudge_compile`, `card_compile`) **should not be auto-accepted away**.
>   - Authoring-time compiles enqueue with **no session** (null save context) and are reviewable on the same surface.
>   - This surface is the **human fidelity floor** for all three leak guards.
> - **Reference:** ADR 0003/0012 §5.

---

## EPIC E5: Relationship Viewer

### E5.1 — Inspect Relationships

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.1.1 | As a **player/author**, I want to view a character's relationships (current axis values, awareness, register, scars) and the per-axis history from the append-only delta log with the mandatory trigger for each change so that I can understand and debug why a character feels the way they do | 8 | High | 52 |
| S-5.1.2 | As an **author**, I want break-glass directives surfaced alongside deltas in the history so that authorial overrides are transparent and auditable | 3 | Medium | 52 |

**Acceptance Criteria - S-5.1.1:**
```gherkin
Feature: Relationship Viewer

Scenario: Inspect a character's current relationship state
  Given a character with relationships in the playthrough
  When I open the relationship viewer for that character
  Then I see the current axis values, awareness, register, and any scars on each edge

Scenario: Inspect the per-axis history with mandatory triggers
  Given an edge with a history of changes
  When I view the per-axis history
  Then each change is shown from the append-only delta log with its mandatory human-readable trigger
  And no change appears without a trigger explaining why it happened

Scenario: Understand why a character feels the way they do
  Given a character that "feels off"
  When I trace its axis history
  Then I can follow the sequence of triggered changes that produced the current state
```

**Acceptance Criteria - S-5.1.2:**
```gherkin
Feature: Break-Glass Directives in History

Scenario: Authorial overrides appear alongside deltas
  Given a break-glass directive was issued against a character
  When I view that character's change history
  Then the directive is surfaced alongside ordinary deltas
  And it is recorded like a delta with its trigger, so the override is transparent and auditable
```

> **Technical Notes E5.1:**
> - **Business Logic:**
>   - The viewer **reads the append-only `axis_deltas` audit log**; current materialized values (axis values, awareness, register, scars) are shown alongside the per-axis change history.
>   - Every committed change carries a **mandatory human-readable trigger** — no silent deltas.
>   - A **break-glass directive is recorded like a delta** and surfaced in the same history, keeping authorial overrides transparent and auditable.
>   - The viewer is **read-only** over the audit history; it never rewrites it.
> - **Reference:** ADR 0003/0008.

---

## EPIC E6: Cost/Latency Dashboard & Debug

### E6.1 — Observability

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-6.1.1 | As a **user**, I want a cost/latency dashboard from the call log (per session/story/beat: call count, cost rendered in Rupiah, latency, status) so that I can monitor and control spend | 5 | Medium | 53 |
| S-6.1.2 | As an **author**, I want to debug a beat that "feels off" by inspecting the calls that produced it (debug-gated, never exposing another character's private state inappropriately) so that I can diagnose quality issues | 5 | Medium | 53 |

**Acceptance Criteria - S-6.1.1:**
```gherkin
Feature: Cost and Latency Dashboard

Scenario: View spend and latency from the call log
  Given a session with logged model calls
  When I open the cost/latency dashboard
  Then I can see, per session, story, and beat: call count, cost, latency, and status
  And cost is rendered in Rupiah for display while stored as the provider-reported value

Scenario: Monitor spend across the playthrough
  Given calls accumulate as I play
  Then the dashboard reflects the running totals so I can monitor and control spend
```

**Acceptance Criteria - S-6.1.2:**
```gherkin
Feature: Beat Debugging from the Call Log

Scenario: Inspect the calls that produced a beat
  Given a beat that "feels off" and debugging is enabled
  When I inspect that beat
  Then I can see the model calls that produced it with their role, model, cost, latency, and status
  And I can diagnose where its quality issue originated

Scenario: Debug view does not leak another character's private state
  Given I inspect a beat's calls
  When debugging is enabled
  Then full message bodies are available only behind the debug gate
  And another character's private state is not exposed inappropriately
```

### E6.2 — Spend Caps

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-6.2.1 | As a **user**, I want to set a spend/latency budget alert or cap per session so that runaway cost is prevented | 3 | Low | 53 |

**Acceptance Criteria - S-6.2.1:**
```gherkin
Feature: Spend and Latency Budget per Session

Scenario: Set a spend or latency budget alert
  Given I want to control runaway cost
  When I set a spend or latency budget for a session
  Then I am alerted when the session approaches or crosses the budget

Scenario: Cap enforced to prevent runaway cost
  Given a session with a configured spend cap
  When the cap is reached
  Then further spend is prevented or held until I intervene
  And runaway cost is prevented
```

> **Technical Notes E6.1/E6.2:**
> - **Business Logic:**
>   - The dashboard reads the **append-only `llm_calls` log**; metrics aggregate **per session/story/beat** (call count, cost, latency, status).
>   - **Cost is stored as provider-reported** and **rendered in Rupiah** for display only — not a stored second column.
>   - `llm_calls` is **save-realm-sensitive and never an agent-readable source**; **full message bodies are debug-gated** (otherwise a summary + token counts).
>   - Beat debugging must **not expose another character's private true-state** inappropriately.
>   - Spend/latency budgets raise **alerts** and may **cap** further spend per session to prevent runaway cost.
> - **Reference:** ADR 0017.

---

## Sprint Roadmap

### Sprint 46: Prose Display (E1.1)
```
Sprint 46 (Week 46):
├── S-1.1.1: Prose rendered in POV contract with progressive streaming
├── S-1.1.2: Readable playthrough scrollback/history
└── Test: streaming never blocks; history shows only committed prose
```

### Sprint 47: Play Controls & Player Input/Delivery (E1.2 + E2)
```
Sprint 47 (Week 47):
├── S-1.2.1: Advance / pause-save / beat-scene status controls
├── S-2.1.1: Player-moment input with sourced delivery + ambiguity confirmation
├── S-2.2.1: Inaction prompt (Continue / Skip / Direct a character)
└── Test: delivery sourced (prose/tag/confirm), never decoded from bare text
```

### Sprint 48: Spin — Regenerate & Choose (E3.1)
```
Sprint 48 (Week 48):
├── S-3.1.1: Spin (regenerate) a narrator or NPC output
├── S-3.1.2: Browse alternatives and pick one as canonical
└── Test: only the chosen alternative enters the playthrough
```

### Sprint 49: Spin — Edit & Safety/Cost (E3.1 + E3.2)
```
Sprint 49 (Week 49):
├── S-3.1.3: Edit a generated output in place before accepting
├── S-3.2.1: Spin isolation/leak guards + discarded never committed + logged/counted
└── Test: discarded alternatives leave no trace; each spin logged toward cost
```

### Sprint 50: Unified Review Surface (E4.1)
```
Sprint 50 (Week 50):
├── S-4.1.1: One review surface across all producer types with accept/edit/reject
└── Test: authoring-time compiles (no session) reviewable on the same surface
```

### Sprint 51: Per-Type Rendering & Efficient Review (E4.1)
```
Sprint 51 (Week 51):
├── S-4.1.2: Per-producer-type review context rendering
├── S-4.1.3: Inline edit + safe batch-accept + reviewer/timestamp
└── Test: beat_record / nudge_compile views never expose private true-state
```

### Sprint 52: Gate Modes & Relationship Viewer (E4.2 + E5)
```
Sprint 52 (Week 52):
├── S-4.2.1: Review-intensity modes (everything vs auto-accept low-confidence)
├── S-5.1.1: Relationship viewer (current state + per-axis triggered history)
├── S-5.1.2: Break-glass directives surfaced alongside deltas
└── Test: safety-critical producers never auto-accepted away
```

### Sprint 53: Observability, Debug & Spend Caps (E6)
```
Sprint 53 (Week 53):
├── S-6.1.1: Cost/latency dashboard (per session/story/beat, Rupiah display)
├── S-6.1.2: Debug a beat from its calls (debug-gated, no inappropriate leak)
├── S-6.2.1: Per-session spend/latency budget alert or cap
└── Phase 7 regression + accessibility pass on the play UI
```

---

## Definition of Done (DoD)

See the [program DoD](./README.md#7-global-definition-of-done-dod). Phase-7 emphasis:

- [ ] Spin (regenerate) output respects the same **isolation/leak guards** as a first-pass output — with explicit negative tests asserting a spin cannot surface forbidden data.
- [ ] **Discarded alternatives are never committed**: only the canonical pick produces deltas/records/downstream state; negative test confirms no trace of unchosen takes.
- [ ] The review surface **covers all producer types** (delta, emotion_delta, nudge_compile, beat_record, card_compile, bible_generate, outline_compile) with accept/edit/reject; **safety-critical producers are never auto-accepted away**; edits captured separately; reviewer + timestamp recorded.
- [ ] Per-type review rendering and the relationship viewer **never expose another character's private true-state**.
- [ ] Relationship viewer reads the append-only audit log; **every change carries its mandatory trigger**; break-glass surfaced like a delta.
- [ ] Play UI **streams progressively and never blocks** on a frozen screen; cost is rendered in Rupiah; `llm_calls` stays debug-gated and never agent-readable.
- [ ] **Accessibility of the play UI**: keyboard-navigable controls, sufficient contrast in both themes, readable prose (line length, font scaling), responsive on desktop + tablet.

---

## Success Metrics — Phase 7

| Metric | Target | Measurement |
|--------|--------|-------------|
| Time-to-first-prose (streaming) | First prose chunk visible < 3s | Latency from beat start to first rendered chunk |
| Frozen-screen incidents during a beat | 0 | Player never blocked while a ~10+ call beat runs |
| Spin leak safety | 0 leaks across regenerations | Negative tests: a spin surfaces nothing a first pass couldn't |
| Discarded-alternative pollution | 0 committed traces | Only the canonical pick produces deltas/records |
| Review coverage | 100% of producer types | All seven producer types reviewable with accept/edit/reject |
| Auto-accept safety | 0 safety-critical auto-accepts | beat_record/nudge_compile/card_compile always surfaced |
| Trigger completeness in viewer | 100% of changes carry a trigger | Audit-log read shows no triggerless delta |
| Cost visibility & control | Per-beat cost visible; cap honored | Dashboard totals match call log; cap halts/holds spend |

---

## Risk Register — Phase 7

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| A spin (regeneration) leaks data a first-pass output could not | Critical | Medium | Route regenerations through the identical assembler/leak guards; explicit negative tests per spin path |
| A discarded alternative pollutes committed state (deltas/records from an unchosen take) | High | Medium | Commit only the canonical pick; derive all downstream from it; test for zero traces of unchosen takes |
| Review surface auto-accepts a safety-critical item (beat_record/nudge_compile/card_compile) | Critical | Medium | Gate modes hard-exclude safety-critical producers from auto-accept; batch-accept guarded; tested |
| Per-type review or relationship viewer exposes another character's private true-state | Critical | Medium | Render from surface/hedged-attribution only; structural separation of true_state; negative tests |
| Streaming a ~10+ call beat blocks or freezes the UI | High | High | Progressive streaming with partial prose + progress indication; recoverable mid-stream failure |
| Runaway cost during heavy spinning / long beats | High | High | Each spin logged + counted; per-session budget alerts/caps; cost visible per session/story/beat |
| Call-log debug surface leaks full prompts (embedding true_state) | Critical | Low | `llm_calls` debug-gated and never agent-readable; summaries + token counts by default |
| Audit-log change without a human-readable trigger reaches the viewer | Medium | Low | Mandatory trigger enforced at commit; viewer asserts every change carries one |
| Play UI not accessible (keyboard/contrast/readability) | Medium | Medium | Accessibility pass in Sprint 53; keyboard nav, contrast, scalable prose, responsive layout |

---

*Document Version: 1.0 · Author: Zulfikar Hidayatullah · Created: June 2026*
