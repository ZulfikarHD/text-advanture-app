# Phase 2: Story & World Management — Directed Interactive Novel Engine
## Directed Interactive Novel Engine (DINE)

**Timeline:** ~1.25 Months (5 Sprints)
**Sprint Duration:** 1 Week
**Team Size Recommendation:** 1 Full-stack Dev (+ optional QA)
**Depends on:** Phase 1 (Foundation, Auth & App Shell)
**Governing ADRs:** 0012 (persistence), 0013 (authoring/compile pipeline), 0019 (outline compilation — partial), 0005 (trigger taxonomy), 0015 (beat document + boundaries), 0014 (internal-state schema), 0017 (LLM/OpenRouter)

> Goal: give the author the surfaces to **create and manage stories**, the **lorebook**, the **reveal ledger**, and to **tune engine config** — and stand up the **per-story authoring workspace** that every later authoring phase plugs into. After this phase a story is a real, owned, importable/exportable container with world facts, load-bearing secrets, and per-story tunables; it can report whether it is play-ready. It still has no compiled characters or compiled structure — those land in Phases 3–4.

---

## Product Backlog Overview

### Epic Summary

| Epic ID | Epic Name | Priority | Story Points | Sprints |
|---------|-----------|----------|--------------|---------|
| E1 | Story Management | Critical | 27 | 7–9 |
| E2 | Authoring Workspace & Navigation | High | 6 | 7–9 |
| E3 | Lorebook Management | High | 10 | 9–10 |
| E4 | Reveal Ledger Management | High | 8 | 10–11 |
| E5 | Tunable Engine Config | Medium | 12 | 10–11 |

**Total Estimated:** ~63 Story Points

---

## EPIC E1: Story Management

### E1.1 — Story CRUD & Dashboard

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.1.1 | As an **author**, I want to create a new story (title, slug, description) so that I have a container for its characters, chapters, and lore | 3 | Critical | 7 |
| S-1.1.2 | As an **author**, I want to list, open, edit, and delete my stories from a dashboard so that I can manage all my projects in one place | 3 | Critical | 7 |
| S-1.1.3 | As an **author**, I want to duplicate a story so that I can fork a variant for experimentation without touching the original | 3 | Medium | 8 |

**Acceptance Criteria - S-1.1.1:**
```gherkin
Feature: Create a Story

Scenario: Author creates a story with valid details
  Given I am signed in as an author
  When I create a story with:
    | Field       | Value                                  |
    | title       | The Crystal Hollow                     |
    | slug        | the-crystal-hollow                     |
    | description | A wandering archivist and her ward     |
  Then the story is created and owned by me
  And it becomes the scoping root for its characters, chapters, scenes, beats, lorebook, and reveal ledger
  And its slug is unique among the stories I own

Scenario: Slug is derived when omitted
  Given I am creating a story titled "The Crystal Hollow"
  When I do not provide a slug
  Then a URL-safe slug is derived from the title

Scenario: Duplicate slug is rejected
  Given I already own a story with slug "the-crystal-hollow"
  When I create another story with slug "the-crystal-hollow"
  Then creation is rejected with a clear message that the slug is already in use
  And no story is created
```

**Acceptance Criteria - S-1.1.2:**
```gherkin
Feature: Story Dashboard

Scenario: List only my own stories
  Given I own several stories and another author owns others
  When I open my story dashboard
  Then I see only the stories I own
  And each entry shows at least its title, slug, and description

Scenario: Open and edit a story
  Given I own a story
  When I open it and change its title or description and save
  Then the story reflects the updated details
  And a slug change that collides with another of my stories is rejected

Scenario: Delete a story with confirmation
  Given I own a story
  When I delete it and confirm the action
  Then the story and its authoring data are removed
  And I can no longer open it

Scenario: Empty dashboard guides first creation
  Given I own no stories yet
  When I open the dashboard
  Then I am guided toward creating my first story rather than shown an unexplained empty screen
```

