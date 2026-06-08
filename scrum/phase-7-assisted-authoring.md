# Phase 7: Assisted Authoring — three ways to make every entry
## Directed Interactive Novel Engine (DINE)

**Timeline:** ~1.5 Months (4–5 Sprints)
**Depends on:** Phase 0 (the `LlmClient` + OpenRouter gateway, `ModelRoleResolver`, structured-output calls, `prompt_blocks` registry, and the **manual creation surfaces** for story / character / lorebook / reveal-ledger). Phase 1 (the structure surfaces for chapter / scene / beat). **No play-loop precondition** — this phase only touches authoring, so it is independently schedulable once the entry hosts and the LLM client exist.
**Governing ADRs:** 0018 (character creation — AI / manual / hybrid + archetypes; **generalized here to every entry type**), 0013 (authoring/compile pipeline), 0020 (prompt-block registry — drives the generation prompts), 0017 (LLM client routed by role, call log), 0019 (outline compilation — the chapter/scene/beat generator reuses this contract).

> **Goal — every entry has three doors.** Today an author *types* every field of every entry by hand. This phase gives **every** authoring entry (character, lorebook fact, scene, beat, chapter, reveal-ledger secret) the **same three creation modes**:
>
> 1. **Manual** — the existing hand-authored form (unchanged; still the default).
> 2. **Brief** — the author writes a short, free-text brief ("a nervous archivist who hides a royal birthmark") and a model **drafts the full structured entry**; the author then **saves it, spins for a different draft, edits it, or discards it** — nothing is saved until the author commits.
> 3. **Full** — the author asks for a complete draft from almost nothing (story context only, "surprise me"); same **save / spin / edit / discard** review.
>
> **Whatever a model returns lands in the entry's canonical _context format_ — not just the visual form.** "Format" here means the **structured data shape the engine consumes**, as defined by the ADRs and the architecture brief: the shape the **assembler (ADR 0007) folds into each agent's prompt blocks (ADR 0020)** under the leak guards — a character card carrying `knowledge_boundary` (ADR 0001/0013), a lorebook entry that is **world-facts-only, never interiority** (ADR 0013 §5), a scene's POV contract (ADR 0009), a beat's goal/intent (ADR 0015), the reveal-ledger record (ADR 0013). Generation is **structured output validated against that canonical shape** — the same mechanism the app already uses for the narrator prose call (ADR 0016 §4 / ADR 0020) — so a saved draft is directly consumable by context assembly without reshaping. The author **picks which model** drafts each one, and every draft is a logged, costed call. The author is always the commit gate — the engine never auto-saves a generated entry.

> **Built once, reused everywhere.** Epic E1 is a **single shared assisted-creation contract** (mode selector + draft generation + schema-conformance + the save/spin/edit/discard review + per-generation model selector + safe-fail). Epic E2 **lights up each entry type** on that one contract by mapping the entry's real fields — it never re-implements generation per entry. This generalizes ADR 0018's character creation pattern to the whole authoring surface; the character-specific depth (archetypes, psychology fields) and the outline→beats compile (ADR 0019) **plug into this same contract** as they land in Phases 4–5.

> **Deferred / out of scope:** auto-committing generated content (the author always reviews — consistent with the propose→review→commit ethos of ADR 0003); bulk "generate a whole cast/lorebook in one shot"; cross-chapter spoiler clamping of generated character/reveal content (that is the Phase 5 compile clamp — see Leak-guards on E2.2/E2.5); fine-tuning / embeddings.

---

## Product Backlog Overview

### Epic Summary

| Epic ID | Epic Name | Priority | Story Points | Sprints |
|---------|-----------|----------|--------------|---------|
| E1 | The Assisted-Creation Contract (shared host) | Critical | 29 | 1–3 |
| E2 | Per-Entry Generators (light up each entry type) | High | 21 | 3–5 |

**Total Estimated:** ~50 Story Points

---

## EPIC E1: The Assisted-Creation Contract (shared host)

> One reusable mechanism every entry type plugs into. It owns: the **mode selector** (Manual / Brief / Full), the **draft generation** (brief and full), the **schema-conformance** that forces output into the entry's canonical fields, the **draft-review affordance** (save / spin / edit / discard), the **per-generation model selector**, and **safe-fail** (malformed output is retried then surfaced, never saved). Built once; Epic E2 and the Phase 4–5 generators reuse it.

