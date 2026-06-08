# Directed Interactive Novel Engine — Scrum Program Backlog (v2, play-first)

**Document type:** Scrum requirement program (Phase → Epic → Sub-epic → User Story + Gherkin)
**App:** Directed Interactive Novel Engine (DINE)
**Language:** English (international project)
**Author:** Zulfikar Hidayatullah
**Created:** June 2026 · **Revision:** v2 — re-sliced from subsystem-first to **play-first vertical slices**
**Status:** Planning — design complete (ADR 0001–0020, all `Proposed`). Phase 0 (foundation + most authoring surfaces) is **already built**; the playable engine is what remains.

---

## 1. Why this is a v2 (read this first)

The first version of this program was sliced **horizontally by subsystem**: build *all* authoring surfaces to full depth, then *all* runtime. Two problems made that the wrong shape for **this** project:

1. **Play arrived far too late.** "Dive into a chapter and play" first appeared ~Sprint 26 — so the single most uncertain, highest-value question (*does the narrator → me → NPC loop actually feel good?*) was answered dead last, after months of building psychology machinery for a loop nobody had felt.
2. **It breaks the build model.** DINE is built **agentically**: one fresh, **stateless** agent per story, whose only memory is this backlog. A subsystem-first order specs cross-cutting in-play surfaces *before the context that hosts them exists*, so the amnesiac agent can only build **detached artifacts**. The proof is in the codebase: the original Phase 1 told an agent to "build the review-gate foundation" before any play loop or producer existed, so it built a standalone `/reviews` page that nothing feeds — `ReviewGateService::propose()` is never called and the page renders an empty teaching state.

**v2 re-slices the exact same design (ADR 0001–0020, every old story) into play-first vertical slices.** Nothing in the design is discarded — only the **order and the integration points** change, so that (a) you can play early and deepen, and (b) each stateless agent always wires new work into a host that already exists.

> **Source-of-truth rule.** This backlog describes **observable behavior** (what an author/player/system can do and perceive). The **[ADRs](../docs/adr/README.md)** hold the *why/how* (data shapes, leak guards, algorithms). The **[architecture brief](../docs/directed_interactive_novel_engine_v2.html)** holds the *what* (the app and its play loop). Priority for understanding: **brief first (what), ADRs second (how)**. Where this backlog and an ADR disagree on behavior, raise it — the ADR is the design of record.

---

## 2. The context-management spine (the core of the app)

**Context management is the heart of DINE, not a phase of it.** The entire engine exists to assemble the right **final prompt** for each of three agents while holding a hard **isolation boundary**. Everything else (axes, masks, registers, nudges, the delta engine) exists to *feed those prompts*.

```
THREE AGENTS — layered context isolation
  NARRATOR → sees beat doc (full) + full relationship mesh + scene history + player input + NPC responses.
             Uses the mesh for atmosphere / body-language / room-dynamics ONLY.
  PLAYER   → sees rendered prose ONLY.
  NPC      → sees ONLY: own card (+ knowledge_boundary), own internal state, own edges (own-perspective),
             the leak-checked nudge addressed to it, and its witnessed, POV-projected scene excerpt.
             NEVER: the beat doc, another character's card or edges, another character's true_state,
             or narrator instructions.

THE FINAL PROMPT (assembled by the ADR 0007 assembler, driven by the ADR 0020 prompt_blocks registry)
  NARRATOR system: [POV_CONTRACT][MESH_AWARENESS][BEAT][DIRECTOR_STATE][LOREBOOK][SCENE_STATE]
           user:   [RESUME_ANCHOR]
  NPC      system: [IDENTITY][SELF][SNAPSHOT][MASKS][DIRECTIVES][NUDGE][SCENE_RULES]
           user:   [SCENE_EXCERPT]
```