**Acceptance Criteria - S-1.1.3:**
```gherkin
Feature: Duplicate a Story

Scenario: Fork a variant for experimentation
  Given I own story "the-crystal-hollow" with characters, lorebook entries, reveal-ledger entries, and structure
  When I duplicate it
  Then a new story I own is created with a distinct, unique slug (e.g. "the-crystal-hollow-copy")
  And the copy contains the same authoring content as the original
  And no playthrough saves are copied
  And editing the copy never changes the original

Scenario: Duplicate resolves slug collisions
  Given I already own a story whose slug ends in "-copy"
  When I duplicate it again
  Then the new slug is made unique without overwriting any existing story
```

> **Technical Notes E1.1:**
> - **Business Logic:**
>   - A **story is the scoping root** for all authoring content — every character, chapter, scene, beat, lorebook entry, and reveal-ledger entry resolves to exactly one story.
>   - The authoring realm is **immutable at runtime**: story authoring data is the template a playthrough forks from, never edited mid-play.
>   - `slug` is URL-safe and **unique per owner**; it is derived from `title` when omitted. Required: `title`, `slug`. Optional: `description`.
>   - Delete is destructive and cascades to the story's authoring rows; it must ask for confirmation first and is **owner-scoped** (a non-owner is denied without leaking existence).
>   - Duplicate is a **deep copy of authoring rows only** (never save-realm playthroughs); the copy receives a fresh unique slug and is fully independent of the original.
>   - Create / duplicate / delete are **atomic**: each either completes fully or makes no change.
> - **Reference:** ADR 0012, 0017.

---

### E1.2 — Story Settings & Overview

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.2.1 | As an **author**, I want to configure per-story settings (default POV, model-role overrides, tunable rubric overrides) so that a story can deviate from global defaults | 5 | High | 8 |
| S-1.2.2 | As an **author**, I want a story overview (counts of characters/chapters/scenes/beats/lore/saves and play-readiness) so that I know what the story still needs | 3 | Medium | 8 |

**Acceptance Criteria - S-1.2.1:**
```gherkin
Feature: Per-Story Settings

Scenario: Override global defaults for one story
  Given I own a story
  When I set per-story settings:
    | Setting                | Value                              |
    | default POV            | third_limited                      |
    | model role override    | narrator_prose → a chosen slug     |
    | rubric override        | a severity-tier magnitude tweak    |
  Then those settings are saved as this story's config
  And they take precedence over the global defaults for this story only

Scenario: Unset settings fall back to global defaults
  Given a story with no override for a given setting
  When the engine resolves that setting for the story
  Then it uses the global default

Scenario: Model-role override resolution order
  Given a per-story model-role override for "narrator_prose"
  When a model is resolved for "narrator_prose" within this story
  Then the per-story override is used before the global default
```

**Acceptance Criteria - S-1.2.2:**
```gherkin
Feature: Story Overview

Scenario: Overview shows the authoring inventory
  Given I own a story with some authored content
  When I open its overview
  Then I see counts of characters, chapters, scenes, beats, lorebook entries, reveal-ledger entries, and saves
  And I see whether the story is play-ready

Scenario: Overview reflects what is missing
  Given a story with no characters yet
  When I view its overview
  Then it reports the story is not yet play-ready
  And it indicates what the story still needs to become playable
```

> **Technical Notes E1.2:**
> - **Business Logic:**
>   - Per-story config is held as the story's **settings**: default POV, `model_roles` overrides, and tunable rubric overrides.
>   - **Resolution order for every tunable and every model role is per-story override → global default.**
>   - Overview counts are **derived on read** (not stored), aggregating the story's authoring rows plus its count of saves.
>   - **Play-readiness is a derived gate, not stored state** — recomputed whenever the overview is read.
> - **Reference:** ADR 0012, 0017.

---

### E1.3 — Import / Export

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.3.1 | As an **author**, I want to export a story's authoring data so that I can back it up or move it between environments | 5 | Medium | 9 |
| S-1.3.2 | As an **author**, I want to import a previously exported story so that I can restore or transfer it | 5 | Low | 9 |

