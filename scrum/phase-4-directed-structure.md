# Phase 4: Directed Structure — the Novel Crafter layer
## Directed Interactive Novel Engine (DINE)

**Timeline:** ~1.5 Months (5–6 Sprints)
**Sprint Duration:** 1 Week
**Depends on:** Phase 3 (multi-character play, orchestration), Phase 2 (assembler/recorder/projection), Phase 1 (loop spine + the play front door E0; player-facing prompts surface in the **Writing/Play page host E0.4**).
**Governing ADRs:** 0019 (outline compilation), 0015 (beat document + `BEAT_DONE` + boundaries), 0008 (psychological nudge + escalation ladder), 0016 (loop sequencing of clock + boundaries), 0020 (`NUDGE` / `BEAT` / `DIRECTOR_STATE` blocks).

> **Goal — turn free roleplay into a *directed* novel.** Phases 1–3 deliver live multi-character play; this phase adds the **authorial direction** that makes it a *Directed* Interactive Novel: a real **outline → chapter → scene → beat** structure (AI-compiled or hand-authored), the **beat document** (omniscient `intent`, `goal`, `word_budget`, `nudge_target`), the **nudge** (leak-checked authorial pressure delivered to an NPC), and the **word-budget pacing clock** with `BEAT_DONE`, the escalation ceiling/break-glass, and the **boundary events** (`SCENE_DONE`/`CHAPTER_DONE`) that drive batched subsystems. After this phase the human directs *where the story goes* while characters still play themselves — the Novel Crafter half of the vision sitting on top of the SillyTavern half.

> **Blocks lit this phase:** narrator `BEAT` is **enriched** from goal-only (Phase 1) to full `intent`/`goal`/`word_budget`; narrator `DIRECTOR_STATE` is **new**; NPC `NUDGE` is **new**. **Guard activated:** `omniscient_authoring` (the nudge-derivation leak-check — the raw omniscient `intent` never crosses to an NPC).
> **`MESH_AWARENESS` is deliberately NOT here.** It reads the full relationship mesh (edges, ADR 0002), which does not exist until Phase 5 — so per the integration-point ordering rule it lights up **in Phase 5** with the mesh. Lighting it now would assemble an empty block over nonexistent data — exactly the orphan/ordering mistake this re-slice exists to avoid.
> **Also deferred to Phase 5:** the *batched drift* and *time decay* that `SCENE_DONE`/`CHAPTER_DONE` will trigger (they need edges + internal state); the *register-gating* of the nudge. This phase wires the boundary-event **slots** and fires the parts it has producers for (scene summary, elapsed bucket, chapter log, card swap).

---

## Product Backlog Overview

### Epic Summary

| Epic ID | Epic Name | Priority | Story Points | Sprints |
|---------|-----------|----------|--------------|---------|
| E1 | Outline & Structure Authoring | Critical | 21 | 1–2 |
| E2 | The Nudge (authorial pressure) | Critical | 21 | 3–4 |
| E3 | Pacing Clock & Boundary Events | Critical | 26 | 4–6 |

**Total Estimated:** ~68 Story Points

---

## EPIC E1: Outline & Structure Authoring

> The human authors *route, not structure* (ADR 0008): a chapter outline, scenes, and beats exist, but drifting from the path is fine as long as the beat's **goal** holds. This epic builds the full `story → chapters → scenes → beats` authoring — AI-compiled or by hand — enriching the Phase-1 minimal beat.

### E1.1 — Outline Compilation & Manual Path

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.1.1 | As an **author**, I want to compile a premise/outline into a `chapters → scenes → beats` draft (AI-assisted) reviewed before it commits so that I get structure fast without losing control | 8 | Critical | 1 |
| S-1.1.2 | As an **author**, I want to author/edit the full hierarchy by hand (and edit any compiled output) so that I am never forced through the AI path | 5 | High | 1 |