The **assembler ([ADR 0007](../docs/adr/0007-npc-context-assembly.md)) is both a compiler** (structured data → folded prose blocks) **and the isolation boundary** (the one place that guarantees an NPC sees only its own data + what it witnessed). The **`prompt_blocks` registry ([ADR 0020](../docs/adr/0020-prompt-block-registry.md))** is the single source of truth that *drives* assembly: each block declares its `agent`, `section`, `order_index`, `compile_instruction`, and **`leak_rules`**. The registry is already seeded (Phase 0) with the ~15 engine blocks.

**The three leak guards** (each a context-management mechanism, each a `leak_rule` the assembler enforces):

- `awareness_fold` — a capped feeling is never stated plainly (own capped feelings). [ADR 0007]
- `omniscient_authoring` — author-side omniscient input (beat intent) must be compiled into a bounded nudge before it can cross to a character. [ADR 0008]
- `hedged_attribution` + POV projection — others' hidden truth and narrator omniscience never reach an agent; `true_state` never crosses, only the hedged `surface` does. [ADR 0009 / 0010]
- (plus `knowledge_boundary` and `own_perspective_only` as structural clamps.)

**How v2 uses this as the spine:** the assembler + registry is the **contract built once** (Phase 2). Every later phase **lights up more blocks** of the same final prompt and **activates that block's leak guard exactly when the data behind it first exists** — never before. The phase map (§6) is literally "which blocks light up, and which guard turns on, this phase."

---

## 3. The stateless-agent story convention (the structural fix)

Because each story is implemented by a **fresh agent whose only memory is the story text**, every story in this program (Phases 1–7) carries three machine-checkable fields in its Technical Notes, in addition to its Gherkin:

- **Preconditions** — what must already exist in the codebase for this story to be buildable (e.g. "the assembler from S-2.1.1", "the `play_sessions` fork from S-1.1.1"). If a precondition is not yet built, the story is mis-ordered.
- **Integrates-into** — the **existing** surface/route/service this story extends. A story must **never** stand up a new detached page when it belongs inside an existing host. (This is the rule the orphaned `/reviews` page violated.)
- **Leak-guards** — which of the existing guards (`awareness_fold`, `knowledge_boundary`, `hedged_attribution`, `own_perspective_only`, `omniscient_authoring`, `none`) this story must apply, named from the `prompt_blocks` registry — never inventing a new guard.

> **Ordering invariant:** a story may only be scheduled in a sprint after all its `Preconditions` are met by an earlier (already-built) story. This is what lets an amnesiac agent "catch the track."

---

## 4. Locked technical context (from ADR 0011 / 0012 / 0017)

- **Backend:** Laravel 13.x (PHP 8.4), pragmatic Service pattern (logic in `app/Services/`, not fat controllers).
- **Frontend:** Vue 3 + Inertia.js v3, **Wayfinder** (typed routes, *not* Ziggy), Tailwind 4, shadcn-vue.
- **Tooling:** pnpm, Vite. Lint via `pnpm lint`.
- **Database:** MariaDB (MySQL-8-compatible, JSON columns), two realms (authoring immutable / save mutable) — **both already migrated** (see Phase 0).
- **LLM:** Claude models via the **OpenRouter** gateway behind a thin `LlmClient`; routed by **role**.
- **Auth:** Laravel Fortify (passkeys available) via the official Vue starter kit.
- **Standards:** Timezone Asia/Jakarta (WIB); currency Rupiah (Rp) for display of provider cost.

---

## 5. Actors / roles

| Role | Who | Sees / does |
|------|-----|-------------|
| **Account Owner / User** | The human operating the app (single-author by default; multi-user-ready) | Authentication, account, API keys, owns stories & saves |
| **Author** | The same human in their *authoring* capacity | Creates/edits stories, characters, lorebook, outlines, beats, settings; runs the review gate |
| **Player** | The same human in their *playing* capacity | Reads prose, gives input + delivery, regenerates ("spin"), reviews proposals |
| **System / Engine** | The runtime (narrator, NPC assembler, appraisal, recorder, clocks) | Generates prose, assembles context, proposes deltas/records, enforces isolation & leak guards |

