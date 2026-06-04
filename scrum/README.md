# Directed Interactive Novel Engine — Scrum Program Backlog

**Document type:** Scrum requirement program (Phase → Epic → Sub-epic → User Story + Gherkin)
**App:** Directed Interactive Novel Engine (DINE)
**Language:** English (international project)
**Author:** Zulfikar Hidayatullah
**Created:** June 2026
**Status:** Planning — design complete (ADR 0001–0020, all `Proposed`); application not yet built.

---

## 1. What this program covers

This is the **end-to-end build backlog**, from an empty repository to a finished, playable, authorable product. It deliberately covers **both**:

1. **The application shell** the engine needs to be a real product — authentication, users, API-key & provider management, story/character/lorebook management, settings, prompting configuration, navigation, theming, and the full player/author UI/UX.
2. **The narrative engine** specified in [`docs/adr/`](../docs/adr/README.md) (ADR 0001–0020) — character psychology, the delta engine, the narrator loop, POV projection, the recorder, the nudge, context assembly, and the review gate.

> **Source-of-truth rule.** User stories and acceptance criteria describe **observable behavior** (what an author/player/system can do and perceive). The **ADRs** hold the *why* and the *how* (data shapes, leak guards, algorithms). Each sub-epic's **Technical Notes** points back to the governing ADR(s). Where this backlog and an ADR disagree on behavior, raise it — the ADR is currently the design of record.

> **UI/UX note.** UX is in scope and has first-class stories (theming, responsiveness, loading/empty/error states, accessibility). Acceptance criteria still describe *intent and outcome* rather than specific widgets/colors, so the chosen UI kit (shadcn-vue) can evolve without rewriting the spec.

---

## 2. Locked technical context (from ADR 0011 / 0012 / 0017)

- **Backend:** Laravel 13.x (PHP 8.3+), pragmatic Service pattern.
- **Frontend:** Vue 3 + Inertia.js v3, **Wayfinder** (typed routes, *not* Ziggy), Tailwind 4, shadcn-vue.
- **Tooling:** pnpm, Vite 7. Lint via `pnpm lint`.
- **Database:** MariaDB 11.7 (MySQL-8-compatible), two realms (authoring immutable / save mutable).
- **LLM:** Claude models via the **OpenRouter** gateway behind a thin `LlmClient`; routed by **role**.
- **Auth:** Laravel Fortify (passkeys available) via the official Vue starter kit.
- **Standards:** Timezone Asia/Jakarta (WIB); currency Rupiah (Rp) for display of provider cost.

---

## 3. Actors / roles

| Role | Who | Sees / does |
|------|-----|-------------|
| **Account Owner / User** | The human operating the app (single-author by default; multi-user-ready) | Authentication, account, API keys, owns stories & saves |
| **Author** | The same human in their *authoring* capacity | Creates/edits stories, characters, lorebook, outlines, beats, settings; runs the review gate |
| **Player** | The same human in their *playing* capacity | Reads prose, gives input + delivery, regenerates ("spin"), reviews proposals |
| **System / Engine** | The runtime (narrator, NPC assembler, appraisal, recorder, clocks) | Generates prose, assembles context, proposes deltas/records, enforces isolation & leak guards |

> The engine has **no operator/admin role hierarchy** by design (it is single-author-centric). "Multi-user" here means *account isolation* (each owner sees only their stories/saves), not RBAC.

---

## 4. Phase map

| Phase | Theme | Goal | Key Epics | Est. SP | Est. Sprints |
|-------|-------|------|-----------|---------|--------------|
| **1** | Foundation, Auth & App Shell | A running, themed, authenticated app with the full DB schema, the LLM client, and API-key/provider management | Scaffold · Auth & Users · App Shell/UX · Persistence Schema · LLM Provider & API-key Mgmt · Global Libraries & Review-gate foundation | ~115 | 1–6 |
| **2** | Story & World Management | Author can create/manage stories, lorebook, reveal ledger, and engine config | Story Mgmt · Authoring Workspace · Lorebook Mgmt · Reveal Ledger · Tunable Engine Config | ~63 | 7–11 |
| **3** | Character Authoring & Compile | Create characters (AI/manual/hybrid), compile spoiler-safe cards, author edges/registers/sensitivities | Creation Pipeline · Archetype Library · Bible→Card Compile · Edges & Priors · Registers · Sensitivities · Character Mgmt UI | ~100 | 12–19 |
| **4** | Story Structure & Prompting | Turn outlines into chapters/scenes/beats (or author manually), derive nudges, manage prompt blocks | Outline Compilation · Manual Authoring · Beat Document · Nudge Derivation · Prompt Block Registry & Prompting Settings | ~62 | 20–25 |
| **5** | Runtime: Narrator Loop & Session | The narrator → player → narrator spine running with recorder, POV projection, memory, boundaries, save/resume | Session & Save Mgmt · State Machine · Narrator Turn (prose+recorder) · POV Projection · Memory & Boundaries · Player Input & Delivery | ~111 | 26–34 |
| **6** | Runtime: NPC Behaviour & Delta Engine | Full NPC psychology and the relationship simulation, with orchestration | NPC Assembly · Edges/Axes/Awareness · Delta Engine · Decay & Scars · Internal State · Register Resolution · Nudge (runtime) · Interaction Queue · Orchestration | ~144 | 35–45 |
| **7** | Player Experience, Spin & Review UI | The player UI, regenerate/"spin" mode, the shared review-gate surface, relationship viewer, observability | Play UI · Input UX & Delivery · Spin/Regenerate · Review-Gate Surface · Relationship Viewer · Cost/Latency & Break-glass | ~87 | 46–53 |

