# Phase 4: Story Structure & Prompting — Directed Interactive Novel Engine
## Directed Interactive Novel Engine (DINE)

**Timeline:** ~1.5 Months (6 Sprints)
**Sprint Duration:** 1 Week
**Team Size Recommendation:** 1 Full-stack Dev (+ optional QA)
**Depends on:** Phase 1 (schema, LLM client, review-gate foundation, seeded prompt blocks) + Phase 3 (characters must exist for `nudge_target` / `pov_anchor`)
**Governing ADRs:** 0019 (outline compilation), 0015 (beat document + boundaries), 0008 (psychological nudge), 0020 (prompt-block registry), 0009 (POV projection)

> Goal: turn a free-text outline into structured `chapters / scenes / beats` (LLM-assisted, through the review gate) — or let the author build that structure entirely by hand. Author the beat document (`intent` / `goal` / `word_budget` / optional `nudge_target`) and the scene/chapter config (`pov_mode` / `pov_anchor` / `tone` / `elapsed_bucket`; `pov_default` / `outline` / `word_cap`). Derive a leak-checked `nudge` from each beat's omniscient intent against the target's `knowledge_boundary`, and manage the `prompt_blocks` registry + prompting settings. After this phase a story has a runnable structure and a directing layer — but the narrator loop that consumes it lands in Phase 5.

---

## Product Backlog Overview

### Epic Summary

| Epic ID | Epic Name | Priority | Story Points | Sprints |
|---------|-----------|----------|--------------|---------|
| E1 | Outline Compilation | Critical | 16 | 20–21 |
| E2 | Manual Authoring Path | High | 5 | 21 |
| E3 | Beat Document & Scene Config | Critical | 13 | 22 |
| E4 | Nudge Derivation (author-side) | High | 14 | 23–24 |
| E5 | Prompt Block Registry & Prompting Settings | Medium | 14 | 24–25 |

**Total Estimated:** ~62 Story Points

---

## EPIC E1: Outline Compilation

### E1.1 — Free Outline → Structured Tree

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.1.1 | As an **author**, I want to store a free-text outline verbatim and compile it (LLM-assisted) into draft chapters/scenes/beats through the review gate so that I get the structured hierarchy without hand-building every row | 8 | Critical | 20 |
| S-1.1.2 | As an **author**, I want the compiler to infer the scene breakdown, per-beat goal strings, suggested word budgets, elapsed buckets, and nudge targets — all human-confirmable — so that the draft is useful but I stay in control of the load-bearing signals | 5 | High | 21 |

**Acceptance Criteria - S-1.1.1:**
```gherkin
Feature: Store and Compile a Free Outline

Scenario: Raw outline is stored verbatim
  Given I am authoring a story
  When I save a free-text outline:
    | Field    | Value                                                                                  |
    | raw_text | "Ch 1: Luna and the player meet in the workshop; she deflects the gloves question; Vixia watches" |
  Then the outline is stored exactly as written in chapter_outlines.raw_text
  And its status is "draft"
  And the raw outline is never injected into any runtime agent

Scenario: Compile a stored outline into a draft structured tree
  Given a chapter_outline with status "draft"
  When I run the outline compile
  Then the compiler proposes a structured tree of chapters → scenes → beats
  And each proposed beat carries an intent, a goal, a word_budget, and an optional nudge_target
  And each proposed scene carries pov_mode, pov_anchor, tone, and an elapsed_bucket
  And a review item is created with producer_type "outline_compile" referencing the proposed tree
  And nothing is committed to chapters/scenes/beats until I review it

Scenario: Accept the compile commits the authoring rows
  Given a pending "outline_compile" review item
  When I accept the proposal
  Then chapters, scenes, and beats rows are committed for the story
  And the originating chapter_outline status becomes "compiled"
  And the chapter_outline links to the compiled chapter (an outline may span chapters)

Scenario: Edit before commit
  Given a pending "outline_compile" review item
  When I edit the proposed tree (e.g. split a scene, reword a goal) and commit
  Then the edited structure is committed, not the original proposal
  And the review item is marked edited

Scenario: Reject the compile commits nothing
  Given a pending "outline_compile" review item
  When I reject it
  Then no chapter/scene/beat rows are created
  And the chapter_outline remains status "draft" for a later re-run
```