> No operator/admin RBAC by design — "multi-user" means **account isolation** (each owner sees only their own stories/saves), not roles.

---

## 6. Phase map — play-first vertical slices

Each phase ends in a **felt, playable increment** and lights up more of the per-agent final prompt. "Folds" shows which old-version content is re-sliced in (nothing is lost).

| Phase | Theme | Felt outcome | Blocks lit / guards activated | Folds (old v1) | Est. SP |
|-------|-------|--------------|-------------------------------|----------------|---------|
| **0** | Foundation & authoring shell — **DONE** | A themed, authed app with both DB realms, the LLM client, seeded libraries, and story/lorebook/reveal-ledger surfaces | `prompt_blocks` seeded; no agent runs yet | old P1 + P2 (E1–E4) | — (built) |
| **1** | Walking Skeleton — front door + narrator → me loop | I **sign in → open a book → pick a chapter → I'm in the Writing/Play page** playing a solo narrated scene (the narrator writes, I respond, it continues). New books drop me in via a **POV-gated onboarding**; the save fork is **invisible** | Front door (E0): Home, chapter entrance, Writing/Play shell host. Narrator: `POV_CONTRACT, BEAT, SCENE_STATE, LOREBOOK, RESUME_ANCHOR`. Player = prose only | old P5 E1/E2/E3.1/E5/E6 + minimal P3/P4 | ~97 |
| **2** | One Live Character — SillyTavern parity | I play a scene with **one in-character NPC** who only knows what it witnessed | NPC: `IDENTITY, SCENE_RULES`, user `SCENE_EXCERPT`. Guards on: `knowledge_boundary, hedged_attribution, own_perspective_only`. Recorder two-layer + POV projection. First inline review-gate producer | old P5 E3.2/E4/E6 + P6 E1 (thinned) + P7 E2 | ~60 |
| **3** | Multi-Character Play | Multiple NPCs **take turns naturally** in one scene | Orchestration of the same blocks across NPCs; no new guard | old P6 E8/E9 | ~27 |
| **4** | Directed Structure — NovelCrafter spine | Beats have **goals**; the narrator steers; the **nudge** pressures a character — directed play | Narrator: `BEAT` (enriched), `DIRECTOR_STATE`. NPC: `NUDGE` (guard: `omniscient_authoring` + `knowledge_boundary`) | old P4 (all) + P5 E5.2 + P6 E7 | ~68 |
| **5** | Psychology Depth — more than SillyTavern | Characters **evolve** (relationships, scars, moods) for explainable reasons; spoiler-safe per chapter | Narrator: `MESH_AWARENESS` (turns on once the mesh exists). NPC: `SELF, SNAPSHOT, MASKS, DIRECTIVES` (guards: `awareness_fold, own_perspective_only`); nudge becomes register/mask/awareness-gated | old P3 (all) + P6 E2–E7 | ~165 |
| **6** | Control & Observability | Full authorial control: review everything mid-play, spin, inspect relationships, watch spend | Unified review surface, relationship viewer, cost dashboard, registry/tunable management | old P7 + P2 E5 + P4 E5 | ~90 |
| **7** | Assisted Authoring — three ways to make every entry | For **any** entry (character, lorebook, scene, beat, chapter, reveal) I can **type it**, draft it from a one-line **brief**, or generate it **in full** — then save / spin / edit / discard; whatever a model returns lands in the **same fields**, and I pick the model | Reuses the structured-output + `prompt_blocks` contract for authoring generation; authoring realm (`none` guards) | old P4 outline-compile (0019) + P5 character AI/hybrid (0018), **generalized to every entry** | ~50 |