**Program total:** ~682 story points · ~53 one-week sprints (solo-dev estimate; parallelizes with more contributors). Phases are dependency-ordered but Phases 2–4 (authoring) and the latter half of 5–7 (runtime) can overlap once Phase 1 lands.

---

## 5. Dependency graph (high level)

```
Phase 1 (Foundation/Auth/Shell/DB/LLM) ─┬─► Phase 2 (Story & World Mgmt)
                                         │
                                         ├─► Phase 3 (Character Authoring) ──► needs Phase 2 (a story)
                                         │
                                         └─► Phase 4 (Story Structure)    ──► needs Phase 3 (chars for nudge_target/pov_anchor)
                                                                                │
Phase 5 (Narrator Loop & Session) ◄──────────── needs Phases 2–4 (committed authoring content)
        │
        └─► Phase 6 (NPC Behaviour & Delta) ◄── needs Phase 5 (recorder surface to appraise)
                    │
                    └─► Phase 7 (Player UX, Spin, Review UI) ◄── surfaces Phases 5–6 to the human
```

The **shared review gate** (foundation in Phase 1 E6, full UI in Phase 7 E4) is cross-cutting — every compile/proposal producer in Phases 2–6 enqueues to it.

---

## 6. ID & estimation conventions

| Level | Pattern | Example |
|-------|---------|---------|
| Phase | Phase N | Phase 3 |
| Epic | E[N] | E1, E2 (numbering restarts per phase) |
| Sub-epic | E[N].[M] | E1.1, E1.2 |
| User Story | S-[N].[M].[X] | S-1.1.1 |