### E1.1 — Creation mode selector

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.1.1 | As an **author**, I want every entry's creation surface to offer three modes — Manual, Brief, and Full — with Manual as the default so that assisted creation is always available but never forced on me | 3 | Critical | 1 |

**Acceptance Criteria - S-1.1.1:**
```gherkin
Feature: Three creation modes on every entry

Scenario: Manual is the default and unchanged
  Given I begin creating any entry (character, lorebook, scene, beat, chapter, reveal)
  Then Manual mode is selected by default
  And the manual form behaves exactly as it did before this phase

Scenario: Assisted modes are offered consistently
  Given I begin creating any entry that supports assisted creation
  Then I can choose Manual, Brief, or Full
  And the same three modes appear on every supported entry type, in the same way

Scenario: Switching modes never silently loses my work
  Given I have typed into the manual form or written a brief
  When I switch creation mode
  Then I am warned before any field I entered would be discarded
```

> **Technical Notes E1.1:**
> - **Preconditions:** the existing manual creation surface for each entry (story/character/lorebook/reveal from Phase 0; chapter/scene/beat from Phase 1).
> - **Integrates-into:** the **existing creation form/dialog of each entry** — add the mode selector *into that same surface*. **Do not** build a separate "AI generation" page; assisted creation is a mode of the existing host, not a detached artifact (the §3 orphan rule).
> - **Leak-guards:** `none` (authoring realm; the author is omniscient over their own story).
> - **Business Logic:** Manual is always the default. The set of supported entry types is the union covered by Epic E2. Mode is a per-creation choice, not a stored preference this phase.

---

### E1.2 — Draft from a brief + save / spin / edit / discard

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.2.1 | As an **author**, I want to write a short brief and have a model draft the full entry so that I can start from a complete, on-theme draft instead of an empty form | 5 | Critical | 1 |
| S-1.2.2 | As an **author**, I want to **spin** for a different draft, **edit** a draft, or **discard** it — and only **save** commits — so that I stay in control and nothing is persisted until I accept it | 3 | Critical | 2 |

**Acceptance Criteria - S-1.2.1:**
```gherkin
Feature: Draft an entry from a brief

Scenario: A brief produces a complete structured draft
  Given I choose Brief mode for an entry
  And I write a brief describing what I want
  When I trigger generation
  Then a model drafts the entry with every canonical field of that entry type populated
  And the draft is presented to me for review, not saved

Scenario: The draft is grounded in the story it belongs to
  Given my story already has a title, premise, existing cast, and lorebook
  When I generate an entry from a brief
  Then the draft is consistent with that existing story context
  And it does not contradict entries that already exist

Scenario: An empty brief is rejected
  Given I choose Brief mode
  And I leave the brief blank
  When I trigger generation
  Then I am told the brief is required before a draft can be generated
  And no model call is made
```

**Acceptance Criteria - S-1.2.2:**
```gherkin
Feature: Review a generated draft (save / spin / edit / discard)

Scenario: Spin produces an alternative draft
  Given a draft is presented for review
  When I spin
  Then a fresh draft is generated for the same brief
  And I can keep spinning; each draft replaces the one shown, and none is saved

Scenario: Edit before saving
  Given a draft is presented for review
  When I edit any field
  Then my edits are reflected in the draft
  And saving persists exactly the reviewed (edited) values

Scenario: Discard leaves nothing behind
  Given a draft is presented for review
  When I discard it
  Then no entry is created and no draft is retained

Scenario: Save is the only commit
  Given a draft I am satisfied with
  When I save
  Then the entry is created with the reviewed values
  And it is indistinguishable from a manually-created entry afterward
```