**Remaining program total:** ~537 story points across Phases 1–7 (Phase 0 already delivered). Sprint numbering restarts at **Phase 1, Sprint 1** (Phase 0 is the prior build). **Phase 7 is authoring-only** (no play-loop dependency) and so can be scheduled independently once the entry hosts + LLM client exist.

---

## 7. Dependency graph

```
Phase 0 (DONE: app, auth, both DB realms, LlmClient, seeded libraries incl. prompt_blocks,
         story / lorebook / reveal-ledger surfaces)
   │
   └─► Phase 1  Walking Skeleton (front door + narrator → me loop) ── builds the play front door (E0: Home → chapter → Writing/Play page,
          │                                                            POV-gated onboarding, fork hidden, Saves demoted) + the loop spine + narrator final prompt
          │
          └─► Phase 2  One Live Character                        ── builds the assembler + isolation boundary + recorder/projection
                 │                                                  (the contract every later phase enriches)
                 └─► Phase 3  Multi-Character Play                ── orchestrates many NPC turns over the same assembler
                        │
                        └─► Phase 4  Directed Structure           ── lights up BEAT/NUDGE/DIRECTOR_STATE + the word-budget clock
                               │
                               └─► Phase 5  Psychology Depth       ── lights up MESH_AWARENESS + SELF/SNAPSHOT/MASKS/DIRECTIVES + the delta engine
                                      │
                                      └─► Phase 6  Control & Observability ── surfaces all of the above to the human

Phase 0/1 (entry hosts: story/character/lorebook/reveal + chapter/scene/beat; LlmClient + model roles)
   └─► Phase 7  Assisted Authoring ── authoring-only side track (no play-loop dependency): the shared three-mode
                                       (Manual/Brief/Full) creation contract for EVERY entry, which the Phase 4
                                       outline-compile (0019) and Phase 5 character AI/hybrid (0018) plug into
```

The **assembler + `prompt_blocks` registry** (built in Phase 2) is the cross-cutting backbone; Phases 4–5 add producers and turn on each block's `leak_rule`. The **review gate** is *not* a foundation page — it first becomes real inline in Phase 2 (reviewing beat records), gains producers through Phases 4–5, and is unified into one surface in Phase 6 (where the orphaned `/reviews` page is repurposed).

The **play front door** (Phase 1 · Epic E0) is the cross-cutting *UX* backbone: **Home → open a book → select a chapter → land in the Writing/Play page** is the single way in, and the **Writing/Play page is the host** every later play story mounts into (the prose reader, the narrator → me loop, then NPC turns in Phase 2). The two-realm fork (ADR 0012) is **kept but invisible** — chapter selection silently resumes-or-creates the playthrough — and **"Saves" is demoted** from the entrance to an optional branches/history panel. This is the deliberate fix for the disease in §1: a play surface must never be a detached artifact reached through configuration; it is the front door, built first, that the engine fills in.

The **assisted-authoring contract** (Phase 7 · Epic E1) is the cross-cutting *authoring* backbone: **every** entry creation surface offers the same three doors — **Manual** (the existing hand-authored form, still the default), **Brief** (write a one-line intent → a model drafts the full entry), and **Full** (draft from minimal seed). Whatever a model returns is **validated into the entry's canonical fields** (the same shape + validation a manual save uses, mirroring the narrator prose schema), then the author **saves / spins / edits / discards** — the engine never auto-commits a generated entry, and the author picks which model drafts each one. Like the play front door, this is built **once into the existing creation hosts** (never a detached "AI generation" page), and the deeper per-entry generators — character AI/hybrid + archetypes (ADR 0018) and outline→beats compile (ADR 0019) — **plug into this same contract** rather than re-implementing generation.

---

## 8. ID & estimation conventions

| Level | Pattern | Example |
|-------|---------|---------|
| Phase | Phase N | Phase 2 |
| Epic | E[N] | E1, E2 (numbering restarts per phase) |
| Sub-epic | E[N].[M] | E1.1, E1.2 |
| User Story | S-[N].[M].[X] | S-1.1.1 |