**Acceptance Criteria - S-1.3.1:**
```gherkin
Feature: Export a Story

Scenario: Export authoring data only
  Given I own a story with characters, cards, registers, sensitivities, lorebook entries, reveal-ledger entries, chapters/scenes/beats, and outlines
  When I export the story
  Then I receive a portable artifact containing the story's authoring rows
  And the artifact contains no save-realm playthrough data (sessions, edges, beat records, etc.)
  And source bibles referenced by bible_path are included by reference (path), not inlined

Scenario: Export is owner-scoped
  Given a story I do not own
  When I attempt to export it
  Then the action is denied and nothing about that story is leaked
```

**Acceptance Criteria - S-1.3.2:**
```gherkin
Feature: Import a Story

Scenario: Restore from an exported artifact
  Given a valid exported story artifact
  When I import it
  Then a new story is created under my ownership with its authoring rows restored
  And a slug collision is resolved without overwriting any existing story
  And no save-realm data is created by the import

Scenario: Invalid or incompatible artifact is rejected
  Given a malformed or version-incompatible artifact
  When I import it
  Then the import is rejected with a clear message
  And no partial story is created
```

> **Technical Notes E1.3:**
> - **Business Logic:**
>   - Export/import covers **authoring rows only** — never save-realm playthroughs.
>   - **Bibles referenced by path are included by reference** (the `bible_path`), never inlined into the artifact — consistent with the never-injected source rule.
>   - Import is **owner-scoped**: the resulting story is owned by the importer; a slug collision is resolved (e.g. suffixed) without overwriting an existing story.
>   - Import is **atomic and validated** — a malformed or version-incompatible artifact yields no partial story.
> - **Reference:** ADR 0012, 0017.

---

## EPIC E2: Authoring Workspace & Navigation

### E2.1 — Workspace Shell

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.1.1 | As an **author**, I want a per-story authoring workspace with navigation (characters, structure/outline, lorebook, settings, saves) so that I can move between authoring surfaces for one story | 3 | High | 7 |
| S-2.1.2 | As an **author**, I want a play-readiness checklist (needs ≥1 character, ≥1 chapter/scene/beat, valid model config) so that I know when a story is playable | 3 | Medium | 9 |

**Acceptance Criteria - S-2.1.1:**
```gherkin
Feature: Per-Story Authoring Workspace

Scenario: Enter a story's workspace
  Given I own a story
  When I open it
  Then I enter a per-story authoring workspace
  And I can navigate between its authoring surfaces: characters, structure/outline, lorebook, settings, and saves
  And every surface I reach is scoped to this story only

Scenario: Workspace scope is per-story
  Given I am in the workspace of story A
  Then I see only story A's content across every surface
  And switching to story B re-scopes every surface to story B's content
```

**Acceptance Criteria - S-2.1.2:**
```gherkin
Feature: Play-Readiness Checklist

Scenario: A complete story is reported playable
  Given a story with at least one character, at least one chapter containing a scene and a beat, and a valid model configuration for every required role
  When I view its readiness checklist
  Then every requirement is satisfied
  And the story is reported as playable

Scenario: An incomplete story lists what is missing
  Given a story missing a character, or missing a chapter/scene/beat, or with no resolvable model for a required role
  When I view its readiness checklist
  Then each unmet requirement is listed
  And the story is reported as not yet playable
```

> **Technical Notes E2.1:**
> - **Business Logic:**
>   - The authoring workspace is **story-scoped**: each surface (characters, structure/outline, lorebook, settings, saves) resolves to a single story.
>   - **Play-readiness is a derived gate, not stored state** — recomputed on read from the story's authoring content and resolved model config.
>   - Readiness requires: **≥1 character**, **≥1 chapter with ≥1 scene and ≥1 beat**, and a **valid/resolvable model configuration** for every required engine role.
> - **Reference:** ADR 0012.

---

## EPIC E3: Lorebook Management