> **Technical Notes E1.2:**
> - **Preconditions:** Phase 0 `LlmClient` + structured-output call (the same `completeStructured` path the narrator prose call uses); `ModelRoleResolver`.
> - **Integrates-into:** the entry's existing creation surface (E1.1); the draft-review affordance is part of that same surface.
> - **Leak-guards:** `none` (authoring generation is omniscient-author-side). Grounding context passed to the generator is the author's own story data only.
> - **Business Logic:**
>   - The brief is **required** in Brief mode; an empty brief makes **no** model call.
>   - Generation **never persists** — it returns a draft for review. Only an explicit **Save** commits; **Spin** = a new generation for the same brief; **Edit** mutates the in-review draft; **Discard** retains nothing.
>   - A saved generated entry is **identical in shape** to a manually-created one (same fields, same validation) — downstream consumers cannot tell how it was made.
>   - The generator is grounded with the **story's own context** (title/premise + existing cast/lore as relevant) so drafts fit the world and avoid contradicting existing entries.

---

### E1.3 — Full draft from minimal seed

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.3.1 | As an **author**, I want a full draft from almost nothing (story context only) so that I can discover an entry I had not thought of and refine it from there | 5 | High | 2 |

**Acceptance Criteria - S-1.3.1:**
```gherkin
Feature: Full generation from minimal seed

Scenario: Full mode drafts with no brief
  Given I choose Full mode for an entry
  And I provide no brief (or only the story context)
  When I trigger generation
  Then a model drafts a complete entry that fits the story
  And the draft enters the same save / spin / edit / discard review as Brief mode

Scenario: Full and Brief share the same review and commit path
  Given a draft produced by Full mode
  Then I can save, spin, edit, or discard it exactly as in Brief mode
  And nothing is saved until I commit
```

> **Technical Notes E1.3:**
> - **Preconditions:** E1.2 (the draft/review path it reuses).
> - **Integrates-into:** the same creation surface + draft-review affordance as E1.2.
> - **Leak-guards:** `none`.
> - **Business Logic:** Full mode is Brief mode with an **optional/empty** brief — it reuses the exact same generation, schema-conformance, review, spin, and commit path. Only the seed differs.

---

### E1.4 — Per-generation model selector + spend visibility

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.4.1 | As an **author**, I want to pick which model drafts each entry — defaulting to the configured authoring-generation role, overridable per generation — so that I can trade speed for quality per draft | 3 | High | 2 |
| S-1.4.2 | As an **author**, I want each generation (and each spin) logged with its model, tokens, and cost so that assisted authoring spend is visible | 2 | Medium | 3 |

**Acceptance Criteria - S-1.4.1:**
```gherkin
Feature: Choose the drafting model

Scenario: Generation defaults to the configured authoring-generation role
  Given I have not chosen a model for this generation
  When I generate a draft
  Then the model resolves from the authoring-generation role (per-story override, else global default)

Scenario: Override the model for a single generation
  Given the available models for my account
  When I select a different model before generating
  Then that generation (and any spin after it) uses the selected model
  And the choice applies to this creation only, not as a permanent setting

Scenario: No resolvable model blocks generation gracefully
  Given no model resolves for the authoring-generation role and I select none
  When I attempt to generate
  Then I am told to configure a model first
  And I can still create the entry manually
```

**Acceptance Criteria - S-1.4.2:**
```gherkin
Feature: Log assisted-authoring spend

Scenario: Every draft and spin is a logged call
  Given I generate a draft and then spin twice
  Then three calls are logged, each with its model, token usage, cost, latency, and status

Scenario: Discarded drafts are still logged
  Given I generate a draft and discard it
  Then the call is still logged (the model work happened) even though no entry was saved
```

> **Technical Notes E1.4:**
> - **Preconditions:** Phase 0 model catalog + `ModelRoleResolver` + call log; the model-role configuration surface (Settings → model roles).
> - **Integrates-into:** the generation action of the shared contract; spend is logged to the existing call log and surfaced where other spend is (the Phase 6 cost view consumes it — no new dashboard here).
> - **Leak-guards:** `none`.
> - **Business Logic:**
>   - Default model = resolved **authoring-generation role** (per-story override → global default). The per-generation override applies to **that creation only** and is **not** persisted as a setting.
>   - Cost rendered in **Rupiah (Rp)**; times in **Asia/Jakarta (WIB)**.
>   - Every generation — including spins and discarded drafts — is logged (role, model, tokens, cost, latency, status).
>   - With no resolvable model and no override, generation is blocked with a clear message; **Manual remains available** as the fallback.