- **Story points (Fibonacci):** 1 trivial · 2 simple · 3 moderate · 5 complex · 8 very complex · 13 epic-level.
- **Priority:** Critical (MVP, app can't function without it) · High (core UX) · Medium (improves UX, deferrable) · Low (polish).
- **Sprint length:** 1 week. Sprint numbers in each phase doc are program-global (continuing from the previous phase).

---

## 7. Global Definition of Done (DoD)

A user story is **DONE** when:

- [ ] Acceptance criteria all met and demoed.
- [ ] Code reviewed (or self-reviewed with checklist for solo work).
- [ ] Automated tests written and passing — unit + feature; **isolation/leak-guard stories require explicit negative tests** (assert forbidden data never reaches a prompt).
- [ ] `pnpm lint` clean; type-check passes; Wayfinder types regenerate without errors.
- [ ] No Critical/High defects open.
- [ ] UX states covered: loading, empty, error, success, and unauthorized.
- [ ] Responsive (desktop + tablet) and keyboard-accessible for interactive controls.
- [ ] LLM-touching stories: failure/timeout/malformed-output paths handled and logged to the call log.
- [ ] Append-only invariants respected (no UPDATE/DELETE on audit tables); migrations reversible.
- [ ] Docs updated where behavior diverges from an ADR (note it in `docs/guides/PLACEHOLDER_TRACKING.md`).

---

## 8. Cross-cutting non-functional requirements (NFRs)

| Area | Requirement |
|------|-------------|
| **Security** | All authoring/save data is account-scoped; an owner can never read another owner's stories/saves. API keys are encrypted at rest, never returned in plaintext after save, never logged. `llm_calls.messages` (may embed `true_state`) is debug-gated and never agent-readable. |
| **Isolation (engine)** | The three leak guards (awareness-fold, nudge-compile, POV projection) and the assembler boundary are testable invariants, not best-effort. Safety holds at any model tier. |
| **Performance** | Authoring pages interactive < 2s. A runtime beat (~10+ LLM calls for a 3-NPC scene) streams progressively; the player is never blocked on a frozen screen — partial prose + progress indication. |
| **Observability** | Every LLM call logged (role, model, tokens, cost, latency, status). Per-beat call count and cost visible. |
| **Accessibility** | Semantic structure, keyboard nav, sufficient contrast in both themes, prose readable (line length, font scaling). |
| **Internationalization** | UI copy in English; content (prose) is author-defined. Times rendered Asia/Jakarta; provider cost rendered in Rupiah. |
| **Cost control** | Model tiering by role; block caching within a scene; the player/author can see and cap spend. |

---

## 9. ADR → Phase traceability

| ADR | Subsystem | Primarily in |
|-----|-----------|--------------|
| 0011 Tech stack | scaffold/stack | Phase 1 |
| 0012 Persistence (two realms) | schema | Phase 1 (schema) + every data story |
| 0017 LLM/OpenRouter client | provider, key mgmt, call log | Phase 1 |
| 0020 Prompt block registry | block specs drive assembly | Phase 1 (seed) + Phase 4 (mgmt) |
| 0013 Authoring/compile pipeline | bible→card, lorebook, reveal ledger | Phase 2 (lorebook/ledger) + Phase 3 (compile) |
| 0018 Character creation | AI/manual/hybrid + archetypes | Phase 3 |
| 0001 Three-layer character data | cards/edges/internal split | Phase 3 |
| 0002 Relationship edge schema | edges, axes, priors, register binding | Phase 3 (author) + Phase 6 (runtime) |
| 0005 Trigger taxonomy | sensitivities, universal priors | Phase 3 (author) + Phase 6 (appraisal) |
| 0006 Register system | behavioral grammar | Phase 3 (author) + Phase 6 (resolution) |
| 0019 Outline compilation | outline → chapters/scenes/beats | Phase 4 |
| 0015 Beat document + boundaries | beats, BEAT_DONE, elapsed buckets | Phase 4 (author) + Phase 5 (runtime) |
| 0008 Psychological nudge | directed pressure, ladder, ceiling | Phase 4 (derivation) + Phase 6 (runtime) |
| 0016 Narrator loop | two-call turn, sequencing | Phase 5 |
| 0009 POV projection | per-NPC excerpt projection | Phase 5 |
| 0010 Recorder mechanics | surface/true_state/witness, legibility | Phase 5 |
| 0014 Internal-state schema | `[SELF]`, emotions, masks | Phase 6 |
| 0007 NPC context assembly | compile→act, isolation boundary | Phase 6 |
| 0003 Delta engine | drift/rupture, propose→review→commit | Phase 6 |
| 0004 Decay + latched scars | narrative-time decay, commitment/trauma | Phase 6 |

---

## 10. Program-level Risk Register

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Context-isolation leak (an agent receives data it must not have) | Critical | Medium | Structural guards (separate `true_state` table, hedged-attribution validator, knowledge-boundary clamp) + explicit negative tests in every assembly/projection story; the human review gate as the floor |
| Runtime cost/latency too high (a beat is ~10+ calls) | High | High | Model-role tiering, block caching, progressive streaming, per-beat spend visibility + caps; orchestration epic in Phase 6 |
| Spoiler leak from an early-chapter card (future-arc content) | Critical | Medium | Reveal ledger + section tags → `knowledge_boundary` clamp at compile, all behind the review gate |
| API key compromise / leakage | Critical | Low | Encrypt at rest, never echo, never log; scoped to owner |
| LLM provider outage / model deprecation | High | Medium | Provider-agnostic `LlmClient`; role→slug is config; graceful failure + retry/backoff + manual fallback where possible |
| Authoring burden too high (engine is dense) | Medium | High | AI/hybrid creation modes, archetype libraries, outline compilation, sensible seeded defaults |
| Scope creep across 7 phases | High | High | Strict Critical/High MVP gating per phase; Medium/Low deferrable; phases shippable independently |
| Solo-dev throughput | Medium | High | Phases overlap after Phase 1; ruthless prioritization; the engine subsystems are already designed (ADRs) |

---

## 11. Phase documents

| Phase | File |
|-------|------|
| 1 | [phase-1-foundation-auth-shell.md](./phase-1-foundation-auth-shell.md) |
| 2 | [phase-2-story-world-management.md](./phase-2-story-world-management.md) |
| 3 | [phase-3-character-authoring.md](./phase-3-character-authoring.md) |
| 4 | [phase-4-story-structure-prompting.md](./phase-4-story-structure-prompting.md) |
| 5 | [phase-5-narrator-loop-session.md](./phase-5-narrator-loop-session.md) |
| 6 | [phase-6-npc-behaviour-delta-engine.md](./phase-6-npc-behaviour-delta-engine.md) |
| 7 | [phase-7-player-experience-review-ui.md](./phase-7-player-experience-review-ui.md) |

---

*Document Version: 1.0 · Author: Zulfikar Hidayatullah · Created: June 2026*