**Acceptance Criteria - S-1.1.2:**
```gherkin
Feature: Compiler Inference vs Author-Owned Signals

Scenario: The compiler infers the structure and supporting signals
  Given a chapter_outline being compiled
  Then the compiler infers, as human-confirmable drafts:
    | Inferred signal | Notes                                                        |
    | scene breakdown | how the chapter splits into scenes                           |
    | goal            | per-beat satisfaction anchor string                          |
    | word_budget     | suggested from the chapter word_cap and the beat count       |
    | elapsed_bucket  | with elapsed_source defaulted to "narrator_inferred"         |
    | nudge_target    | which beat (if any) frames pressure onto a character         |
  And every inferred value is surfaced for edit at the review gate

Scenario: Load-bearing authorial signals are drafted but stay mine
  Given a compiled draft tree
  Then the omniscient intent per beat, the pov_default per chapter, and the chapter word_cap
       are presented as editable author-owned fields
  And the compiler may draft them but never finalizes them without my confirmation

Scenario: Nudge target left unresolved when no character fits
  Given an outline whose story has no suitable character for a beat
  When the compiler cannot resolve a nudge_target
  Then nudge_target is left null for me to fill once characters exist
  And the beat is still valid without a nudge_target
```

> **Technical Notes E1.1:**
> - **Business Logic:**
>   - The author's free outline is stored verbatim in `chapter_outlines.raw_text`; it is an authoring artifact (like a bible) and is **NEVER injected at runtime** — only the **compiled beats** reach the loop.
>   - Outline compilation is the `propose → review → commit` pattern applied to beats: the LLM (`compiler` role) **proposes** a structured tree; the human **accepts / edits / rejects** before any `chapters` / `scenes` / `beats` row is committed. It enqueues as `producer_type = outline_compile` on the shared review gate.
>   - **Inferred + human-confirmable:** scene breakdown, per-beat `goal`, suggested `word_budget` (from chapter `word_cap` ÷ beat count), `elapsed_bucket` (with `elapsed_source = narrator_inferred`), and which beat carries a `nudge_target`.
>   - **Stays the author's (load-bearing):** the omniscient `intent` per beat, the `pov_default` per chapter, and the chapter `word_cap` — drafted, but always surfaced for edit and never auto-finalized.
>   - `nudge_target` references a **character**; it is nullable until resolved at review (a character may not yet exist).
>   - Outline compilation touches **no leak guard** — it produces author-side rows whose runtime exposure is gated elsewhere (the nudge leak-check, E4).
> - **Reference:** ADR 0019.

---

### E1.2 — Recompile From Source

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.2.1 | As an **author**, I want to re-run the compile when the outline changes (an outline may span chapters) so that the structured tree stays in sync with my source | 3 | Medium | 21 |

**Acceptance Criteria - S-1.2.1:**
```gherkin
Feature: Recompile a Changed Outline From Source

Scenario: Editing the raw outline enables a fresh compile
  Given a chapter_outline previously compiled (status "compiled")
  When I edit its raw_text and re-run the outline compile
  Then a new "outline_compile" review item is proposed from the updated source
  And the existing committed beats are not altered until I accept the new proposal

Scenario: An outline that spans multiple chapters
  Given a single chapter_outline whose raw_text covers more than one chapter
  When it is compiled
  Then each compiled chapter is linked back to the same source outline via chapter_id
  And re-running the compile re-derives the full span from the one raw_text source

Scenario: Re-compile keeps the source as the system of record
  Given the structured tree has drifted from the raw_text
  When I recompile from source and accept
  Then the structured tree reflects the current raw_text
  And the raw_text remains stored verbatim and is still never injected at runtime
```