**Acceptance Criteria - S-1.1.1:**
```gherkin
Feature: Outline Compilation

Scenario: Compile a premise into a structured draft
  Given a story with a premise/outline
  When I run outline compilation
  Then it produces a draft hierarchy of chapters -> scenes -> beats
  And each beat carries at least a goal; scenes carry pov/tone/present-characters
  And the draft is enqueued on the review gate before it commits (accept / edit / reject)

Scenario: Compilation never auto-commits
  Given a compiled draft
  Then nothing enters the authoring tables until I accept (or edit-then-accept)
```

**Acceptance Criteria - S-1.1.2:**
```gherkin
Feature: Manual Structure Authoring

Scenario: Hand-author the full hierarchy
  Given a story
  When I create/edit chapters, scenes, and beats by hand
  Then the hierarchy commits scoped to the story with no LLM call required

Scenario: Edit compiled output
  Given an accepted compiled outline
  Then I can edit any chapter/scene/beat afterward; the manual and compiled paths converge on the same tables
```

> **Technical Notes E1.1:**
> - **Preconditions:** Phase 1 minimal `chapters/scenes/beats` + workspace `structure` surface; Phase 0 `ReviewGateService`; `LlmClient` compile role.
> - **Integrates-into:** an `OutlineCompiler` (producer on the review gate) + an expanded `structure` authoring surface (build on the Phase-1 minimal editor). ADR 0019.
> - **Leak-guards:** none at authoring time. Compiled output is a review-gate producer like the recorder (Phase 2) and nudge (E2) — same accept/edit/reject contract.

---

### E1.2 — Full Beat Document, Scene & Chapter Config

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.2.1 | As an **author**, I want the full beat document (`intent` omniscient/author-side, `goal`, `word_budget`, optional `nudge_target`) so that the engine has the pacing + direction signals the nudge and clock consume | 5 | Critical | 2 |
| S-1.2.2 | As an **author**, I want scene config (pov/tone/present-characters + elapsed-time bucket) and chapter config (outline + word cap) so that boundaries and pacing have their inputs | 3 | High | 2 |

**Acceptance Criteria - S-1.2.1:**
```gherkin
Feature: Full Beat Document

Scenario: Author the beat's pacing + direction fields
  Given a beat (Phase 1 carried goal only)
  When I open it
  Then I can author:
    | Field        | Meaning                                                        |
    | intent       | OMNISCIENT, author-side ("corner him; she mustn't learn arson")|
    | goal         | the satisfaction anchor ("Luna presses him about that night")  |
    | word_budget  | the per-beat pacing clock                                      |
    | nudge_target | optional; who (if anyone) the nudge is framed onto             |
  And pov/tone are INHERITED from the scene, not re-authored on the beat

Scenario: intent is author-side only
  Given a beat with an omniscient intent
  Then intent is never sent to any NPC as-is; only a leak-checked nudge derived from it may cross (E2)
```

**Acceptance Criteria - S-1.2.2:**
```gherkin
Feature: Scene & Chapter Config

Scenario: Scene carries pov/tone/present + elapsed bucket
  Given a scene
  Then it carries pov_mode/pov_anchor/tone, present characters, and an optional authored elapsed-time bucket

Scenario: Chapter carries outline + word cap
  Given a chapter
  Then it carries its outline text and a word cap (the outer hard pacing flag)
```

> **Technical Notes E1.2:**
> - **Preconditions:** S-1.1.x; Phase 1 minimal beat/scene.
> - **Integrates-into:** enrich the Phase-1 beat editor with `intent`/`word_budget`/`nudge_target` (goal already exists); scene gains the elapsed bucket; chapter gains the word cap. Columns land per ADR 0012/0015 follow-on.
> - **Leak-guards:** `intent` is the omniscient author-side text that the `omniscient_authoring` guard keeps from crossing to NPCs; the narrator `BEAT` block may carry it (narrator-only) — see E2.2.

---

## EPIC E2: The Nudge — authorial pressure, leak-checked

> The signature mechanic: the author's omniscient `intent` becomes a **bounded nudge** delivered to (at most) one NPC, **leak-checked at the assembler boundary** so the raw omniscient truth never crosses. This is the `omniscient_authoring` guard made concrete, and it lights the NPC `NUDGE` block + the narrator `BEAT`/`DIRECTOR_STATE` blocks.

