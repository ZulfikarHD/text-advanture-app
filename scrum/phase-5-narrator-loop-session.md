# Phase 5: Runtime — Narrator Loop & Session — Directed Interactive Novel Engine
## Directed Interactive Novel Engine (DINE)

**Timeline:** ~2.25 Months (9 Sprints)
**Sprint Duration:** 1 Week
**Team Size Recommendation:** 1 Full-stack Dev (+ optional QA for leak-guard negative tests)
**Depends on:** Phases 1–4 (running app + DB schema + LLM client + committed authoring content: characters, cards, edges/priors, registers, sensitivities, lorebook, chapters/scenes/beats, nudges, prompt blocks)
**Governing ADRs:** 0016 (narrator loop), 0009 (POV projection), 0010 (recorder mechanics), 0015 (beat document + boundaries), 0012 (persistence schema), 0013 (authoring/compile pipeline)

> Goal: stand up the **narrator → player → narrator spine** running end to end. A session forks the authored template into a save realm; the session state machine drives the deterministic loop; each narrator turn is **two calls** (prose then recorder); the recorder commits the two-layer record (public `surface` + per-character `true_state` + witnesses); POV projection hands each NPC only its own limited excerpt; context memory and boundary events keep the loop bounded and evolve state on narrative time; the player gives prose input with sourced delivery; and a session can be saved and resumed exactly where it left off. After this phase a story can be **played start to finish** — appraisal and full NPC psychology (the delta engine) land in Phase 6.

---

## Product Backlog Overview

### Epic Summary

| Epic ID | Epic Name | Priority | Story Points | Sprints |
|---------|-----------|----------|--------------|---------|
| E1 | Session & Save Management | Critical | 18 | 26–27 |
| E2 | Session State Machine | Critical | 13 | 28 |
| E3 | Narrator Turn — Prose + Recorder | Critical | 36 | 29–31 |
| E4 | POV Projection | Critical | 13 | 32 |
| E5 | Context Memory & Boundary Events | High | 26 | 33–34 |
| E6 | Player Input & Delivery Channel | High | 5 | 32 |

**Total Estimated:** ~111 Story Points

---

## EPIC E1: Session & Save Management

### E1.1 — Create / Fork / Multi-Save

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.1.1 | As a **player**, I want to start a new session that deep-copies the authored starting state into the save realm and seeds relationship edges from disposition priors so that a playthrough evolves without touching the template | 8 | Critical | 26 |
| S-1.1.2 | As a **player**, I want multiple independent saves (name, list, load, reset, delete) so that I can keep parallel playthroughs and reset freely | 5 | High | 27 |

**Acceptance Criteria - S-1.1.1:**
```gherkin
Feature: Start a New Session (Fork)

Scenario: Starting a session deep-copies the authored template into a save realm
  Given a story with committed authoring content (characters, cards, edges priors, chapters/scenes/beats)
  When I start a new session for that story
  Then a save realm is created scoped to that session
  And the authored starting state is deep-copied into it
  And the session begins at the session_start state node positioned at the story's first chapter, scene, and beat

Scenario: Relationship edges are seeded from disposition priors by target traits
  Given a character whose card declares disposition_priors keyed by target traits
  When the session is forked
  Then a directed relationship edge is created from that character toward each present target
  And each edge's axes are seeded from the matching priors selected by the target's traits (e.g. gender, demeanor, faction, shows-interest)
  And a player character carries appearance only and has no outgoing edges

Scenario: The authoring realm is never mutated by play
  Given an active session whose state has evolved
  When I inspect the original authoring template
  Then the template is unchanged
  And the fork either completes fully or not at all (no partially-seeded session)
```

**Acceptance Criteria - S-1.1.2:**
```gherkin
Feature: Multiple Independent Saves

Scenario: Create and name parallel saves
  Given a story I own
  When I create more than one save and name each one
  Then each save is an independent fork with its own evolving state
  And changes in one save never affect another

Scenario: List and load saves
  Given I have several saves
  When I list my saves
  Then I see each save's name and when it was last played
  And loading a save resumes it where it left off

Scenario: Reset a save
  Given an evolved save
  When I reset it
  Then its evolving state returns to the freshly-forked starting state
  And the authoring template is untouched

Scenario: Delete a save
  Given a save I no longer want
  When I delete it after confirming
  Then that save and its evolving state are removed
  And my other saves and the template remain intact
```

> **Technical Notes E1.1:**
> - **Business Logic:**
>   - A **session is a fork** of the authored template: at creation the engine **deep-copies** the authored starting state into the **save realm**, which is mutable and **session-scoped**. The **authoring realm is immutable** at runtime.
>   - At the fork, **relationship edges are seeded from `disposition_priors`** keyed by the target's traits; the matched priors set the starting axis values. A **player character is appearance-only and has no outgoing edges**.
>   - The fork must be **atomic/transactional** — a session is either fully seeded or not created at all; a half-seeded save must never be loadable.
>   - **Multi-save and reset come for free** from forking: each save is an independent fork; reset re-forks from the template; delete removes only that fork. None of these ever mutate the template.
>   - All saves are **owner-scoped** — a player only ever sees, loads, resets, or deletes their own saves.
> - **Reference:** ADR 0012/0002.