> **Technical Notes E1.2:**
> - **Business Logic:**
>   - `chapter_outlines.status` lifecycle: `draft` (stored, not yet compiled) → `compiled` (committed beats exist) — and `manual` for an outline whose beats were authored directly with no compile (see E2).
>   - A recompile re-runs `propose → review → commit` **from the stored `raw_text`** (the source of record); committed beats change only on accept.
>   - An outline may **span chapters**: `chapter_outlines.chapter_id` is set per compiled chapter, all referencing the one `raw_text`.
>   - The `raw_text` is preserved across recompiles and is **never injected at runtime**; only compiled beats reach the narrator loop.
> - **Reference:** ADR 0019.

---

## EPIC E2: Manual Authoring Path

### E2.1 — Author Structure Directly

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.1.1 | As an **author**, I want to author chapters/scenes/beats directly via forms (skip the compile) so that I can fully structure a story by hand — manual and compiled beats must be indistinguishable downstream | 5 | High | 21 |

**Acceptance Criteria - S-2.1.1:**
```gherkin
Feature: Manual Structure Authoring

Scenario: Build the hierarchy by hand with no compile
  Given I am authoring a story
  When I create chapters, scenes, and beats directly through forms
  Then the rows are committed without any outline_compile step
  And I populate intent, goal, word_budget, and optional nudge_target on each beat by hand
  And I populate pov_mode, pov_anchor, tone, and elapsed_bucket on each scene by hand

Scenario: A manual outline is recorded as such
  Given I authored beats directly without compiling an outline
  Then any associated chapter_outline carries status "manual"

Scenario: Manual and compiled beats are indistinguishable downstream
  Given two beats, one authored manually and one produced by an outline_compile
  When the narrator loop later consumes them
  Then they carry the same shape (intent, goal, word_budget, nudge_target)
  And nothing in a committed beat records how it was authored
  And the loop cannot tell which path produced it
```

> **Technical Notes E2.1:**
> - **Business Logic:**
>   - The full manual path is **first-class, not a fallback** — the author may skip the compile entirely and write `chapters` / `scenes` / `beats` directly through the same forms the review gate edits.
>   - Manual and compiled beats are **indistinguishable downstream**: a committed beat stores no provenance flag, and the narrator loop cannot tell how a beat was authored.
>   - A directly-authored outline record is marked `status = manual`; the manual path needs no LLM.
> - **Reference:** ADR 0019 §3.

---

## EPIC E3: Beat Document & Scene Config

### E3.1 — Beat Shape

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.1.1 | As an **author**, I want to author/edit beats (intent: omniscient free text never injected raw; goal: the satisfaction anchor; word_budget: the pacing clock; optional nudge_target) so that the engine has the pacing and goal signals it needs | 5 | Critical | 22 |

**Acceptance Criteria - S-3.1.1:**
```gherkin
Feature: Beat Document Authoring

Scenario: Author a beat with its four signals
  Given a scene under a chapter
  When I author a beat:
    | Field        | Value                                                                |
    | intent       | "Corner him about the night of the fire; she mustn't learn it was arson" |
    | goal         | "Luna presses him about that night"                                  |
    | word_budget  | 400                                                                  |
    | nudge_target | Luna                                                                 |
  Then the beat is stored with that intent, goal, word_budget, and nudge_target
  And the intent is treated as omniscient, author-side, and is never injected raw at runtime

Scenario: word_budget is the pacing clock
  Given a beat with word_budget 400
  Then word_budget is recorded as the per-beat pacing clock the runtime ladder reads later
  And it is the only per-beat pacing signal authored (the runtime level is not authored here)

Scenario: nudge_target is optional
  Given a beat that frames no pressure onto any character
  When I leave nudge_target empty
  Then the beat is valid with a null nudge_target
  And it will run pure self-simulation downstream

Scenario: goal is required as the satisfaction anchor
  Given I author a beat without a goal
  When I try to save
  Then I am told the goal (satisfaction anchor) is required
  And the beat is not saved
```

> **Technical Notes E3.1:**
> - **Business Logic:**
>   - Hierarchy: `story → chapters → scenes → beats`; a beat is the smallest authored unit.
>   - A beat carries `intent` (free-text, **omniscient**, author-side — never injected raw), `goal` (the satisfaction anchor for the runtime goal judge), `word_budget` (the per-beat pacing clock), and an optional `nudge_target` (the character, if any, pressure is framed onto).
>   - `intent` is omniscient/author-side and becomes a **leak-checked nudge** at runtime (E4) — the raw intent never crosses to a character.
>   - A beat's `level` (nudge rung) is **NOT authored** — it is set at runtime by the word-budget clock.
>   - A typical beat targets **0–1** characters with a nudge; everyone else runs pure self-simulation.
> - **Reference:** ADR 0015 / 0009.