### E2.1 — Nudge Derivation & the NPC NUDGE Block

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.1.1 | As a **system**, I want to derive a bounded nudge from `beat.intent` + the target's `knowledge_boundary` (leak-checked at the boundary) so that authorial pressure reaches an NPC without leaking omniscient truth | 8 | Critical | 3 |
| S-2.1.2 | As an **author**, I want a hand-authored nudge mode that takes the **same** leak-check so that I can write a nudge directly without bypassing safety | 5 | High | 3 |
| S-2.1.3 | As a **system**, I want the leak-checked nudge assembled into the NPC `NUDGE` block (a typical beat nudges 0–1 characters; everyone else runs pure self-simulation) so that direction is delivered in-context, isolated like every other NPC block | 3 | Critical | 4 |

**Acceptance Criteria - S-2.1.1:**
```gherkin
Feature: Nudge Derivation (leak-checked)

Scenario: Compile omniscient intent into a bounded nudge
  Given beat.intent "Corner him about the fire; she mustn't learn it was arson"
  And the target NPC's knowledge_boundary excludes "arson"
  When the nudge compiles
  Then it produces nudge { kind, level, text, target, goal, source: derived }
  And the text frames pressure toward the goal WITHOUT revealing "arson"
  And the raw omniscient intent never crosses the assembler boundary

Scenario: Derivation runs through the review gate
  Given a derived nudge
  Then it is a review-gate producer (accept / edit / reject) before it can be delivered
  And level is NOT authored here — it is set at runtime by the word-budget clock (E3)
```

**Acceptance Criteria - S-2.1.2:**
```gherkin
Feature: Hand-Authored Nudge

Scenario: Same leak-check as derived
  Given I hand-author a nudge instead of deriving it
  When it is processed
  Then it takes the same leak-check against the target's knowledge_boundary
  And it cannot deliver a fact outside that boundary
```

**Acceptance Criteria - S-2.1.3:**
```gherkin
Feature: NPC NUDGE Block

Scenario: The nudge is delivered as an isolated block
  Given a leak-checked nudge targeting Luna
  When Luna's prompt assembles
  Then the [NUDGE] block carries only the bounded directed-pressure text
  And no other present character receives Luna's nudge
  And a beat with no nudge_target delivers no NUDGE block (everyone self-simulates)

Scenario: NUDGE never widens the boundary
  Given the [NUDGE] block
  Then it cannot reintroduce a fact the IDENTITY/knowledge_boundary excludes (register-gating of HOW it lands arrives in Phase 5)
```

> **Technical Notes E2.1:**
> - **Preconditions:** S-1.2.1 (`intent`/`nudge_target`); Phase 2 assembler + `knowledge_boundary`; Phase 0 review gate + compile role.
> - **Integrates-into:** a `NudgeDeriver` (review-gate producer) feeding a new `NUDGE` block in the Phase-2 NPC assembler. ADR 0015 §2 / 0008.
> - **Leak-guards:** `NUDGE` block = `omniscient_authoring` (+ inherits `knowledge_boundary`). The leak-check is structural at the assembler boundary; the review gate is the human floor. Register-gated *delivery* (how hard/softly it lands) is layered on in Phase 5.

---

### E2.2 — Narrator BEAT (enriched) & DIRECTOR_STATE (new)

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.2.1 | As a **system**, I want the narrator `BEAT` block enriched to the full `intent`/`goal`/`word_budget` (narrator-only omniscient view) so that the narrator steers toward the authored direction | 3 | Critical | 4 |
| S-2.2.2 | As a **system**, I want a new narrator `DIRECTOR_STATE` block (engine → narrator word-budget warnings + ceiling pushes) so that the narrator paces toward landing the beat | 2 | Critical | 4 |

**Acceptance Criteria - S-2.2.1:**
```gherkin
Feature: Enriched Narrator BEAT Block

Scenario: Narrator sees the full beat (omniscient, narrator-only)
  Given a beat with intent/goal/word_budget
  When the narrator prompt assembles
  Then [BEAT] carries intent + goal + word_budget (Phase 1 carried goal only)
  And this omniscient intent is narrator-only and never reaches an NPC
```