### E1.2 — Loop State Persistence

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.2.1 | As a **system**, I want to persist loop state (state_node, current chapter/scene/beat, word counts, nudge level, narrative clock, resume_anchor, last-played) so that a session can be saved and resumed exactly where it left off | 5 | Critical | 27 |

**Acceptance Criteria - S-1.2.1:**
```gherkin
Feature: Loop State Persistence

Scenario: Loop state is persisted with the session
  Given an in-progress session
  Then the session persists its loop state:
    | Field             | Meaning                                                  |
    | state_node        | current state-machine node                               |
    | current chapter   | where the loop is positioned                             |
    | current scene     | where the loop is positioned                             |
    | current beat      | where the loop is positioned                             |
    | beat word count   | clocks the beat word_budget thresholds                   |
    | chapter word count| clocks the chapter word_cap                              |
    | nudge level       | current escalation rung                                  |
    | narrative clock   | accumulated in-world time from elapsed buckets           |
    | resume_anchor     | scene type, last line, POV, tone                         |
    | last-played       | when the session was last advanced                       |

Scenario: Resume restores the exact loop position
  Given a saved session
  When I load it later
  Then the engine restores the same state_node, position, word counts, nudge level, and narrative clock
  And narration continues from the persisted resume_anchor rather than restarting the beat

Scenario: Save is consistent under interruption
  Given a session interrupted mid-turn
  When I reload it
  Then loop state reflects the last committed boundary
  And never a half-applied turn
```

> **Technical Notes E1.2:**
> - **Business Logic:**
>   - The session carries the full **loop state**: `state_node`, current chapter/scene/beat, `beat_word_count`, `chapter_word_count`, `nudge_level`, `narrative_clock`, `resume_anchor`, and last-played time.
>   - Persisted loop state is the **materialized current position**; resume must restore it **exactly** so a save/load is indistinguishable from never having paused.
>   - Loop state is only advanced at **committed boundaries** (a turn or boundary event commits atomically). A reload must never observe a half-applied narrator turn or a partially-fired boundary event.
>   - `resume_anchor` is the **continuity seam** consumed by the narrator loop on resume (see E5.3).
> - **Reference:** ADR 0016/0012.

---

## EPIC E2: Session State Machine