---

### E3.2 — Scene & Chapter Config

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.2.1 | As an **author**, I want to author scene config (pov_mode, pov_anchor, tone, present characters, elapsed_bucket + its source) so that each scene declares its POV contract and any time gap | 5 | High | 22 |
| S-3.2.2 | As an **author**, I want to author chapter config (pov_default, outline, word_cap as the outer hard pacing flag) so that chapters have a default POV and a wrap trigger | 3 | High | 22 |

**Acceptance Criteria - S-3.2.1:**
```gherkin
Feature: Scene Configuration

Scenario: Declare a scene POV contract and time gap
  Given a chapter
  When I author a scene:
    | Field              | Value          |
    | pov_mode           | third_limited  |
    | pov_anchor         | Luna           |
    | tone               | tense          |
    | present_characters | [Luna, Vixia]  |
    | elapsed_bucket     | weeks          |
    | elapsed_source     | authored       |
  Then the scene stores its POV contract (pov_mode + pov_anchor) and its tone
  And the present characters are recorded as the default cast
  And the declared in-world gap is stored as elapsed_bucket with its elapsed_source

Scenario: elapsed_bucket constrained to the coarse vocabulary
  Given I author a scene
  When I set elapsed_bucket
  Then it must be one of: continuous, hours, days, weeks, months, longer
  And elapsed_source must be one of: authored, narrator_inferred, default

Scenario: No declared gap defaults to continuous
  Given a scene where I declare no time gap
  Then elapsed_bucket defaults to "continuous" with elapsed_source "default"
  And the scene is treated as flowing on with no gap

Scenario: pov_anchor names a present character
  Given a scene with pov_mode third_limited
  When I set pov_anchor to a character not in present_characters
  Then I am warned the anchor should be a present character
```

**Acceptance Criteria - S-3.2.2:**
```gherkin
Feature: Chapter Configuration

Scenario: Author chapter defaults and the wrap trigger
  Given a story
  When I author a chapter:
    | Field       | Value                          |
    | number      | 1                              |
    | title       | "The Workshop"                 |
    | pov_default | third_limited/Luna             |
    | outline     | "They meet; the gloves question" |
    | word_cap    | 3000                           |
  Then the chapter stores its pov_default as the POV scenes inherit by default
  And word_cap is recorded as the outer hard pacing flag that forces a chapter wrap

Scenario: Scenes inherit the chapter pov_default
  Given a chapter with pov_default "third_limited/Luna"
  When I add a scene without overriding the POV
  Then the scene's POV contract defaults from the chapter pov_default

Scenario: Chapter numbers are unique within a story
  Given a story already has a chapter number 1
  When I create another chapter with number 1
  Then I am told the chapter number must be unique within the story
  And the duplicate is not saved
```

> **Technical Notes E3.2:**
> - **Business Logic:**
>   - A **scene** declares the POV contract (`pov_mode`, `pov_anchor`), `tone`, the default `present_characters` cast, and the in-world time gap entering it (`elapsed_bucket` + `elapsed_source`). `pov` and `tone` are **inherited by beats** from the scene (not re-authored per beat).
>   - `elapsed_bucket` ∈ {`continuous`, `hours`, `days`, `weeks`, `months`, `longer`}; `elapsed_source` ∈ {`authored`, `narrator_inferred`, `default`}. Absent any declared gap, the bucket defaults to `continuous` with source `default`.
>   - A **chapter** carries `pov_default` (the POV scenes inherit), `outline` (the pantser route), and `word_cap` — the **outer hard pacing flag** that forces a chapter wrap.
>   - The beat `level` is still set at runtime by the word-budget clock — never authored here.
>   - Chapter `number` is unique within a story; scene `number` is unique within a chapter.
> - **Reference:** ADR 0015 / 0009.