**Acceptance Criteria - S-2.2.2:**
```gherkin
Feature: DIRECTOR_STATE Block

Scenario: The engine speaks pacing to the narrator
  Given the word-budget clock raises a warning or fires the ceiling
  When the narrator prompt assembles
  Then [DIRECTOR STATE] carries the budget warning / ceiling push (engine -> narrator)
  And when no warning is active the block is absent (no filler)
```

> **Technical Notes E2.2:**
> - **Preconditions:** S-1.2.1; Phase 1 narrator assembler; E3 word-budget clock (DIRECTOR_STATE consumes it).
> - **Integrates-into:** enrich the Phase-1 `BEAT` block; add `DIRECTOR_STATE` to the narrator assembler. ADR 0016 §2.
> - **Leak-guards:** `BEAT` = `omniscient_authoring` (narrator-side, allowed); `DIRECTOR_STATE` = `none`. `MESH_AWARENESS` is **not** added here (Phase 5).

---

## EPIC E3: Pacing Clock & Boundary Events

> The runtime that lands beats and fires the batched-subsystem boundaries. The word-budget clock drives the ADR 0008 escalation ladder; `BEAT_DONE` closes beats; `SCENE_DONE`/`CHAPTER_DONE` fire the boundary slots (the drift/decay parts await Phase 5).

### E3.1 — The Word-Budget Clock, BEAT_DONE & Ceiling

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.1.1 | As a **system**, I want the word-budget clock to accumulate prose against `word_budget` and climb the nudge `level` (L0→L3) toward/at budget so that pressure rises as a beat overruns | 5 | Critical | 4 |
| S-3.1.2 | As a **system**, I want `BEAT_DONE` to fire when the goal-satisfaction judge trips (LLM judge vs the free-text `goal`, human-reviewable) **or** the budget is exhausted at ceiling — dissolving the nudge on goal-met | 8 | Critical | 5 |
| S-3.1.3 | As a **system**, I want the ceiling + break-glass (word count > 1.6× budget with goal unmet → narrator push → player "Continue / Skip / Direct?" → break-glass directive) so that a beat can always be force-landed | 5 | High | 5 |

**Acceptance Criteria - S-3.1.1:**
```gherkin
Feature: Word-Budget Clock

Scenario: Pressure climbs with overrun
  Given a beat with word_budget N
  When prose accumulates toward and past N
  Then a WARNING flag is raised near budget
  And the nudge level climbs L0 -> L3 at runtime (level is set by the clock, never authored)
```

**Acceptance Criteria - S-3.1.2:**
```gherkin
Feature: BEAT_DONE

Scenario: Goal satisfied
  Given prose so far
  When the LLM goal-satisfaction judge evaluates it against the free-text goal and reports met
  Then BEAT_DONE fires, the judgment is human-reviewable via the gate, and the nudge dissolves

Scenario: Budget exhausted at ceiling
  Given the goal is still unmet and the budget is exhausted at the ceiling
  Then BEAT_DONE fires by force-landing the beat
```

**Acceptance Criteria - S-3.1.3:**
```gherkin
Feature: Ceiling + Break-Glass

Scenario: Escalate to force a landing
  Given word count > 1.6 x budget and the goal still unmet
  Then the ADR 0008 ceiling fires: narrator push -> player "Continue / Skip / Direct?" -> break-glass directive
  And the chapter word cap remains the outer hard flag forcing a chapter wrap
```

> **Technical Notes E3.1:**
> - **Preconditions:** S-1.2.1 (`word_budget`/`goal`), S-2.1.x (nudge level), Phase 1 loop state (word counters exist on the session), Phase 0 review gate; `LlmClient` judge role.
> - **Integrates-into:** the Phase-1 `SessionStateMachine` runs the clock + ladder (ADR 0016 §5); the player-facing "Continue / Skip / Direct?" break-glass prompt surfaces **in the Writing/Play page host (E0.4)**, not a new screen; the goal judge is a review-gate producer. Thresholds (1.0×, 1.6×) are config (shared tunable surface in Phase 6; defaults until then).
> - **Leak-guards:** none new; the nudge it escalates remains leak-checked (E2).