- **Story points (Fibonacci):** 1 trivial · 2 simple · 3 moderate · 5 complex · 8 very complex · 13 epic-level.
- **Priority:** Critical (MVP) · High (core UX) · Medium (deferrable) · Low (polish).
- **Sprint length:** 1 week; sprint numbers are program-global, restarting at Phase 1.

---

## 9. Global Definition of Done (DoD)

A user story is **DONE** when:

- [ ] Acceptance criteria all met and demoed.
- [ ] Its `Preconditions` were genuinely already built; it wired into the declared `Integrates-into` host (no orphan page); its `Leak-guards` are applied and named only from the existing guard set.
- [ ] Automated tests written and passing — unit + feature; **isolation/leak-guard stories require explicit negative tests** (assert forbidden data never reaches a prompt).
- [ ] `pnpm lint` clean; type-check passes; Wayfinder types regenerate without errors.
- [ ] No Critical/High defects open.
- [ ] UX states covered: loading, empty, error, success, and unauthorized.
- [ ] Responsive (desktop + tablet) and keyboard-accessible for interactive controls.
- [ ] LLM-touching stories: failure/timeout/malformed-output paths handled and logged to the call log.
- [ ] Append-only invariants respected (no UPDATE/DELETE on audit tables); migrations reversible (most phases reuse the Phase-0 schema — no new migration unless noted).

---

## 10. Cross-cutting non-functional requirements (NFRs)

| Area | Requirement |
|------|-------------|
| **Security** | All authoring/save data is account-scoped; an owner can never read another owner's stories/saves. API keys encrypted at rest, never returned in plaintext, never logged. `llm_calls.messages` (may embed `true_state`) is debug-gated and never agent-readable. |
| **Isolation (engine)** | The three leak guards + the assembler boundary are testable invariants, not best-effort. Safety holds at any model tier. |
| **Performance** | Authoring pages interactive < 2s. A runtime beat (~10+ LLM calls for a 3-NPC scene) streams progressively; the player is never blocked on a frozen screen — partial prose + progress indication. |
| **Observability** | Every LLM call logged (role, model, tokens, cost, latency, status). Per-beat call count and cost visible. |
| **Accessibility** | Semantic structure, keyboard nav, sufficient contrast in both themes, prose readable (line length, font scaling). |
| **Internationalization** | UI copy in English; content (prose) is author-defined. Times rendered Asia/Jakarta; provider cost rendered in Rupiah. |
| **Cost control** | Model tiering by role; block caching within a scene; the player/author can see and cap spend. |

---

## 11. ADR → Phase traceability (v2 ordering)