---

## EPIC E4: Nudge Derivation (author-side)

### E4.1 — Derive & Hand-Author Nudges

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.1.1 | As an **author**, I want a nudge derived from a beat's intent + the target's knowledge_boundary (compiled and leak-checked at the assembler boundary) through the review gate so that omniscient intent never crosses to the character — only a bounded internal-impulse nudge does | 8 | High | 23 |
| S-4.1.2 | As an **author**, I want to hand-author a bounded nudge directly so that I can write the internal impulse myself — it still passes the same leak-check | 3 | Medium | 23 |
| S-4.1.3 | As an **author**, I want to set the nudge kind (goal/attention/mood/relational-impulse/suppression) and verify internal-impulse framing so that the nudge reads as the character's own urge, never a stage direction | 3 | Medium | 24 |

**Acceptance Criteria - S-4.1.1:**
```gherkin
Feature: Derived Nudge From Beat Intent

Scenario: Compile a bounded nudge from omniscient intent
  Given a beat with intent "Corner him about the fire; she mustn't learn it was arson"
  And a nudge_target character Luna with a known knowledge_boundary
  When I run the nudge derivation
  Then the compiler proposes a nudge for Luna with source "derived"
  And the nudge text is internal-framed (e.g. "His dodging about that night itches at you")
  And a review item is created with producer_type "nudge_compile"
  And nothing is injected until I review it

Scenario: Omniscient intent never crosses the boundary
  Given a derived nudge proposed for Luna
  Then the raw omniscient intent is not present in the nudge text
  And the proposal is leak-checked against Luna's knowledge_boundary at the assembler boundary
  And only the bounded internal-impulse nudge is what can reach the character

Scenario: A nudge that would leak the boundary is blocked
  Given a beat whose intent references a fact outside the target's knowledge_boundary
  When the nudge is derived
  Then any content that crosses the knowledge_boundary is rejected before commit
  And I am required to revise or reject the proposal
  And no leaking nudge is committed

Scenario: Level is not authored at derivation
  Given a derived nudge proposal
  Then it carries no fixed runtime level chosen by me
  And the level is set later by the word-budget clock at runtime
```

**Acceptance Criteria - S-4.1.2:**
```gherkin
Feature: Hand-Authored Bounded Nudge

Scenario: Write the internal impulse directly
  Given a beat targeting Luna
  When I hand-author a nudge:
    | Field  | Value                                          |
    | text   | "You keep circling back to that night"         |
    | target | Luna                                           |
  Then the nudge is stored with source "authored"
  And it is still submitted to the same knowledge_boundary leak-check as a derived nudge

Scenario: A hand-authored nudge that leaks is blocked
  Given I hand-author a nudge that states a fact the target cannot know
  When it is leak-checked
  Then it is rejected before commit with the boundary it violates
  And I must revise it to pass the same check a derived nudge faces

Scenario: Both modes converge on one guard
  Given one derived nudge and one hand-authored nudge for the same target
  Then both pass through the identical knowledge_boundary leak-check at the assembler boundary
  And the only difference recorded is source (derived vs authored)
```

**Acceptance Criteria - S-4.1.3:**
```gherkin
Feature: Nudge Kind and Internal-Impulse Framing

Scenario: Set the nudge kind
  Given a nudge being authored or reviewed
  When I set its kind
  Then kind is one or more of: goal, attention, mood, relational-impulse, suppression
  And the chosen kind drives how the impulse is framed

Scenario: Internal-impulse framing is verified
  Given a nudge text
  Then it must read as the character's own urge, mood, preoccupation, or goal
       (e.g. "you find yourself wanting to…")
  And a text framed as an external stage direction (e.g. "make her confront him") is flagged
  And I must reframe it as an internal impulse before it can be committed

Scenario: A suppression nudge still reads as the character's own
  Given a nudge with kind "suppression"
  Then it frames the character's own urge to hold back, not an instruction to the character
```