### E2.1 — The Spine & In-Loop Sequencing

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.1.1 | As a **system**, I want the state machine to drive transitions (session_start → narrator_turn → {player_moment \| npc_moment \| beat_complete} → narrator_resumes) so that the loop has a deterministic spine | 8 | Critical | 28 |
| S-2.1.2 | As a **system**, I want in-loop sequencing (recorder → appraisal over each character's projected surface → ruptures in-scene / drift batched; boundary events fire batched subsystems) so that appraisal always reasons over witnessed evidence, never omniscient truth | 5 | Critical | 28 |

**Acceptance Criteria - S-2.1.1:**
```gherkin
Feature: The Session Spine

Scenario: The spine drives deterministic transitions
  Given an active session
  Then the state machine moves through session_start → narrator_turn → {player_moment | npc_moment | beat_complete} → narrator_resumes
  And the next node is determined by the narrator turn's handoff signal

Scenario: Handoff routes the next node
  Given a completed narrator turn that emitted a handoff signal
  When the handoff is player_moment
  Then control passes to the player and the loop awaits player input
  When the handoff is npc_moment
  Then the interaction queue runs and an NPC acts, after which the narrator resumes
  When the handoff is beat_complete
  Then the beat is closed and boundary events fire before the narrator resumes

Scenario: The state machine is the only conductor
  Given the running loop
  Then the state machine itself sequences every subsystem
  And there is no separate orchestrator module deciding transitions
```

**Acceptance Criteria - S-2.1.2:**
```gherkin
Feature: In-Loop Sequencing

Scenario: Recorder commits before appraisal runs
  Given a beat has played out
  When the turn proceeds
  Then the recorder commits the surface, per-character true_state, and witnesses first
  And only then does appraisal run

Scenario: Appraisal reasons over witnessed evidence, never omniscient truth
  Given the committed record
  When appraisal runs for a present character
  Then it reads only that character's witness-filtered, POV-projected surface
  And it can never read another character's true_state or the omniscient beat intent

Scenario: Ruptures apply immediately; ordinary drift is batched
  Given appraisal emits edge-axis and emotion proposals
  Then a rupture applies immediately in-scene
  And ordinary drift is batched to SCENE_DONE
  And boundary events fire their batched subsystems in their fixed order
```

> **Technical Notes E2.1:**
> - **Business Logic:**
>   - The **session state machine IS the conductor** — there is no separate orchestrator. It owns the spine `session_start → narrator_turn → {player_moment | npc_moment | beat_complete} → narrator_resumes`, and the **next node is the narrator turn's structured handoff**, not a separate classifier or external scheduler.
>   - **In-loop order is deterministic and recorder-first:** after a beat plays out, the **recorder commits** (`surface` + `true_state` + `witnessed_by`) **before** any appraisal runs. This guarantees appraisal reasons over the **committed, projected surface** (witnessed evidence) and can never peek at omniscient truth or another character's `true_state`.
>   - **Ruptures apply immediately, in-scene; ordinary drift is batched** to `SCENE_DONE`. Boundary events (`BEAT_DONE` / `SCENE_DONE` / `CHAPTER_DONE`) fire their batched subsystems in a fixed sequence (detailed in E5.2).
>   - Appraisal itself (proposals over the projected surface) **lands in Phase 6**; Phase 5 fixes the **sequencing slot** it occupies and the recorder-first invariant it depends on.
> - **Reference:** ADR 0016 §1, §4.

---

## EPIC E3: Narrator Turn — Prose + Recorder

### E3.1 — Prose Call

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.1.1 | As a **system**, I want to assemble the narrator prompt (registry-driven: POV contract, mesh-awareness, beat, director state, lorebook, scene state, resume anchor) so that the narrator has exactly the slots it needs and nothing more | 8 | Critical | 29 |
| S-3.1.2 | As a **system**, I want the prose call to return structured output (prose · handoff signal · inferred elapsed bucket) so that handoff detection is the prose call's output, not a separate classifier pass | 5 | Critical | 29 |
| S-3.1.3 | As a **system**, I want the mesh-awareness rule enforced (use the full mesh for atmosphere/body-language/room-dynamics only; never state what a present character would not know; perceived reads hedged) so that the narrator's omniscience never leaks into prose | 5 | Critical | 30 |

**Acceptance Criteria - S-3.1.1:**
```gherkin
Feature: Narrator Prompt Assembly

Scenario: The narrator prompt carries exactly its registry-defined slots
  Given a narrator turn is about to run
  When the prompt is assembled from the prompt-block registry
  Then it contains:
    | Slot            | Content                                                            |
    | POV contract    | scene pov_mode + pov_anchor + tone                                 |
    | mesh-awareness  | the directive plus the full relationship mesh                      |
    | beat            | current beat intent / goal / word_budget                           |
    | director state  | word-budget warnings + ceiling pushes                              |
    | lorebook        | keyword-matched world facts                                        |
    | scene state     | present characters · immediate context (~2000 tokens) · scene summary |
    | resume anchor   | injected only when resuming                                        |
  And no slot outside the registry definition is added

Scenario: The narrator sees the full beat doc and full mesh; the NPC never does
  Given the same beat
  Then the narrator prompt may include the full beat document and the full relationship mesh
  And no NPC prompt ever receives the omniscient beat intent or the full mesh
```

**Acceptance Criteria - S-3.1.2:**
```gherkin
Feature: Structured Prose Output

Scenario: The prose call returns prose, handoff, and an inferred elapsed bucket
  Given the prose call runs on the strong narrator model
  When it completes
  Then it returns structured output containing:
    | Field          | Value                                                      |
    | prose          | the narrated text                                          |
    | handoff        | player_moment | npc_moment | beat_complete                |
    | elapsed bucket | inferred in-world gap (continuous … longer)                |
  And handoff detection is this structured output, not a separate classifier pass

Scenario: Malformed structured output is never trusted
  Given the prose call returns an unparseable or non-conforming structure
  Then it is retried within a bound
  And, failing that, it is recorded as a failed call and surfaced — never guessed
```

**Acceptance Criteria - S-3.1.3:**
```gherkin
Feature: Mesh-Awareness Rule

Scenario: The mesh feeds atmosphere only
  Given the narrator holds the full mesh
  When it writes prose
  Then it may use the mesh only for atmosphere, body-language, and room dynamics

Scenario: Omniscience never leaks into prose
  Given a fact a present character would not know
  Then the prose never states that fact
  And every perceived read is hedged ("looks" / "seems" / "appears"), never asserted as truth

Scenario: The directive feeds the recorder's structural check
  Given hedged framing produced under this directive
  Then it is what the recorder's hedged-attribution validator enforces structurally downstream
  And the mesh-awareness rule is a directive, not a fourth leak guard
```

> **Technical Notes E3.1:**
> - **Business Logic:**
>   - The **narrator turn is two calls**: this **prose call** first, then the recorder sub-call (E3.2). The prose call runs on the **strong** narrator model and emits **structured output: prose · handoff · inferred elapsed bucket**.
>   - The prompt is **registry-driven** — slots are exactly: POV contract, mesh-awareness directive + full mesh, beat (intent/goal/`word_budget`), director state (budget warnings/ceiling), keyword-matched lorebook, scene state (present characters · immediate context ~2000 tokens · scene summary), and the resume anchor on resume. **Nothing outside the registry** is injected.
>   - The **narrator sees the full beat doc and the full mesh; the NPC never does** — the mesh-awareness rule keeps that omniscience out of the prose (atmosphere/body-language/room-dynamics only; never state what a present character would not know; **perceived reads hedged**).
>   - **Handoff is the prose call's structured output**, not a separate classifier pass; structured output is **parse-validated, retried within a bound, then surfaced as failed — never trusted** if non-conforming.
> - **Reference:** ADR 0016.

### E3.2 — Recorder Sub-Call

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.2.1 | As a **system**, I want the recorder to commit the two-layer record (surface + per-character true_state + witnessed_by{full\|overheard\|partial} + pov_anchor) so that downstream agents read only the public surface and never another character's private state | 8 | Critical | 30 |
| S-3.2.2 | As a **system**, I want hedged-attribution validation (reject unhedged "is sad"/"is lying"; block hidden facts via knowledge_boundary) before the record commits through the review gate so that safety holds at any model tier | 5 | Critical | 31 |
| S-3.2.3 | As a **system**, I want legibility derived for the committed surface read (= base_opacity × emotion intensity × awareness/mask × resolved register composure+tells on the target→observer edge) so that how much shows varies by character, feeling, and watcher | 5 | High | 31 |

**Acceptance Criteria - S-3.2.1:**
```gherkin
Feature: Two-Layer Beat Record

Scenario: The recorder commits the public surface and the private true_state separately
  Given a beat has played out
  When the recorder sub-call runs on the narrator model
  Then it commits:
    | Layer        | Content                                                        | Crosses agents? |
    | surface      | observable behavior + dialogue + hedged perceived reads        | yes             |
    | true_state   | per-character private feeling/intent                           | no, never       |
    | witnessed_by | per character: full | overheard | partial                      | tags only       |
    | pov_anchor   | the scene-contract anchor                                      | display only    |

Scenario: Downstream agents read only the public surface
  Given the committed record
  When any downstream agent reads it
  Then it can read only the surface
  And it can never read another character's true_state
  And a "read surface only" path physically cannot reach the private layer
```

**Acceptance Criteria - S-3.2.2:**
```gherkin
Feature: Hedged-Attribution Validation

Scenario: Unhedged mental-state assertions are rejected
  Given a proposed surface that asserts "is sad" or "is lying"
  When the recorder validates it before commit
  Then the unhedged assertion is rejected
  And only perceptual forms ("looks" / "seems" / "appears") are allowed

Scenario: Hidden facts are blocked by the knowledge boundary
  Given a proposed surface that would expose a fact behind a character's knowledge_boundary
  Then that hidden fact is blocked before commit

Scenario: The record commits through the review gate at any model tier
  Given a validated proposed record
  Then it is committed through the shared review gate (propose → review → commit)
  And the validator is structural — it holds even on a weak model and does not trust the model's good behavior
```

**Acceptance Criteria - S-3.2.3:**
```gherkin
Feature: Legibility Derivation

Scenario: Legibility is computed for the committed surface read
  Given a target character observed by an observer on the target→observer edge
  When the recorder commits the surface read
  Then legibility = base_opacity × emotion intensity × awareness/mask × resolved register (composure + tells) on that edge

Scenario: The same true_state surfaces differently to different watchers
  Given one true_state and two observers with different per-edge composure and tells
  Then the committed surface shows more to the watcher the target is fragile toward
  And less to the watcher the target is sealed toward

Scenario: A poker-face target dampens to "hard to read"
  Given a target with high base_opacity
  When the true_state is turmoil
  Then the committed surface still dampens to a composed / hard-to-read read
```

> **Technical Notes E3.2:**
> - **Business Logic:**
>   - The **recorder is a separate sub-call** on the narrator model (the second half of the two-call narrator turn), kept distinct so hedged-attribution validation and `true_state` derivation stay isolated from prose generation.
>   - It commits the **two-layer record**: `surface` = observable behavior + dialogue + **HEDGED** perceived reads (the **only** cross-agent layer); `true_state` = per-character private feeling/intent, held in a **separate place and NEVER cross-fed** (a character's own copy reaches it only via its `[SELF]` block); plus `witnessed_by{full | overheard | partial}` and `pov_anchor`.
>   - **Hedged-attribution rule is a structural validator** that does **not** trust the model: unhedged mental-state assertions (`is sad`, `is lying`) are **rejected before commit**; hidden facts are blocked by `knowledge_boundary`. The **review gate is the fidelity floor** (propose → review → commit), which matters more on weaker models.
>   - **Legibility is a derivation, not a stored trait:** `surface_read(target → observer) = base_opacity × intensity × (awareness/mask: can it be concealed?) × resolved register (composure + tells) on the target→observer edge`. Because `composure`/`tells` are **per-edge and directional**, one `true_state` surfaces **differently to different watchers**; high `base_opacity` dampens even strong turmoil to "composed / hard to read".
>   - **Model tiering:** the recorder runs on the **strong** narrator model (legibility + hedged surface is the hard generative step); minor NPCs never record — they only react to a pre-framed `surface`.
> - **Reference:** ADR 0010/0009/0016.

---

## EPIC E4: POV Projection

### E4.1 — Per-NPC Projection & Display

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.1.1 | As a **system**, I want each NPC's excerpt projected to its own limited POV (witness-filter → fidelity degrade → surface only → decode via reads_target → validate vs knowledge_boundary) so that no NPC receives another character's interiority or hidden facts | 8 | Critical | 32 |
| S-4.1.2 | As a **player**, I want the display rendered in the scene's POV contract (pov_anchor interiority allowed in display only) so that I read coherent prose while NPC agents get the projected surface | 5 | High | 32 |

**Acceptance Criteria - S-4.1.1:**
```gherkin
Feature: Per-NPC POV Projection

Scenario: An NPC excerpt is projected to that NPC's limited POV
  Given a committed surface and an NPC present at the beat
  When the NPC's excerpt is built
  Then the projection pipeline runs in order:
    | Step                | Effect                                                        |
    | witness-filter      | keep only what witnessed_by includes that NPC                 |
    | fidelity degrade    | degrade per the NPC's full / overheard / partial fidelity     |
    | surface only        | reduce to the public surface layer                            |
    | decode via reads_target | apply that NPC's reads_target toward the target           |
    | knowledge_boundary  | validate against the NPC's knowledge_boundary                 |

Scenario: No NPC receives another character's interiority or hidden facts
  Given the projected excerpt
  Then it never contains any other character's true_state
  And it never contains a fact outside that NPC's knowledge_boundary
  And the NPC's own feelings come from its own SELF / SNAPSHOT, not from the excerpt

Scenario: Decode quality varies without leaking
  Given an observer whose reads_target is accurate toward the target
  And another observer whose reads_target crashes toward the target
  Then the accurate observer gets a faithful read
  And the crashing observer gets a degraded read
  And in both cases the underlying truth never leaks
```

**Acceptance Criteria - S-4.1.2:**
```gherkin
Feature: Display in the POV Contract

Scenario: The human reads coherent prose in the scene's POV
  Given the scene's POV contract with its pov_anchor
  When prose is displayed to the player
  Then it is rendered in that POV contract
  And pov_anchor interiority is allowed in the display only

Scenario: NPC agents still receive only the projected surface
  Given the same beat
  Then while the display may use the anchor's interiority
  And NPC agents receive only their own projected surface
  And the display path never feeds an NPC the anchor's interiority
```

> **Technical Notes E4.1:**
> - **Business Logic:**
>   - There are **two per-edge, directional dials**: **legibility** — *how much shows* — is baked into `surface` at the **recorder** (E3.2); **decode** — *how well the observer reads it* — is applied **per observer at projection** via `reads_target` on the observer→target edge (`accurate → faithful`, `crashes → degraded/distorted`).
>   - The **NPC scene-excerpt pipeline** is, in order: **witness-filter → fidelity-degrade → surface only → decode via `reads_target` → validate vs `knowledge_boundary`**. The result carries **no other character's `true_state` and no out-of-boundary fact**; the NPC's **own** feelings come from its `[SELF]`/`[SNAPSHOT]`, never the excerpt.
>   - **Safety is structural and model-independent** (holds at any tier); **fidelity is best-effort and human-backstopped** — a misread is an in-character, recoverable quality bug; a leaked secret is never permitted. This is the **third leak guard** (alongside awareness-fold and nudge-compile).
>   - The **display** renders `surface` in the scene's **POV contract** with **`pov_anchor` interiority allowed in display only**; `3rd-omniscient` is display-only because NPC excerpts are *always* projected to a limited POV.
> - **Reference:** ADR 0009.

---

## EPIC E5: Context Memory & Boundary Events

### E5.1 — Memory Layers

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.1.1 | As a **system**, I want to maintain immediate context (~2000 tokens) and compact it into scene summaries at SCENE_DONE and chapter logs at CHAPTER_DONE so that context stays bounded while continuity is preserved | 5 | High | 33 |
| S-5.1.2 | As a **system**, I want lorebook injection by keyword match into the narrator (and knowledge-boundary-clamped NPC) context so that relevant world facts appear without breaching isolation | 3 | High | 33 |

**Acceptance Criteria - S-5.1.1:**
```gherkin
Feature: Memory Layers

Scenario: Immediate context stays bounded
  Given an ongoing scene
  Then an immediate context window of about 2000 tokens carries the recent exchange

Scenario: Scene summary at SCENE_DONE
  Given the immediate context has grown across a scene
  When SCENE_DONE fires
  Then the immediate context is compacted into a scene summary
  And the window is bounded again while continuity is preserved

Scenario: Chapter log at CHAPTER_DONE
  When CHAPTER_DONE fires
  Then scene summaries are compacted into a chapter log of key events for continuity
```

**Acceptance Criteria - S-5.1.2:**
```gherkin
Feature: Lorebook Injection

Scenario: World facts injected on keyword match
  Given lorebook entries with keywords
  When matching keywords appear in play
  Then the matched world facts are injected into the narrator context

Scenario: NPC lorebook injection is knowledge-boundary clamped
  Given the same matched facts
  When they are injected into an NPC context
  Then they are clamped by that NPC's knowledge_boundary
  And a fact the NPC must not know is withheld, preserving isolation

Scenario: Lorebook carries world facts only
  Given any injected lorebook content
  Then it is a world fact
  And never a character's interiority
```

> **Technical Notes E5.1:**
> - **Business Logic:**
>   - **Three memory layers** bound growth while preserving continuity: **immediate context (~2000 tokens)** of the recent exchange → compacted into a **scene summary at `SCENE_DONE`** → compacted into a **chapter log at `CHAPTER_DONE`**.
>   - **Lorebook injection is by keyword match.** Matched world facts enter the **narrator** context directly; entering an **NPC** context they are **clamped by that NPC's `knowledge_boundary`**, so isolation is preserved.
>   - Lorebook content is **world facts only — never a character's interiority**; an optional minimum-reveal-chapter gate keeps future-arc facts out until they are knowable.
> - **Reference:** ADR 0015/0016/0013.

### E5.2 — Boundary Events & Clock

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.2.1 | As a **system**, I want the word-budget clock (warning near budget; hard override when word count exceeds 1.6× budget with the goal still unmet; chapter word_cap forces a wrap) so that pacing is enforced | 5 | High | 33 |
| S-5.2.2 | As a **system**, I want a BEAT_DONE judge (goal-satisfaction signal OR budget exhausted) and elapsed-time sourcing (authored → narrator-inferred → default continuous, human-confirmable) so that beats land and time gaps are recorded | 5 | High | 34 |
| S-5.2.3 | As a **system**, I want SCENE_DONE/CHAPTER_DONE to fire batched drift, plus decay + emotion gap-drift only on a real gap (days+), plus the next-chapter card swap, so that relationship/internal state evolves at boundaries on narrative time | 5 | High | 34 |

**Acceptance Criteria - S-5.2.1:**
```gherkin
Feature: Word-Budget Clock

Scenario: Warning near budget
  Given prose accumulating against a beat's word_budget
  When the count reaches or nears the budget
  Then a warning flag is raised
  And the nudge ladder climbs

Scenario: Hard override past 1.6x budget with the goal unmet
  Given the word count exceeds 1.6 × the beat's word_budget
  And the goal is still unmet
  Then the ceiling fires to force the beat to land:
    | Step           | Effect                                  |
    | narrator push  | the engine pushes the narrator to wrap  |
    | player prompt  | "Continue / Skip / Direct?"             |
    | break-glass    | a hard directive forces the beat to land|

Scenario: Chapter word_cap forces a wrap
  Given the chapter word_count exceeds the chapter word_cap
  Then a chapter wrap is forced as the outer hard flag
```

**Acceptance Criteria - S-5.2.2:**
```gherkin
Feature: BEAT_DONE Judge and Elapsed Sourcing

Scenario: BEAT_DONE on goal satisfaction
  Given the beat's goal-satisfaction signal trips (a judge evaluates the prose against the free-text goal, human-reviewable)
  When the goal is met
  Then BEAT_DONE fires
  And the nudge dissolves

Scenario: BEAT_DONE on budget exhaustion
  Given the goal is unmet
  And the word budget is exhausted and the ceiling reached
  Then BEAT_DONE fires
  And the beat is force-landed

Scenario: Elapsed time is sourced and human-confirmable
  Given a boundary
  Then the elapsed bucket is sourced in order:
    | Order | Source            | When                                       |
    | 1     | authored          | the author declared the gap                |
    | 2     | narrator-inferred | inferred from the prose ("weeks later")    |
    | 3     | default           | continuous — the scene flows on, no gap    |
  And a narrator-inferred gap is human-confirmable at the boundary
```

**Acceptance Criteria - S-5.2.3:**
```gherkin
Feature: Scene and Chapter Boundary Events

Scenario: SCENE_DONE applies batched drift and writes the summary
  When SCENE_DONE fires
  Then batched ordinary drift is applied to edges
  And the scene summary is written

Scenario: Decay and emotion gap-drift fire only on a real gap
  Given a boundary that declares a real elapsed gap of days or more
  Then decay and emotion gap-drift are applied, scaled by the elapsed bucket
  And a continuous or filler boundary changes nothing by time — only events move state

Scenario: CHAPTER_DONE swaps the next-chapter card
  When CHAPTER_DONE fires
  Then the chapter log is written
  And the next-chapter card snapshot is loaded
  And the save state carries across the swap
```

> **Technical Notes E5.2:**
> - **Business Logic:**
>   - **Word-budget clock**, two tunable thresholds: a **warning at/near `word_budget`** (the nudge ladder climbs L0→L3); a **hard override when word count > 1.6 × `word_budget` with the goal still unmet**, firing the ceiling — **narrator push → player "Continue / Skip / Direct?" → break-glass directive** — to force the beat to land. The **chapter `word_cap` is the outer hard flag** that forces a chapter wrap.
>   - **`BEAT_DONE` fires when EITHER** the **goal-satisfaction signal trips** (an LLM judge evaluates the prose against the free-text `goal`, human-reviewable through the shared gate) **OR** the **word budget is exhausted and the ceiling reached** (force-land). Satisfying the goal **dissolves the nudge**.
>   - **Elapsed time is a coarse, sourced bucket** (`continuous · hours · days · weeks · months · longer`), sourced in order **authored → narrator-inferred → default `continuous`**, with inferred gaps **human-confirmable** at the boundary.
>   - **Boundary sequencing:** `BEAT_DONE` → recorder + appraisal + nudge dissolve; `SCENE_DONE` → **batched ordinary drift** + scene summary; `CHAPTER_DONE` → chapter log + **next-chapter card swap** (save state carries across). **Ruptures apply immediately in-scene; only ordinary drift is batched.**
>   - **Decay + emotion gap-drift fire ONLY on a real gap (`days`+)**, keyed to the **elapsed bucket on whichever boundary carries it** (a scene that declares "three weeks pass" decays at its own `SCENE_DONE`). A **`continuous`/filler** boundary changes nothing by time — only events move state. The bucket → decay-magnitude / gap-cap mapping is shared tunable config.
> - **Reference:** ADR 0015/0008/0004.

### E5.3 — Resume Anchor

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.3.1 | As a **system**, I want to build and inject the resume anchor (scene type, last line, POV, tone) on resume after any pause so that the narrator continues seamlessly | 3 | High | 34 |

**Acceptance Criteria - S-5.3.1:**
```gherkin
Feature: Resume Anchor

Scenario: Build the resume anchor on pause
  Given the session pauses (a player moment, or save/load)
  Then a resume anchor is built from the scene type, the last line, the POV, and the tone

Scenario: Inject on resume for seamless continuation
  Given a paused session that is resumed
  When the narrator resumes
  Then the resume anchor is injected into the narrator prompt
  And narration continues seamlessly rather than restarting the beat
```

> **Technical Notes E5.3:**
> - **Business Logic:**
>   - The **resume anchor** is built from the session's persisted `resume_anchor` (**scene type · last line · POV · tone**) and **injected on `narrator_resumes` after any pause** (player moment, save/load).
>   - It is the **continuity seam** for E1.2 loop-state persistence: resume restores the exact loop position **and** re-establishes narrative voice/tone so the beat continues rather than restarts.
> - **Reference:** ADR 0016/0012.

---

## EPIC E6: Player Input & Delivery Channel

### E6.1 — Input + Sourced Delivery

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-6.1.1 | As a **player**, I want to give prose input with delivery sourced (prose → optional tone tag → narrator infer + ask-when-ambiguous) so that my emotional delivery is captured at the source and never decoded from bare text | 5 | High | 32 |

**Acceptance Criteria - S-6.1.1:**
```gherkin
Feature: Player Input and Sourced Delivery

Scenario: Delivery sourced from prose
  Given I write prose input that includes my delivery (e.g. "'Fine,' I say, forcing a smile")
  When I submit it
  Then the recorder commits surface and true_state from the sourced delivery
  And my emotional delivery is captured at the source, never decoded from bare text

Scenario: Optional tone tag
  Given I want precision without writing delivery into the prose
  When I attach an optional tone tag to my input
  Then the delivery is taken from the tag

Scenario: Infer with ask-when-ambiguous
  Given I submit bare dialogue with a genuinely ambiguous read
  When the narrator cannot resolve the delivery
  Then it proposes a surface
  And only interrupts to ask me to confirm when it cannot resolve it on its own
```

> **Technical Notes E6.1:**
> - **Business Logic:**
>   - **Player delivery is SOURCED, not decoded** — text alone is ambiguous, so delivery is fixed at the source three ways, in order of preference: **prose** (the human writes the delivery in) → **optional tone tag** (a lightweight hint) → **narrator infer + ask-when-ambiguous** (propose a surface; interrupt only when genuinely unresolvable).
>   - The **recorder commits `surface`/`true_state` from the sourced delivery** (it is the player's input into the two-layer record of E3.2).
>   - **NPC delivery needs no decode** — the NPC agent generated its line *with intent* and reports its own delivery; only the **human** and the **observed surface** require the sourcing/legibility machinery.
> - **Reference:** ADR 0010.

---

## Sprint Roadmap

### Sprint 26: Forking a Session (E1.1)
```
Sprint 26 (Week 26):
├── S-1.1.1: Start a new session — deep-copy template, seed edges from priors
└── Test: fork is atomic; template never mutated; player has no outgoing edges
```

### Sprint 27: Multi-Save & Loop State (E1.1 + E1.2)
```
Sprint 27 (Week 27):
├── S-1.1.2: Multiple independent saves (name/list/load/reset/delete)
├── S-1.2.1: Persist loop state (state_node, position, counters, anchor, clock)
└── Test: resume restores exact loop position
```

### Sprint 28: The Spine (E2.1)
```
Sprint 28 (Week 28):
├── S-2.1.1: State machine drives deterministic transitions (handoff-routed)
├── S-2.1.2: In-loop sequencing (recorder → appraisal; rupture in-scene / drift batched)
└── Test: appraisal slot reads only the committed projected surface
```

### Sprint 29: Prose Call (E3.1)
```
Sprint 29 (Week 29):
├── S-3.1.1: Narrator prompt assembly (registry-driven slots)
├── S-3.1.2: Structured prose output (prose · handoff · elapsed bucket)
└── Test: handoff is the prose output; malformed output retried then surfaced
```

### Sprint 30: Mesh Rule & Recorder Record (E3.1 + E3.2)
```
Sprint 30 (Week 30):
├── S-3.1.3: Mesh-awareness rule enforced (atmosphere only; reads hedged)
├── S-3.2.1: Two-layer record (surface + per-char true_state + witnesses + pov_anchor)
└── Test (leak guard): a "read surface only" path cannot reach true_state
```

### Sprint 31: Recorder Safety & Legibility (E3.2)
```
Sprint 31 (Week 31):
├── S-3.2.2: Hedged-attribution validation + knowledge_boundary block, via review gate
├── S-3.2.3: Legibility derivation (base_opacity × intensity × awareness/mask × register)
└── Test (leak guard): unhedged "is sad"/"is lying" rejected at any model tier
```

### Sprint 32: POV Projection, Display & Player Input (E4.1 + E6.1)
```
Sprint 32 (Week 32):
├── S-4.1.1: Per-NPC projection (witness → fidelity → surface → decode → boundary)
├── S-4.1.2: Display in the scene POV contract (anchor interiority display-only)
├── S-6.1.1: Player input with sourced delivery (prose → tone tag → infer/ask)
└── Test (leak guard): no NPC excerpt carries another's interiority or hidden facts
```

### Sprint 33: Memory & the Clock (E5.1 + E5.2)
```
Sprint 33 (Week 33):
├── S-5.1.1: Immediate context + scene summaries + chapter logs
├── S-5.1.2: Lorebook keyword injection (NPC clamped by knowledge_boundary)
├── S-5.2.1: Word-budget clock (warning · 1.6× hard override · chapter word_cap)
└── Test: NPC lorebook injection withholds out-of-boundary facts
```

### Sprint 34: Boundary Events & Resume (E5.2 + E5.3)
```
Sprint 34 (Week 34):
├── S-5.2.2: BEAT_DONE judge + elapsed-time sourcing (human-confirmable)
├── S-5.2.3: SCENE_DONE/CHAPTER_DONE — batched drift, decay on real gap, card swap
├── S-5.3.1: Build + inject the resume anchor
└── Phase 5 regression + end-to-end playthrough (narrator → player → narrator)
```

---

## Definition of Done (DoD)

See the [program DoD](./README.md#7-global-definition-of-done-dod). Phase-5 emphasis:

- [ ] **Leak-guard negative tests** pass for **POV projection** (no NPC excerpt carries another character's `true_state` or any out-of-`knowledge_boundary` fact) and for the **recorder** (a "read `surface` only" path physically cannot reach `true_state`; unhedged "is sad"/"is lying" rejected at any model tier).
- [ ] **Structured handoff** is the prose call's output (no separate classifier); malformed structured output is retried within a bound, then surfaced as failed — never trusted.
- [ ] **Deterministic in-loop sequencing** verified: recorder commits before appraisal; ruptures apply in-scene while ordinary drift batches to `SCENE_DONE`; boundary events fire their batched subsystems in fixed order.
- [ ] **Session fork** is atomic and never mutates the authoring template; **multi-save / reset / delete** are owner-scoped and independent.
- [ ] **Save/resume** restores the exact loop position (state_node, position, word counts, nudge level, narrative clock) and continues from the resume anchor rather than restarting the beat.
- [ ] **Word-budget clock** enforces warning → 1.6× hard override → chapter `word_cap` wrap; **decay/gap-drift fire only on a real gap (days+)** and not on `continuous`/filler.
- [ ] Every narrator turn (prose + recorder sub-call) and every projection/judge call is logged to the call log with role, cost, latency, and status.

---

## Success Metrics — Phase 5

| Metric | Target | Measurement |
|--------|--------|-------------|
| Context-isolation leaks (projection + recorder) | 0 | Negative tests: no `true_state` / hidden fact reaches an NPC excerpt or the public surface |
| Hedged-attribution enforcement | 100% | Every unhedged mental-state assertion rejected before commit, on any model tier |
| Handoff determinism | 100% | Every turn routed by the prose call's structured handoff; no separate classifier pass |
| Recorder-before-appraisal ordering | 100% | In-loop sequencing tests show the record committed before any appraisal read |
| Save/resume fidelity | 100% | Resumed sessions restore the exact loop position and continue (not restart) the beat |
| Boundary correctness on narrative time | 0 false fires | Decay/gap-drift fire only on `days+` gaps; `continuous`/filler boundaries move nothing by time |
| Pacing enforcement | 100% | Beats land within the ceiling (warning → 1.6× hard override → break-glass); chapter `word_cap` forces a wrap |

---

## Risk Register — Phase 5

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Appraisal peeks at omniscient truth (sequence violation) | Critical | Medium | Recorder-first invariant enforced by the state machine; negative test that appraisal can read only the committed projected surface |
| A character's `true_state` leaks into an NPC excerpt or the public surface | Critical | Medium | Separate `true_state` layer + projection pipeline + structural hedged-attribution validator; explicit leak-guard negative tests |
| Weak model emits an unhedged mental-state claim | High | Medium | Hedged-attribution is structural (model-independent) + review gate as the fidelity floor; minor NPCs only react to a pre-framed surface |
| Handoff misclassification stalls the loop | High | Medium | Handoff is the prose call's structured output (validated/retried); player-inaction timer escalates (fill → atmosphere → "Continue / Skip / Direct?") |
| Resume produces a discontinuity (beat restarts / tone resets) | Medium | Medium | Build + inject the resume anchor (scene type · last line · POV · tone); save restores exact loop state; resume tests |
| Two-call narrator turn drives cost/latency too high | High | High | Model-role tiering, block caching within a scene, progressive streaming, per-beat spend visibility (full orchestration in Phase 6) |
| Decay erodes relationships across filler chapters | Medium | Low | Elapsed bucket gating — decay/gap-drift only on `days+`; `continuous`/filler changes nothing by time |
| A half-applied turn or boundary is observed after reload | High | Low | Advance loop state only at committed boundaries; fork and boundary commits are atomic/transactional |

---

*Document Version: 1.0 · Author: Zulfikar Hidayatullah · Created: June 2026*