---

### E1.5 — Schema-conformant output + safe-fail

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.5.1 | As a **system**, I want generated output validated against the entry's canonical field schema before it is shown so that a draft is always in the same shape a manual entry would be | 5 | Critical | 1 |
| S-1.5.2 | As an **author**, I want malformed or failed generation handled safely — retried then surfaced with nothing saved — so that a bad model never corrupts my entries or blocks me | 3 | Critical | 3 |

**Acceptance Criteria - S-1.5.1:**
```gherkin
Feature: Output conforms to the entry's canonical schema

Scenario: A draft is always in the manual form's shape
  Given any entry type's generation
  When a draft is produced
  Then it contains exactly the entry's canonical fields, correctly typed
  And it passes the same field validation a manual save would apply

Scenario: Required fields are never empty in a presented draft
  Given an entry type with required fields
  When a draft is presented for review
  Then every required field is populated (or the draft is treated as malformed — see S-1.5.2)
```

**Acceptance Criteria - S-1.5.2:**
```gherkin
Feature: Safe-fail on malformed or failed generation

Scenario: Malformed output is retried then surfaced
  Given a model returns output that does not conform to the entry schema
  When generation runs
  Then it is retried up to the configured bound
  And if it still does not conform, I am shown a clear error and nothing is saved
  And I can spin, adjust the brief, choose another model, or fall back to Manual

Scenario: A failed model call never partially creates an entry
  Given a generation call fails or times out
  Then no entry and no partial draft is persisted
  And the failure is logged with its status

Scenario: Generation never auto-saves
  Given any successful generation
  Then the result is only ever presented for review
  And it is saved only by my explicit commit
```