> **Technical Notes E4.1:**
> - **Business Logic:**
>   - The `nudge` is the **ONLY** authorial channel into an NPC; everything else a character does is self-simulation. Raw omniscient `intent` **never crosses** the boundary.
>   - **Two modes, one guard:** *derived* (beat `intent` + target `knowledge_boundary` → compiled, bounded nudge on the `nudge_compiler` role) and *hand-authored* (the human writes the bounded internal nudge directly). **Both** pass the same `knowledge_boundary` leak-check at the assembler boundary, through the shared review gate (`producer_type = nudge_compile`).
>   - This is the **second leak guard** (authorial / plot omniscience) — orthogonal to the awareness-fold and POV-projection guards.
>   - Framing is **internal-impulse**: every nudge reads as the character's *own* urge / mood / preoccupation / goal, never as a stage direction.
>   - `kind` ∈ {`goal`, `attention`, `mood`, `relational-impulse`, `suppression`} (1+); `source` ∈ {`derived`, `authored`}. `level` is **not** authored — it is set at runtime by the word-budget clock.
> - **Reference:** ADR 0008 / 0015 §2.

---

## EPIC E5: Prompt Block Registry & Prompting Settings

### E5.1 — Registry Management

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.1.1 | As an **author**, I want to view/edit prompt blocks (key, agent, section, label, purpose, source_producers, compile_instruction, leak_rules, order_index, active) so that prompt assembly is data-driven and auditable | 5 | Medium | 24 |
| S-5.1.2 | As an **author**, I want a human-readable block reference rendered from the same registry rows so that the definition can never drift between code and docs | 3 | Low | 25 |
| S-5.1.3 | As an **author**, I want to reorder/toggle blocks (and later set per-story overrides) so that I can shape assembly without code changes | 3 | Low | 25 |

**Acceptance Criteria - S-5.1.1:**
```gherkin
Feature: Prompt Block Registry Management

Scenario: View the seeded registry
  Given the prompt-block registry seeded in Phase 1
  When I open the registry
  Then I see each prompt_block with its key, agent, section, label, purpose,
       source_producers, compile_instruction, leak_rules, order_index, and active flag

Scenario: Edit a block definition
  Given a prompt_block "MASKS"
  When I edit its purpose, compile_instruction, or leak_rules and save
  Then the changes are stored on that row
  And the same rows drive both prompt assembly and the rendered block reference

Scenario: Key is unique and constrained
  Given I edit a block's key
  Then key must be one of the known engine keys (IDENTITY, SELF, SNAPSHOT, MASKS,
       DIRECTIVES, NUDGE, SCENE_RULES, SCENE_EXCERPT, POV_CONTRACT, MESH_AWARENESS,
       BEAT, DIRECTOR_STATE, LOREBOOK, SCENE_STATE, RESUME_ANCHOR)
  And agent must be one of: narrator, npc, both
  And section must be one of: system, user
```

**Acceptance Criteria - S-5.1.2:**
```gherkin
Feature: Block Reference Rendered From the Registry

Scenario: One definition, two surfaces
  Given the prompt_blocks registry rows
  When the human-readable block reference is rendered
  Then it is generated from the same rows that drive assembly
  And it shows each block's label, purpose, source_producers, and leak_rules

Scenario: A definition cannot drift between code and docs
  Given I edit a block's purpose in the registry
  When I view the block reference
  Then the reference reflects the edited definition without a separate doc update
  And there is no second place where the definition could disagree
```

**Acceptance Criteria - S-5.1.3:**
```gherkin
Feature: Reorder and Toggle Blocks

Scenario: Reorder blocks within a section
  Given several active blocks in the system section
  When I change their order_index
  Then assembly selects active blocks for an agent ordered by order_index within section

Scenario: Toggle a block off
  Given an active prompt_block
  When I set is_active to false
  Then it is excluded from assembly
  And it still appears in the registry as inactive

Scenario: Global now, per-story override later
  Given the registry is global (no story_id)
  Then changes apply across stories by default
  And a per-story override is supported as a later capability without code changes
```