### E3.1 — Lorebook CRUD

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.1.1 | As an **author**, I want to create/edit/delete lorebook entries (title, keywords, content, optional minimum-reveal-chapter) so that world facts can be injected on keyword match at runtime | 5 | High | 9 |
| S-3.1.2 | As an **author**, I want guidance/validation that lorebook entries are world facts only (never a character's interiority) so that lorebook injection never breaches character isolation | 2 | Medium | 10 |

**Acceptance Criteria - S-3.1.1:**
```gherkin
Feature: Lorebook Entry Management

Scenario: Create a lorebook entry
  Given I am in a story's lorebook
  When I create an entry with:
    | Field               | Value                                       |
    | title               | The Crystal Hollow                          |
    | keywords            | ["Crystal Hollow","gloves","Chrysalis"]     |
    | content             | The Hollow is a sealed Aether sink...       |
    | min_reveal_chapter  | (optional) Chapter 3                        |
  Then the entry is saved scoped to this story
  And on a keyword match at runtime its content can be injected into the narrator context and into knowledge-bounded NPC context

Scenario: Edit and delete an entry
  Given an existing lorebook entry
  When I edit its keywords or content, or delete it with confirmation
  Then the change is saved, or the entry is removed

Scenario: Minimum reveal chapter withholds early injection
  Given an entry with min_reveal_chapter set to Chapter 3
  When the active chapter is earlier than Chapter 3
  Then the entry is not injected

Scenario: An entry requires at least one keyword and content
  Given I create an entry with no keywords or empty content
  When I save it
  Then it is rejected with a clear message and not stored
```

**Acceptance Criteria - S-3.1.2:**
```gherkin
Feature: Lorebook World-Fact Discipline

Scenario: Author is guided to keep entries to world facts
  Given I am authoring a lorebook entry
  Then I am guided that content must be a world fact (places, objects, lore, mechanisms such as Link Resonance or the suppressor gloves)
  And that it must never contain a character's private interiority (feelings, secret intent, hidden knowledge)

Scenario: Character interiority is steered away from the lorebook
  Given content that reads as a character's private thoughts or secret motives
  When I attempt to save it as a lorebook entry
  Then I am warned that interiority does not belong in the lorebook
  And I am directed toward the character/card authoring surfaces instead
```

> **Technical Notes E3.1:**
> - **Business Logic:**
>   - A lorebook entry is **story-scoped** `{ title?, keywords, content, min_reveal_chapter? }`.
>   - **World facts only** — never a character's interiority; this discipline is what keeps lorebook injection from breaching character isolation.
>   - Injection is a **soft keyword mechanism**, not a new subsystem: on keyword match against the active scene/excerpt, the entry is injected into the **narrator** context and into an **NPC** world-knowledge block **clamped by `knowledge_boundary`** (an NPC only receives a world fact it would plausibly know at the current chapter).
>   - `keywords` required (≥1), `content` required; `min_reveal_chapter` optional — when set, the entry is not injected before that chapter.
> - **Reference:** ADR 0013 §5.

---

### E3.2 — Keyword Match Preview

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.2.1 | As an **author**, I want to test which lorebook entries a sample text triggers so that I can tune keywords before play | 3 | Medium | 10 |

**Acceptance Criteria - S-3.2.1:**
```gherkin
Feature: Lorebook Keyword Match Preview

Scenario: Preview the entries triggered by sample text
  Given a story with lorebook entries whose keywords include "gloves" and "Aether"
  When I submit sample text "she adjusted her suppressor gloves before touching the Aether"
  Then the entries whose keywords match the sample are listed as triggered
  And entries with no matching keyword are not listed

Scenario: Reveal-chapter clamp is reflected in the preview
  Given a triggered entry with a min_reveal_chapter later than the previewed chapter
  When I preview at the earlier chapter
  Then that entry is shown as withheld due to its minimum reveal chapter
```

> **Technical Notes E3.2:**
> - **Business Logic:**
>   - Keyword preview matches sample text against each entry's `keywords` and returns the set that **would** trigger — a tuning aid that uses the **same matching as runtime injection**.
>   - Preview honors the same clamps: an entry past its `min_reveal_chapter` (relative to the previewed chapter) is reported as **withheld**.
>   - World facts are not character-private, so the only gate keyword injection needs is the per-character `knowledge_boundary` clamp.
> - **Reference:** ADR 0013 §5.

---

## EPIC E4: Reveal Ledger Management

### E4.1 — Ledger CRUD & Clamp Preview

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.1.1 | As an **author**, I want to record load-bearing secrets (fact, reveal chapter, who-knows-before) so that spoiler-safety is explicit and never rests on inference | 5 | High | 10 |
| S-4.1.2 | As an **author**, I want to preview how a secret's reveal point will clamp a character's knowledge per chapter so that I can verify no early-chapter card leaks a future arc | 3 | Medium | 11 |

**Acceptance Criteria - S-4.1.1:**
```gherkin
Feature: Reveal Ledger Entry

Scenario: Record a load-bearing secret
  Given I am in a story's reveal ledger
  When I record a secret with:
    | Field           | Value             |
    | fact            | the_diagnosis     |
    | reveal_chapter  | Chapter 7         |
    | who_knows       | ["vixia-archi"]   |
  Then the secret is stored against its character (or as a world secret when no character is named)
  And spoiler-safety for that fact is explicit rather than inferred

Scenario: who_knows lists characters who know before the reveal
  Given a secret "the_diagnosis" with reveal_chapter Chapter 7
  When I add "vixia-archi" to who_knows
  Then vixia-archi is treated as knowing the fact before Chapter 7
  And other characters do not know it until Chapter 7

Scenario: Edit and delete a ledger entry
  Given an existing reveal-ledger entry
  When I change its reveal_chapter or who_knows, or delete it with confirmation
  Then the change is saved, or the entry is removed
```

**Acceptance Criteria - S-4.1.2:**
```gherkin
Feature: Reveal Clamp Preview

Scenario: A card before the reveal does not know the fact
  Given a secret "the_diagnosis" with reveal_chapter Chapter 7 for a character
  When I preview that character's knowledge at Chapter 3
  Then the fact is excluded from what the card knows
  And it appears as an explicit does_not_know entry on the chapter-3 knowledge_boundary

Scenario: A card at or after the reveal knows the fact
  Given the same secret revealed at Chapter 7
  When I preview the character's knowledge at Chapter 7
  Then the fact is included in what the card knows

Scenario: A pre-reveal knower is not clamped
  Given "vixia-archi" is listed in who_knows for "the_diagnosis"
  When I preview vixia-archi's knowledge before Chapter 7
  Then the fact is included for vixia-archi despite the reveal point
```

> **Technical Notes E4.1:**
> - **Business Logic:**
>   - The reveal ledger is a per-character (or world) list of load-bearing secrets `{ fact, reveal_chapter, who_knows }`.
>   - **Clamp rule** for a card at chapter **N**: include a fact iff `reveal_point ≤ N`; otherwise it becomes an explicit **`does_not_know`** entry on the card's `knowledge_boundary` (or is omitted).
>   - `who_knows` lists character slugs that know the fact **before** its `reveal_chapter` — those characters are **exempt** from the clamp for that fact.
>   - **Section tags cover the bulk; the ledger backstops the few critical facts** — spoiler-safety never rests on inference.
>   - Preview is **read-only** and computes the same clamp the compiler applies; it changes no committed card.
> - **Reference:** ADR 0013 §3.

---

## EPIC E5: Tunable Engine Config

### E5.1 — Shared Config Management

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.1.1 | As an **author**, I want to edit the shared severity rubric (severity tier → magnitude range + channel) so that appraisal magnitudes are tunable | 3 | Medium | 10 |
| S-5.1.2 | As an **author**, I want to edit the elapsed-time bucket → decay/gap-drift mapping so that narrative-time decay is tunable | 3 | Medium | 11 |
| S-5.1.3 | As an **author**, I want to edit emotion drift caps (the ±N off-screen bound) and the model-role tier→slug defaults so that emotion wobble and model routing are tunable | 3 | Medium | 11 |
| S-5.1.4 | As an **author**, I want per-story overrides for any tunable so that one story can deviate from global defaults | 3 | Medium | 11 |

**Acceptance Criteria - S-5.1.1:**
```gherkin
Feature: Severity Rubric Configuration

Scenario: Edit a severity tier's mapping
  Given I am editing the shared severity rubric
  When I set each tier's magnitude range and channel:
    | Tier        | Channel               |
    | negligible  | drift                 |
    | minor       | drift                 |
    | notable     | scales_with_severity  |
    | major       | rupture               |
    | defining    | rupture               |
  Then appraisal magnitudes for each tier resolve from the new mapping

Scenario: Invalid magnitude range is rejected
  Given a tier whose lower magnitude bound exceeds its upper bound
  When I save the rubric
  Then the change is rejected with a clear message
  And the prior rubric mapping stands
```

**Acceptance Criteria - S-5.1.2:**
```gherkin
Feature: Elapsed-Time Bucket Mapping

Scenario: Edit decay and gap-drift per elapsed bucket
  Given I am editing the elapsed-time mapping
  When I configure decay and emotion gap-drift for the buckets:
    | Bucket      | Real gap | Applies decay/gap-drift |
    | continuous  | no       | no                      |
    | hours       | no       | no                      |
    | days        | yes      | yes                     |
    | weeks       | yes      | yes                     |
    | months      | yes      | yes                     |
    | longer      | yes      | yes                     |
  Then narrative-time decay and emotion gap-drift resolve from the new mapping

Scenario: No real gap fires neither decay nor gap-drift
  Given a scene boundary whose elapsed bucket is "continuous" or "hours"
  Then neither decay nor emotion gap-drift fires for that boundary
```

**Acceptance Criteria - S-5.1.3:**
```gherkin
Feature: Drift Caps & Model-Role Defaults

Scenario: Edit the off-screen emotion drift cap
  Given the default emotion drift cap is ±3
  When I change the cap to a new ±N
  Then off-screen emotion wobble is bounded by the new ±N

Scenario: Edit the model-role tier → slug defaults
  Given the engine roles and their default model slugs
  When I change the default slug for a role (e.g. narrator_prose, npc_major, npc_minor)
  Then subsequent calls for that role resolve to the new slug by default
```

**Acceptance Criteria - S-5.1.4:**
```gherkin
Feature: Per-Story Tunable Overrides

Scenario: One story deviates from a global default
  Given a global default for a tunable (severity rubric, elapsed mapping, drift cap, or model role)
  When I set a per-story override for that tunable
  Then this story uses the override
  And other stories continue to use the global default

Scenario: Override resolution order
  Given a per-story override and a global default for the same tunable
  When the engine resolves the value within this story
  Then the per-story override takes precedence over the global default
```

> **Technical Notes E5.1:**
> - **Business Logic:**
>   - **Severity rubric:** tiers `negligible / minor / notable / major / defining` map to **magnitude ranges + a drift/rupture channel** — appraisal magnitudes are tunable from this rubric.
>   - **Elapsed buckets:** `continuous / hours / days / weeks / months / longer`; **decay and emotion gap-drift fire only on a real gap (days+)** — `continuous` and `hours` apply neither.
>   - **Emotion drift cap:** the **±N off-screen wobble bound**; default ≈ **±3**.
>   - **Model-role tier → slug defaults:** each engine role resolves to a default model slug.
>   - **Resolution for every tunable and every role is per-story override → global default.**
>   - Validation: magnitude ranges must be well-formed (lower ≤ upper); edits are non-destructive to prior committed history.
> - **Reference:** ADR 0005, 0015, 0014, 0017.

---

## Sprint Roadmap

### Sprint 7: Stories & Workspace Shell (E1.1 + E2.1)
```
Sprint 7 (Week 7):
├── S-1.1.1: Create a story (title, slug, description)
├── S-1.1.2: Story dashboard (list / open / edit / delete)
├── S-2.1.1: Per-story authoring workspace + navigation
└── Integration testing: owner-scoped story CRUD & workspace scope
```

### Sprint 8: Duplicate, Settings & Overview (E1.1 + E1.2)
```
Sprint 8 (Week 8):
├── S-1.1.3: Duplicate a story (authoring-only deep copy)
├── S-1.2.1: Per-story settings (POV / model-role / rubric overrides)
├── S-1.2.2: Story overview (counts + play-readiness)
└── Integration testing: override resolution (per-story → global)
```

### Sprint 9: Import/Export, Readiness & Lorebook CRUD (E1.3 + E2.1 + E3.1)
```
Sprint 9 (Week 9):
├── S-1.3.1: Export a story (authoring rows only)
├── S-1.3.2: Import a story (owner-scoped, atomic)
├── S-2.1.2: Play-readiness checklist
├── S-3.1.1: Lorebook entry CRUD (keywords / content / min_reveal_chapter)
└── Integration testing: export/import round-trip (no save-realm data)
```

### Sprint 10: World Facts, Reveal Ledger & Rubric (E3 + E4.1 + E5.1)
```
Sprint 10 (Week 10):
├── S-3.1.2: World-fact discipline (interiority rejected)
├── S-3.2.1: Keyword match preview
├── S-4.1.1: Reveal-ledger CRUD (fact / reveal_chapter / who_knows)
├── S-5.1.1: Severity rubric configuration
└── Integration testing: keyword preview matches runtime injection
```

### Sprint 11: Clamp Preview & Tunables (E4.1 + E5.1)
```
Sprint 11 (Week 11):
├── S-4.1.2: Reveal clamp preview (per-chapter knowledge_boundary)
├── S-5.1.2: Elapsed-bucket → decay/gap-drift mapping
├── S-5.1.3: Emotion drift caps + model-role tier→slug defaults
├── S-5.1.4: Per-story overrides for any tunable
└── Phase 2 regression + hardening
```

---

## Definition of Done (DoD)

See the [program DoD](./README.md#7-global-definition-of-done-dod). Phase-2 emphasis:

- [ ] Story create / edit / delete / duplicate are **owner-scoped and atomic**; cross-owner access has explicit negative tests.
- [ ] Per-story settings resolve **per-story override → global default** for every tunable and every model role; verified.
- [ ] Export/import round-trips **authoring rows only**; no save-realm data crosses; bibles included **by reference (path)**; import is atomic and slug-collision safe.
- [ ] Lorebook entries are **world-facts-only** (interiority rejected/steered away); keyword preview matches runtime injection; NPC injection is **`knowledge_boundary`-clamped**.
- [ ] Reveal-ledger clamp preview **matches the compile clamp**: pre-reveal facts become explicit `does_not_know`; `who_knows` knowers are exempt; no early-chapter leak.
- [ ] Play-readiness is a **derived gate** (not stored) and enumerates unmet requirements.
- [ ] `pnpm lint` clean; type-check passes; Wayfinder types regenerate without errors.
- [ ] UX states covered (loading, empty, error, success, unauthorized); responsive and keyboard-accessible.

---

## Success Metrics — Phase 2

| Metric | Target | Measurement |
|--------|--------|-------------|
| Story CRUD correctness | 100% | Create/edit/delete/duplicate pass; owner-scope negative tests pass |
| Duplicate fidelity | 100% authoring · 0 save data | Copy equals original authoring rows; no session/edge/record rows copied; editing copy ≠ original |
| Export/import round-trip | 100% authoring rows restored · 0 save-realm rows | Re-import yields an equivalent authoring set; bibles referenced by path |
| Lorebook isolation | 0 interiority entries accepted | World-fact validation passes; NPC injection `knowledge_boundary`-clamped |
| Reveal clamp accuracy | 0 early-chapter leaks | Pre-reveal facts excluded; clamp preview == compile clamp; `who_knows` exemptions correct |
| Tunable resolution | 100% correct precedence | Per-story override beats global default across rubric/elapsed/drift/model-role |

---

## Risk Register — Phase 2

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Spoiler leak from an early-chapter card via a missing/incorrect reveal-ledger entry | Critical | Medium | Reveal ledger + section tags → `knowledge_boundary` clamp; clamp preview; review gate as the floor |
| Lorebook entry carries character interiority and breaches isolation | High | Medium | World-fact-only guidance/validation; NPC injection clamped by `knowledge_boundary` |
| Export/import drags save-realm data or overwrites an existing story | High | Low | Authoring-only scope; owner-scoped, atomic, validated import; slug-collision resolution |
| Per-story override resolution diverges from the global-default order | Medium | Medium | Single resolution rule (per-story → global) tested across all tunables and roles |
| Story delete cascade removes more or less than intended | High | Low | Owner-scoped, confirmed, atomic delete; cascade tests on authoring rows only |
| Duplicate creates a slug collision or shares rows with the original | Medium | Low | Fresh unique slug; deep copy; independence test (edit copy ≠ original) |

---

*Document Version: 1.0 · Author: Zulfikar Hidayatullah · Created: June 2026*