| ADR | Subsystem | Primarily in (v2) |
|-----|-----------|-------------------|
| 0011 Tech stack | scaffold/stack | Phase 0 |
| 0012 Persistence (two realms) | schema | Phase 0 (schema) + every data story |
| 0017 LLM/OpenRouter client | provider, key mgmt, call log | Phase 0 (client) + Phase 3/6 (orchestration, cost UI) |
| 0020 Prompt block registry | block specs drive assembly | Phase 0 (seed) + Phase 2 (assembler consumes) + Phase 6 (mgmt UI) |
| 0016 Narrator loop | two-call turn, spine, sequencing | Phase 1 (loop + prose) + Phase 2 (recorder sub-call) + Phase 4 (clock/boundaries) + Phase 5 (mesh-awareness) |
| 0009 POV projection | per-NPC excerpt projection | Phase 2 |
| 0010 Recorder mechanics | surface/true_state/witness, legibility | Phase 2 |
| 0007 NPC context assembly | compile→act, isolation boundary | Phase 2 (thin) + Phase 5 (full blocks) |
| 0019 Outline compilation | outline → chapters/scenes/beats | Phase 4 (bulk compile) + Phase 7 (reuses the assisted-creation contract) |
| 0015 Beat document + boundaries | beats, BEAT_DONE, elapsed buckets | Phase 1 (minimal beat) + Phase 4 (full) |
| 0008 Psychological nudge | directed pressure, ladder, ceiling | Phase 4 (derive + runtime) + Phase 5 (register-gated) |
| 0018 Character creation | AI/manual/hybrid + archetypes | Phase 1 (minimal manual) + Phase 7 (generalized 3-mode creation contract) + Phase 5 (full pipeline / archetypes / psychology fields) |
| 0001 Three-layer character data | cards/edges/internal split | Phase 1 (card) + Phase 5 (edges/internal) |
| 0013 Authoring/compile pipeline | bible→card, lorebook, reveal ledger | Phase 0 (lorebook/ledger) + Phase 5 (compile/clamp) |
| 0002 Relationship edge schema | edges, axes, priors, register binding | Phase 5 |
| 0005 Trigger taxonomy | sensitivities, universal priors | Phase 0 (priors seed) + Phase 5 (appraisal) |
| 0006 Register system | behavioral grammar | Phase 5 |
| 0003 Delta engine | drift/rupture, propose→review→commit | Phase 5 |
| 0004 Decay + latched scars | narrative-time decay, commitment/trauma | Phase 5 |
| 0014 Internal-state schema | `[SELF]`, emotions, masks | Phase 5 |

---

## 12. Program-level risk register

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| A stateless agent builds a detached artifact (the orphan-page disease) | High | High | The story convention (§3): every story declares `Preconditions` / `Integrates-into` / `Leak-guards`; ordering invariant enforced; review/spin/viewer surfaces only specced after their host exists |
| Context-isolation leak (an agent receives data it must not have) | Critical | Medium | Assembler is the single boundary; registry-driven `leak_rules`; explicit negative tests in every assembly/projection story; separate `true_state` table; human review gate as the floor |
| The core loop doesn't feel good | High | Medium | Play-first slicing surfaces the loop in Phase 1–2 (not Sprint 26); deepen only a loop already validated |
| Runtime cost/latency too high (a beat is ~10+ calls) | High | High | Model-role tiering, block caching, progressive streaming, per-beat spend visibility + caps; orchestration in Phase 3 |
| Spoiler leak from an early-chapter card | Critical | Medium | Reveal ledger + section tags → `knowledge_boundary` clamp at compile (Phase 5), all behind the review gate |
| API key compromise / leakage | Critical | Low | Encrypt at rest, never echo, never log; scoped to owner (Phase 0) |
| Authoring burden too high (engine is dense) | Medium | High | Minimal manual character/beat in Phase 1; **generalized three-mode (Manual/Brief/Full) assisted creation for every entry in Phase 7**; AI/hybrid + archetypes + outline compile deepen it in Phase 4–5 once play justifies them |
| Solo-dev / single-agent throughput | Medium | High | Vertical slices ship independently; ruthless Critical/High gating; the engine subsystems are already designed (ADRs) |

---

## 13. Phase documents

| Phase | File |
|-------|------|
| 0 (DONE) | [phase-0-foundation-asbuilt.md](./phase-0-foundation-asbuilt.md) |
| 1 | [phase-1-walking-skeleton.md](./phase-1-walking-skeleton.md) |
| 2 | [phase-2-one-live-character.md](./phase-2-one-live-character.md) |
| 3 | [phase-3-multi-character-play.md](./phase-3-multi-character-play.md) |
| 4 | [phase-4-directed-structure.md](./phase-4-directed-structure.md) |
| 5 | [phase-5-psychology-depth.md](./phase-5-psychology-depth.md) |
| 6 | [phase-6-control-observability.md](./phase-6-control-observability.md) |
| 7 | [phase-7-assisted-authoring.md](./phase-7-assisted-authoring.md) |

---

*Document Version: 2.0 (play-first re-slice) · Author: Zulfikar Hidayatullah · Created: June 2026*