---

### E3.2 — Boundary Events & Elapsed Time

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.2.1 | As a **system**, I want `SCENE_DONE` and `CHAPTER_DONE` boundary events fired in-loop (scene summary + chapter log + next-chapter card swap now; batched-drift/decay slots reserved for Phase 5) so that the loop has the deterministic boundary spine | 5 | Critical | 6 |
| S-3.2.2 | As a **system**, I want elapsed time sourced as a coarse bucket (authored → narrator-inferred + review → default `continuous`) on scene/chapter boundaries so that later decay can key off a *real* gap | 3 | High | 6 |

**Acceptance Criteria - S-3.2.1:**
```gherkin
Feature: Boundary Events (this-phase subset)

Scenario: SCENE_DONE fires its available producers
  Given a scene ends
  Then SCENE_DONE writes the scene summary (compacting immediate context)
  And the batched-DRIFT and time-DECAY slots are reserved but no-op this phase (they need edges/internal state, Phase 5)

Scenario: CHAPTER_DONE fires its available producers
  Given a chapter ends (the state machine's chapter wrap)
  Then CHAPTER_DONE writes the chapter log and swaps to the next-chapter character_card snapshot
  And the decay/gap-drift slot is reserved but no-op this phase

Scenario: Deterministic ordering
  Given a boundary fires
  Then BEAT_DONE -> SCENE_DONE -> CHAPTER_DONE producers run in the fixed ADR 0016 order
```

**Acceptance Criteria - S-3.2.2:**
```gherkin
Feature: Elapsed-Time Bucket

Scenario: Sourced three ways, in order
  Given a scene/chapter boundary
  Then elapsed time is one of: continuous | hours | days | weeks | months | longer
  And it is sourced: authored if set; else narrator-inferred from prose (human-confirmable via the gate); else default continuous

Scenario: The bucket is recorded for later decay
  Given a boundary declares a gap of days+
  Then the bucket is persisted on the boundary so Phase-5 decay can key off it
  And a continuous/filler boundary changes nothing by time
```

> **Technical Notes E3.2:**
> - **Preconditions:** S-3.1.x; Phase 1 scene summary + `chapter_logs`; Phase 2 recorder; Phase 0 `character_cards` (per chapter).
> - **Integrates-into:** the Phase-1 state machine gains `SCENE_DONE`/`CHAPTER_DONE` (ADR 0016 §5). **Crucially, the drift/decay producers are wired as reserved slots now and *filled* in Phase 5** when edges + internal state exist — establishing the boundary spine before the subsystems that hang off it.
> - **Leak-guards:** none new. Elapsed-bucket inference is a review-gate-confirmable narrator output (sourced, like player delivery in Phase 2). ADR 0015 §5–6.

---

## Sprint Roadmap

### Sprint 1: Outline Authoring (E1.1)
```
├── S-1.1.1: Outline compilation (premise -> chapters/scenes/beats draft -> review gate)
├── S-1.1.2: Manual hierarchy authoring + editing compiled output
└── Test: compilation never auto-commits; manual + compiled converge on the same tables
```

### Sprint 2: Beat Document & Config (E1.2)
```
├── S-1.2.1: Full beat document (intent/goal/word_budget/nudge_target; pov/tone inherited)
├── S-1.2.2: Scene config (+ elapsed bucket) + chapter config (+ word cap)
└── Test: intent is author-side only and never sent to an NPC as-is
```

### Sprint 3: Nudge Derivation (E2.1 start)
```
├── S-2.1.1: Derive leak-checked nudge from intent + knowledge_boundary (-> review gate)
├── S-2.1.2: Hand-authored nudge takes the same leak-check
└── Test (leak guard): omniscient "arson" never crosses into the nudge text
```