> **Technical Notes E1.5:**
> - **Preconditions:** the per-entry canonical field definitions (the manual forms' validation rules); the structured-output call path (mirrors the narrator prose schema approach, ADR 0016 §4 / ADR 0020).
> - **Integrates-into:** the shared generation action; reuses the project's structured-output + retry pattern already used by the narrator prose call (S-4.2.2 safe-fail).
> - **Leak-guards:** `none` (authoring), but output is **structurally validated** — the engine never trusts raw model text.
> - **Business Logic:**
>   - Each entry type declares a **generation schema that is its canonical _context_ shape** — the structured data the assembler (ADR 0007) folds into the agent prompt blocks (ADR 0020), which is the same set of fields the manual form already persists. Output is validated against that schema **and** against the same business validation a manual save uses, so a saved draft is directly consumable by context assembly without reshaping.
>   - Generation honors each entry type's **downstream format conventions**, not just its field list: a lorebook entry stays **world-facts-only / never interiority** (ADR 0013 §5), a character draft always carries a populated `knowledge_boundary` (ADR 0001/0013), a scene draft fills a valid POV contract (ADR 0009), a beat draft carries a goal/intent (ADR 0015). A draft that violates a convention is treated as malformed (S-1.5.2).
>   - **Retry bound** matches the engine's existing malformed-output policy (retry to the configured bound, then fail closed) — see Phase 1 S-4.2.2.
>   - A non-conforming result after retries is a **handled error**, surfaced to the author with nothing saved; a failed/timed-out call persists **no** entry and **no** partial draft and is logged.
>   - Generation is **never** auto-committed.

---

## EPIC E2: Per-Entry Generators (light up each entry type)

> Each sub-epic maps one entry type's **real fields** onto the E1 contract. These stories are thin — they declare the entry's generation schema + grounding context and wire the existing manual form into the shared mode selector / draft review. They add **no** new generation mechanism.

### E2.1 — Lorebook entry

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.1.1 | As an **author**, I want to generate a lorebook entry (`title`, `keywords`, `content`, optional `min_reveal_chapter`) from a brief or in full so that world facts are faster to author and consistently keyworded | 3 | High | 3 |

**Acceptance Criteria - S-2.1.1:**
```gherkin
Feature: Assisted lorebook entry

Scenario: Generate a lorebook fact from a brief
  Given I choose Brief mode for a lorebook entry
  And I write "the ban on open flame inside the archive"
  When I generate
  Then the draft populates title, a sensible set of keywords, and content
  And I can set or clear min_reveal_chapter before saving

Scenario: Generated lore is a world fact, never a character's interiority
  Given any generated lorebook draft
  Then it describes a world fact only
  And it does not assert a character's private feelings or hidden intent
```

> **Technical Notes E2.1:**
> - **Preconditions:** E1 contract; the existing lorebook creation surface (Phase 0).
> - **Integrates-into:** the existing lorebook create form — add the three modes + draft review there.
> - **Leak-guards:** `none` (world facts; authoring). Generated content stays within the lorebook's "world facts only, never interiority" rule (ADR 0013 §5).
> - **Business Logic:** schema = lorebook canonical fields (`title` nullable, `keywords` list, `content` required, `min_reveal_chapter` optional). Keywords should be derivable from the content for keyword-match injection.

---

### E2.2 — Character

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.2.1 | As an **author**, I want to generate a character's minimal card (`name`, `appearance`, `folded_identity`, mandatory `knowledge_boundary`, `is_player`) from a brief or in full so that a playable character exists in seconds — at the depth the engine currently supports | 5 | High | 3 |

**Acceptance Criteria - S-2.2.1:**
```gherkin
Feature: Assisted character creation

Scenario: Generate a minimal character from a brief
  Given I choose Brief mode for a character
  And I write "a guarded archivist who hides that she is the lost heir"
  When I generate
  Then the draft populates name, appearance, folded_identity, and a knowledge_boundary
  And is_player defaults to not-the-player unless I indicate otherwise

Scenario: knowledge_boundary is always present in a character draft
  Given any generated character draft
  Then knowledge_boundary is populated (it is mandatory for the engine)
  And I can edit it before saving

Scenario: Depth matches what the engine supports at the time
  Given the engine's character model at this point in the program
  Then generation populates exactly the fields that exist then
  And richer psychology fields are generated only once they exist (Phase 5)
```

> **Technical Notes E2.2:**
> - **Preconditions:** E1 contract; the existing character creation surface (Phase 0/1, minimal card).
> - **Integrates-into:** the existing character create form. This is **ADR 0018 generalized onto the shared contract**; the full AI/hybrid pipeline + archetypes (ADR 0018) and psychology fields deepen this **same** generator in Phase 5 — not a separate one.
> - **Leak-guards:** `none` at authoring time. **Note:** generated character content must not fabricate cross-chapter spoilers into early-chapter cards — that clamp is the Phase 5 compile (`knowledge_boundary` at compile, ADR 0013 §4); this phase generates the authoring card the author reviews.
> - **Business Logic:** schema = the character's canonical fields **as they exist when this story is built** (minimal card now: `name`, `appearance`, `folded_identity`, required `knowledge_boundary`, `is_player` default false). Exactly one `is_player` per story still holds — generation defaults new characters to non-player.

---

### E2.3 — Scene (POV contract + present cast)

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.3.1 | As an **author**, I want to generate a scene's POV contract (`pov_mode`, `pov_anchor`, `tone`) and `present_characters` from a brief or in full so that a scene is set up consistently with the chapter and cast | 5 | High | 4 |

**Acceptance Criteria - S-2.3.1:**
```gherkin
Feature: Assisted scene setup

Scenario: Generate a scene's POV contract within its chapter
  Given I create a scene inside a chapter
  And I choose Brief mode and write "a tense confrontation in the archive at night"
  When I generate
  Then the draft populates pov_mode, pov_anchor, tone, and present_characters
  And pov_anchor and present_characters reference only characters that exist in this story

Scenario: The scene fits its parent chapter
  Given the chapter the scene belongs to has a default POV
  When a scene is generated
  Then its POV contract is consistent with the chapter's default unless I change it
```

> **Technical Notes E2.3:**
> - **Preconditions:** E1 contract; the existing scene creation surface (Phase 1 structure); the story's cast (to anchor POV / present cast).
> - **Integrates-into:** the existing scene create form within the structure surface.
> - **Leak-guards:** `none`.
> - **Business Logic:** schema = scene canonical fields (`pov_mode`, `pov_anchor`, `tone`, `present_characters`). `pov_anchor` and `present_characters` must reference **existing** characters of the story (no invented cast); the draft defaults consistent with the parent chapter's `pov_default`.

---

### E2.4 — Beat

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.4.1 | As an **author**, I want to generate a beat's `goal` from a brief or in full so that a scene's beats are quick to lay down and directable | 3 | Medium | 4 |

**Acceptance Criteria - S-2.4.1:**
```gherkin
Feature: Assisted beat

Scenario: Generate a beat goal from a brief
  Given I create a beat inside a scene
  And I choose Brief mode and write "she almost reveals the birthmark, then pulls back"
  When I generate
  Then the draft populates the beat goal in the engine's beat shape
  And I can save, spin, edit, or discard it

Scenario: The beat fits its scene
  Given the scene the beat belongs to
  When a beat is generated
  Then its goal is consistent with the scene's POV contract and present cast
```

> **Technical Notes E2.4:**
> - **Preconditions:** E1 contract; the existing beat creation surface (Phase 1 structure).
> - **Integrates-into:** the existing beat create form within the structure surface.
> - **Leak-guards:** `none`.
> - **Business Logic:** schema = beat canonical fields (`goal` required this phase; richer beat-document fields — ADR 0015 — are generated once they exist, Phase 4). The **outline→chapters/scenes/beats compile (ADR 0019)** is a *bulk* generator that reuses this same contract and lands with Phase 4.

---

### E2.5 — Chapter & reveal-ledger entry

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.5.1 | As an **author**, I want to generate a chapter (`title`, `pov_default`, optional `outline`) from a brief or in full so that a chapter scaffolds with a coherent premise | 3 | Medium | 5 |
| S-2.5.2 | As an **author**, I want to generate a reveal-ledger entry (the load-bearing secret) from a brief or in full so that spoiler-safety is explicit early — drafted, then reviewed by me | 2 | Low | 5 |

**Acceptance Criteria - S-2.5.1:**
```gherkin
Feature: Assisted chapter

Scenario: Generate a chapter scaffold
  Given I create a chapter
  And I choose Brief mode and write "the heir's secret begins to surface"
  When I generate
  Then the draft populates title, pov_default, and an optional outline
  And the chapter number is assigned by the system, not the model
```

**Acceptance Criteria - S-2.5.2:**
```gherkin
Feature: Assisted reveal-ledger entry

Scenario: Generate a load-bearing secret
  Given I create a reveal-ledger entry
  And I choose Brief mode and write "the archivist is the lost heir; revealed in chapter 3"
  When I generate
  Then the draft populates the reveal's canonical fields
  And it is presented for my review before it becomes part of the spoiler map

Scenario: A generated reveal is never auto-applied to play
  Given a generated reveal-ledger draft
  Then saving only records the reveal entry
  And it influences play only through the existing compile/clamp pipeline (Phase 5), never directly from generation
```

> **Technical Notes E2.5:**
> - **Preconditions:** E1 contract; the existing chapter creation surface (Phase 1) and reveal-ledger surface (Phase 0).
> - **Integrates-into:** those existing create forms.
> - **Leak-guards:** `none` at authoring. **Note (reveal-ledger):** the reveal map gates spoilers at **compile** (Phase 5, `knowledge_boundary`); generation only drafts the authoring record the author reviews — it never directly affects what a player or NPC sees.
> - **Business Logic:** chapter `number` is **system-assigned** (next ordinal), never taken from the model; chapter schema = `title`, `pov_default`, optional `outline`. Reveal schema = the reveal-ledger's canonical fields. Both follow the universal rule: draft → review → explicit save.

---

## Sprint Roadmap

### Sprint 1: The contract — modes, brief draft, schema conformance (E1.1 + E1.2 + E1.5 start)
```
├── S-1.1.1: Three-mode selector on every entry (Manual default)
├── S-1.2.1: Draft an entry from a brief (grounded, not saved)
├── S-1.5.1: Output conforms to the entry's canonical schema
└── Test: a draft always matches the manual form's shape + validation
```

### Sprint 2: Review, full mode, model selector (E1.2 + E1.3 + E1.4 start)
```
├── S-1.2.2: Save / spin / edit / discard (save is the only commit)
├── S-1.3.1: Full generation from minimal seed (shares the review path)
├── S-1.4.1: Per-generation model selector (role default + override)
└── Test: spin/discard never persist; save commits exactly the reviewed values
```

### Sprint 3: Safe-fail, spend logging, first entries (E1.4 + E1.5 + E2.1 + E2.2)
```
├── S-1.5.2: Safe-fail (retry → surface → nothing saved; never auto-save)
├── S-1.4.2: Log every generation/spin/discard (model, tokens, cost)
├── S-2.1.1: Lorebook generator
├── S-2.2.1: Character generator (minimal card; ADR 0018 generalized)
└── Test (safe-fail): malformed output retried then surfaced, nothing saved
```

### Sprint 4: Structure entries (E2.3 + E2.4)
```
├── S-2.3.1: Scene generator (POV contract + present cast, existing cast only)
├── S-2.4.1: Beat generator (goal)
└── Test: generated scene/beat reference only existing cast + fit their parent
```

### Sprint 5: Chapter & reveal (E2.5)
```
├── S-2.5.1: Chapter generator (system-assigned number)
├── S-2.5.2: Reveal-ledger generator (drafted, reviewed, compile-gated for play)
└── Phase 7 end-to-end: every entry creatable via Manual / Brief / Full, author-committed
```

---

## Definition of Done (DoD)

See the [program DoD](./README.md#9-global-definition-of-done-dod). Phase-7 emphasis:

- [ ] Every supported entry type offers **Manual / Brief / Full**, with **Manual the default** and unchanged.
- [ ] Generated output is **validated against the entry's canonical schema** (same fields + same business validation as the manual save); a saved generated entry is **indistinguishable** from a manual one.
- [ ] Generation **never auto-saves**: every draft goes through **save / spin / edit / discard**, and only an explicit save commits.
- [ ] **Safe-fail** verified: malformed output is retried to the bound then surfaced with **nothing saved**; a failed/timed-out call leaves **no** entry and **no** partial draft and is logged.
- [ ] The author can **pick the model** per generation (role default + override); every generation/spin/discard is **logged** with model, tokens, and cost (Rp / WIB).
- [ ] Assisted creation is wired **into the existing creation surfaces** — no new detached generation page.
- [ ] `pnpm lint` clean; type-check passes; Wayfinder types regenerate; LLM failure/timeout/malformed paths handled and logged.

---

## Success Metrics — Phase 7

| Metric | Target | Measurement |
|--------|--------|-------------|
| Mode coverage | 100% of supported entries | Character, lorebook, scene, beat, chapter, reveal each offer Manual/Brief/Full |
| Schema conformance | 100% | Every presented draft matches the entry's canonical fields + passes manual validation |
| No auto-save | 0 auto-commits | Nothing is persisted without an explicit author save (spin/discard persist nothing) |
| Safe-fail | 0 corrupt/partial entries | Malformed/failed generation never creates an entry or partial draft |
| Spend visibility | All calls logged | Every generation/spin/discard logged with model, tokens, cost |

---

## Risk Register — Phase 7

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Generated output off-schema corrupts an entry | High | Medium | Structured output validated against the entry's canonical schema + the same business validation as manual save; retry-then-fail; never trust raw model text |
| Generation auto-saves / bypasses author control | High | Low | Universal draft→review→commit: only explicit save persists; spin/edit/discard persist nothing; mirrors the propose→review→commit ethos |
| A separate "AI generation" page becomes an orphan artifact | Medium | Medium | Assisted creation is a **mode of the existing creation surface**, not a new page (§3 story convention) |
| Assisted-authoring spend runs away (spins are cheap to trigger) | Medium | High | Per-generation model selector (cheap model default), every spin logged + costed, spend visible in the Phase 6 cost view, Rp display |
| Generated character/reveal leaks a cross-chapter spoiler into play | Critical | Low | Generation only drafts the **authoring** record; spoilers are gated at the Phase 5 **compile** clamp (`knowledge_boundary`), never directly from generation |
| Drafts contradict existing entries / invent non-existent cast | Medium | Medium | Generator grounded in the story's own context; scene/beat references constrained to existing cast; author reviews before save |

---

*Document Version: 1.0 · Author: Zulfikar Hidayatullah · Created: June 2026*