> **Technical Notes E5.1:**
> - **Business Logic:**
>   - `prompt_blocks` is the **single source of truth** driving assembly (block selection, order, fold, and leak-rule enforcement) **and** rendering the human-readable block reference — from the **same rows**, so the definition can never drift between code and docs.
>   - Each block carries: `key`, `agent` (`narrator`|`npc`|`both`), `section` (`system`|`user`), `label`, `purpose`, `source_producers`, `compile_instruction`, `leak_rules`, `order_index`, `is_active`.
>   - Assembly selects **active** blocks for an agent, ordered by `order_index` within `section`; toggling `is_active` includes/excludes a block with no code change.
>   - The registry is **global** (no `story_id`); a per-story override is a later, additive capability.
> - **Reference:** ADR 0020.

---

### E5.2 — Prompting Safety

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.2.1 | As a **system**, I want leak_rules treated as enforcement contracts naming existing guards only so that editing a block cannot invent a new guard or silently remove a required one (a warning is raised) | 3 | Medium | 25 |

**Acceptance Criteria - S-5.2.1:**
```gherkin
Feature: leak_rules as Enforcement Contracts

Scenario: leak_rules name existing guards only
  Given I edit a block's leak_rules
  Then each entry must be one of: awareness_fold, knowledge_boundary, hedged_attribution,
       own_perspective_only, omniscient_authoring, none
  And any value outside that set is rejected — the registry cannot invent a new guard

Scenario: Removing a required guard raises a warning
  Given a block (e.g. NUDGE) whose leak_rules include a required guard
       (e.g. omniscient_authoring + knowledge_boundary)
  When I remove a required guard from its leak_rules
  Then a warning is raised that a required guard would be silently removed
  And the change is surfaced for explicit confirmation rather than applied quietly

Scenario: leak_rules drive enforcement at assembly
  Given a block with leak_rules
  When the assembler folds that block
  Then the named guard is applied as the enforcement contract before the block enters the message
  And a block whose content violates its named guard is not allowed through
```

> **Technical Notes E5.2:**
> - **Business Logic:**
>   - `leak_rules` are the registry's **teeth**: each names an **existing** guard and is applied as the enforcement contract for that block before it enters a message. The registry **names which guard applies where**; it invents **none**.
>   - `leak_rules` ∈ {`awareness_fold`, `knowledge_boundary`, `hedged_attribution`, `own_perspective_only`, `omniscient_authoring`, `none`} — any value outside this set is rejected.
>   - Editing a block **cannot invent a new guard** nor **silently remove a required one** — removing a required guard raises a **warning** and requires explicit confirmation.
>   - The registry is **global** with a later per-story override; safety stays exactly the established guard set.
> - **Reference:** ADR 0020.

---

## Sprint Roadmap

### Sprint 20: Outline Compile Core (E1.1)
```
Sprint 20 (Week 20):
├── S-1.1.1: Store free outline verbatim + compile to draft tree via review gate
└── Test: raw_text never injected; nothing committed before accept
```

### Sprint 21: Inference, Recompile & Manual Path (E1.1 + E1.2 + E2.1)
```
Sprint 21 (Week 21):
├── S-1.1.2: Compiler infers scenes/goal/word_budget/elapsed/nudge_target (confirmable)
├── S-1.2.1: Recompile from source (outline may span chapters)
├── S-2.1.1: Manual chapters/scenes/beats authoring (indistinguishable downstream)
└── Test: manual vs compiled beats carry identical shape
```

### Sprint 22: Beat Document & Scene/Chapter Config (E3)
```
Sprint 22 (Week 22):
├── S-3.1.1: Beat shape (intent / goal / word_budget / nudge_target)
├── S-3.2.1: Scene config (pov_mode / pov_anchor / tone / elapsed_bucket + source)
├── S-3.2.2: Chapter config (pov_default / outline / word_cap)
└── Test: pov/tone inheritance; level never authored
```

### Sprint 23: Nudge Derivation & Hand-Authoring (E4.1)
```
Sprint 23 (Week 23):
├── S-4.1.1: Derive leak-checked nudge from intent + knowledge_boundary via review gate
├── S-4.1.2: Hand-author a bounded nudge (same leak-check)
└── Test (negative): omniscient intent never crosses; leaking nudge blocked
```