### Sprint 4: NUDGE/BEAT/DIRECTOR_STATE & Clock (E2 + E3.1 start)
```
├── S-2.1.3: NPC NUDGE block (0-1 targets; isolated; no cross-delivery)
├── S-2.2.1: Enriched narrator BEAT block (full intent/goal/budget, narrator-only)
├── S-2.2.2: New narrator DIRECTOR_STATE block
├── S-3.1.1: Word-budget clock (warning + nudge level L0->L3 at runtime)
└── Test: a no-target beat delivers no NUDGE; everyone self-simulates
```

### Sprint 5: BEAT_DONE & Ceiling (E3.1)
```
├── S-3.1.2: BEAT_DONE (goal judge -> review gate | budget exhausted; nudge dissolves on met)
├── S-3.1.3: Ceiling + break-glass (>1.6x budget & unmet -> push -> Continue/Skip/Direct)
└── Test: a runaway beat always force-lands; chapter cap forces a wrap
```

### Sprint 6: Boundary Events & Elapsed Time (E3.2)
```
├── S-3.2.1: SCENE_DONE / CHAPTER_DONE (summary + chapter log + card swap; drift/decay slots reserved)
├── S-3.2.2: Elapsed-time bucket (authored -> inferred+review -> continuous)
└── Phase 4 end-to-end: a directed beat lands on budget; boundaries fire in order
```

---

## Definition of Done (DoD)

See the [program DoD](./README.md#9-global-definition-of-done-dod). Phase-4 emphasis:

- [ ] A human authors a real **outline → chapter → scene → beat** structure (AI-compiled or by hand); compilation **never auto-commits** (review gate).
- [ ] The beat carries the full document (`intent`/`goal`/`word_budget`/`nudge_target`); the omniscient `intent` is **author/narrator-side only** and never reaches an NPC.
- [ ] The **nudge is leak-checked** (derived or hand-authored): omniscient truth outside the target's `knowledge_boundary` **never** crosses; a no-target beat delivers **no** `NUDGE` block.
- [ ] The **word-budget clock** climbs the nudge level; **`BEAT_DONE`** fires on goal-judge or exhausted budget; the **ceiling/break-glass** can always force a landing.
- [ ] **`SCENE_DONE`/`CHAPTER_DONE`** fire in deterministic order (summary, chapter log, card swap); the **drift/decay slots are reserved but no-op** (filled in Phase 5); the **elapsed bucket** is sourced + persisted.
- [ ] `MESH_AWARENESS` is **not** lit (deferred to Phase 5). `pnpm lint` clean; UX states covered; responsive + keyboard-accessible.

---

## Success Metrics — Phase 4

| Metric | Target | Measurement |
|--------|--------|-------------|
| Directed authoring | Achieved | A human compiles/hand-authors a full outline and plays it |
| Nudge leak-safety | 0 leaks | Omniscient intent outside knowledge_boundary never reaches an NPC |
| Beat landing | 100% | Every beat ends via goal-judge or ceiling/break-glass; no infinite beats |
| Boundary determinism | 100% | BEAT_DONE → SCENE_DONE → CHAPTER_DONE producers run in fixed order |
| Ordering discipline | MESH_AWARENESS deferred | No block assembled over nonexistent mesh data |

---

## Risk Register — Phase 4

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Omniscient intent leaks via the nudge | Critical | Medium | Leak-check at the assembler boundary (derived AND hand-authored) + review gate; raw intent never crosses |
| Lighting MESH_AWARENESS before the mesh exists | High | Medium | Explicitly deferred to Phase 5 (integration-point ordering); block absent until edges exist |
| Beats never land (pantser overrun) | High | Medium | Word-budget clock + goal judge + ceiling/break-glass + chapter cap as the outer flag |
| Goal judge is brittle on free-text goals | Medium | Medium | LLM judge is human-reviewable via the gate; assertions remain a future opt-in |
| Reserved drift/decay slots quietly do the wrong thing | Medium | Low | Slots are explicit no-ops this phase with tests asserting they do not mutate edges/state |

---

*Document Version: 2.0 · Author: Zulfikar Hidayatullah · Created: June 2026*