### Sprint 24: Nudge Framing & Registry Management (E4.1 + E5.1)
```
Sprint 24 (Week 24):
├── S-4.1.3: Nudge kind + internal-impulse framing verification
├── S-5.1.1: View/edit prompt blocks (data-driven, auditable)
└── Test: stage-direction framing flagged; registry edits persist
```

### Sprint 25: Block Reference, Reorder & Prompting Safety (E5.1 + E5.2)
```
Sprint 25 (Week 25):
├── S-5.1.2: Block reference rendered from the same registry rows
├── S-5.1.3: Reorder/toggle blocks (per-story override later)
├── S-5.2.1: leak_rules as enforcement contracts (warning on required-guard removal)
└── Phase 4 regression + hardening
```

---

## Definition of Done (DoD)

See the [program DoD](./README.md#7-global-definition-of-done-dod). Phase-4 emphasis:

- [ ] Outline compile runs `propose → review → commit`; `chapter_outlines.raw_text` is stored verbatim and **never injected at runtime** — explicit negative test asserts the raw outline cannot reach any agent.
- [ ] Compiler-inferred signals (scenes, `goal`, `word_budget`, `elapsed_bucket`, `nudge_target`) are all human-confirmable; load-bearing fields (`intent`, `pov_default`, `word_cap`) stay author-owned.
- [ ] Recompile from source keeps the structured tree in sync; an outline may span chapters; committed beats change only on accept.
- [ ] Manual and compiled beats are **indistinguishable** downstream — test asserts a beat records no provenance.
- [ ] **Nudge leak-check on every nudge:** both derived and hand-authored nudges pass the `knowledge_boundary` check at the assembler boundary; omniscient `intent` never crosses — explicit negative test asserts a leaking nudge is blocked before commit.
- [ ] Nudge `level` is never authored (set at runtime by the word-budget clock); nudges read as internal impulse, not stage direction.
- [ ] `prompt_blocks` registry drives assembly **and** the rendered block reference from one row set; `leak_rules` accept only the existing guard names; removing a required guard raises a warning.

---

## Success Metrics — Phase 4

| Metric | Target | Measurement |
|--------|--------|-------------|
| Raw-outline non-injection | 0 leaks | Negative test: `raw_text` never appears in any assembled prompt |
| Compile usefulness | > 80% of beats accepted/edited (not rejected) | Accepted+edited / total compiled beats at review |
| Author signal retention | 100% | `intent` / `pov_default` / `word_cap` always editable before commit |
| Manual/compiled parity | 0 distinguishing fields | Test: a committed beat carries no authoring-provenance marker |
| Nudge leak-check coverage | 100% of nudges | Every derived and hand-authored nudge runs the `knowledge_boundary` check |
| Nudge leak blocks | 0 leaking nudges committed | Negative tests: boundary-crossing nudges rejected before commit |
| Registry/doc parity | 0 drift | Block reference rendered solely from `prompt_blocks` rows |

---

## Risk Register — Phase 4

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Raw outline or omniscient `intent` injected at runtime | Critical | Medium | `raw_text`/`intent` are author-side only; only compiled beats and leak-checked nudges reach the loop; explicit negative tests |
| Nudge leaks knowledge beyond the target's `knowledge_boundary` | Critical | Medium | Both modes pass the same boundary leak-check at the assembler boundary; review-gate floor; negative tests block leaking nudges |
| Compiler over-reaches into author-owned signals | High | Medium | Inferred values are drafts only; `intent` / `pov_default` / `word_cap` always surfaced for edit and never auto-finalized |
| Manual and compiled beats diverge downstream | High | Low | No provenance stored on beats; parity test in the narrator-loop consumer contract |
| Nudge framed as a stage direction (puppeting) | Medium | Medium | Internal-impulse framing verified; external framing flagged and must be reframed before commit |
| Editing a block silently removes a required guard | High | Low | `leak_rules` constrained to existing guard names; warning + explicit confirmation on required-guard removal |
| `nudge_target` / `pov_anchor` set before the character exists | Medium | Medium | Nullable until resolved at review; Phase 3 dependency ordering; compiler leaves them null when no character fits |

---

*Document Version: 1.0 · Author: Zulfikar Hidayatullah · Created: June 2026*
