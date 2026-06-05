# Architecture — Directed Interactive Novel Engine

> **Living snapshot** distilled from the [architecture brief](../directed_interactive_novel_engine_v2.html) and [ADR 0001–0016](../adr/README.md) (all `Proposed` — planning stage). · **Last Updated:** 2026-06-04
>
> The ADRs are the source of truth for the *why*. This file is the structured *what*. Where they disagree, the ADR wins.

---

## 1. One-paragraph model

A custom interactive-fiction engine on the Claude API. The player experiences a story chapter-by-chapter with authored prose quality, player-input moments, and NPC agent turns. A hidden **beat document** steers all agents toward chapter goals **without** explicit plot instructions. The whole thing is built on **context isolation** between three agents, three orthogonal **leak guards**, and one shared **human review gate**.

---

## 2. Three agents + the Director

"Sees" means "acts within the limits of what it knows." An agent cannot act on information outside its context boundary. The **NPC context assembler** (ADR 0007) is the mechanical enforcement point.

| Agent | Sees | Must never |
|-------|------|-----------|
| **Narrator** | beat doc (full), full relationship mesh, scene history, player inputs, NPC responses | reveal info a character would not know (mesh is for atmosphere / body-language / room dynamics **only**) |
| **Player** | rendered prose only (+ a delivery/tone input channel) | — |
| **NPC** | own card + `knowledge_boundary`, own-perspective edge snapshot, leak-checked nudge, witnessed POV-projected excerpt | see the beat doc, other cards, other edges, or narrator instructions |
| **Director / Engine** | out-of-context orchestration (state machine, stall flags, word budgets, review gate) | inject orchestration signals into a narrative agent (stall flag is read by the engine only) |

Full diagram: [Diagrams/Agents/Context_Isolation.md](./Diagrams/Agents/Context_Isolation.md).

---

## 3. Session state machine (the spine)

```
[SESSION_START]
      ↓
[NARRATOR_TURN] → generate prose → scan for handoff signal
      ↓ branches to:
      ├── [PLAYER_MOMENT]   → render input box → wait → [NARRATOR_RESUMES]
      ├── [NPC_MOMENT]      → run interaction queue → resolve turns → [NARRATOR_RESUMES]
      └── [BEAT_COMPLETE]   → chapter wrap → summary generated
```

The state machine **is** the conductor — there is no separate orchestrator (see [GAPS](../adr/GAPS.md), "removed from the earlier audit"). The narrator loop's *internals* — prose generation under the POV contract, handoff detection, the mesh-awareness rule, witness tagging, the resume anchor, and **in-loop sequencing** (recorder → appraisal → drift/rupture; boundaries fire the batched subsystems) — are specified in **[ADR 0016](../adr/0016-narrator-agent-and-turn-loop.md)** (mirrored in §6.5 below). Full diagram: [Diagrams/Engine/Session_State_Machine.md](./Diagrams/Engine/Session_State_Machine.md).

### Narrator resume anchor (micro-continuity)

```
"You were narrating [scene type]. Last line before pause: '[exact last sentence]'.
 POV: [POV]. Tone: [tone]. Continue from here."
```

---

## 4. The behavior equation (numbers → behavior)

The core principle that turns relationship numbers back into in-character behavior:

```
rendered behavior = axis value  ×  expression mask  ×  (card voice + relational register)
```

Resolved per turn (ADR 0006):

```
1. base                edge's authored default register (may be a HARD PIN)
2. threshold selector  axis value → register variant (e.g. trust gradient L1..L4)
3. situational override event/context conditions (romantic-interest → boundary_protection)
4. emotional modulation current emotional state shifts the SURFACE, not the grammar
5. mask + awareness    suppress specific content / gate whether she can name it
```

The **psychological nudge** (ADR 0008) feeds *into* this pipeline as a bias term and is still gated at step 5 — it never puppets.

---

## 5. NPC turn = compile then act (ADR 0007)

Each NPC turn is two LLM calls. The assembler is both a **compiler** (structured data → folded prose blocks) and the **isolation boundary**.

```
NPC_CALL for [character]:
  system:
    [IDENTITY]    card + knowledge_boundary (clamped to current chapter)
    [SELF]        internal state: mood, active emotions, motivation
    [SNAPSHOT]    edges → present chars, each axis as (value × awareness) language,
                  FOLDED (capped feelings never stated plainly), own-perspective only
    [MASKS]       topic_flags + global mask + active states
    [DIRECTIVES]  resolved register → concrete behavior rules
    [NUDGE]       directed-pressure nudge (ADR 0008), framed as the character's own impulse
    [SCENE RULES] POV, tone, format
  user:
    [SCENE EXCERPT]  recorder `surface`, witness-filtered to this NPC, POV-projected (0009/0010),
                     decoded via reads_target, validated vs knowledge_boundary
    "How does [character] respond?"
```

Caching: stable blocks (identity, register) cached within a scene; the volatile snapshot recompiled after deltas land. Model tiering: major NPCs = full card (Sonnet); minor NPCs = compressed card (Haiku).

---

## 6. The narrator turn + recorder (ADR 0009/0010)

```
NARRATOR TURN
  beat plays out (prose · player input · NPC actions)
        ↓
  RECORDER (after each beat) → commits the beat record:
        surface      observable behavior + dialogue + HEDGED perceived reads ("looks/seems X")  → crosses agents
        true_state   per-character private feeling / intent  → NEVER cross-fed
        witnessed_by { char: full | overheard | partial }     pov_anchor: <scene contract anchor>
        ↓
  NPC TURNS via the ASSEMBLER (consume `surface` only, projected per NPC)
```

Two per-edge, directional legibility dials:

```
LEGIBILITY (how much shows)   baked into `surface` at the RECORDER
   = card base_opacity × axis intensity × awareness/mask × resolved register (composure+tells)   [target→observer]
DECODE (how well it's read)   applied per observer at PROJECTION
   = observer.reads_target   [observer→target]    accurate → faithful/sharpened · crashes → degraded
```

---

## 6.5 Narrator prompt + final context inventory (ADR 0016)

The narrator turn is **two calls** — a prose call (structured output: prose · handoff · inferred elapsed bucket) and a separate **recorder** sub-call (§6). The narrator sees the **beat doc (full)** and the **full mesh** — the NPC never does.

```
NARRATOR_CALL
 system:
   [POV CONTRACT]    scene pov_mode + pov_anchor + tone (ADR 0009)
   [MESH-AWARENESS]  full mesh → atmosphere / body-language / room-dynamics ONLY; never reveal what a
                     present character would not know; perceived reads MUST be hedged
   [BEAT]            current beat intent / goal / word_budget (ADR 0015)
   [DIRECTOR STATE]  word-budget warnings + ceiling pushes (engine → narrator, ADR 0008)
   [LOREBOOK]        keyword-matched world facts (ADR 0013)
   [SCENE STATE]     present characters · immediate context (~2000 tok) · scene summary
 user:
   [RESUME ANCHOR]   scene type · last line · POV · tone  (when resuming)
   "Continue narrating."
```

**Final context inventory — every prompt slot → its producer.**

*Narrator prompt:*

| Slot | Produced by |
|------|-------------|
| POV contract | scene (ADR 0009 / 0015) |
| Mesh-awareness directive + full mesh | ADR 0016 rule + edges (ADR 0002) |
| Beat (intent / goal / budget) | beat doc (ADR 0015) |
| Director state (budget warnings / ceiling) | engine clock (ADR 0008 / 0015) |
| Lorebook (world facts) | keyword match (ADR 0013) |
| Scene state (present chars · immediate ctx · summary) | context-memory (ADR 0012 / 0015) |
| Resume anchor | `sessions.resume_anchor` (ADR 0012) |

*NPC prompt (consolidates §5 with the now-settled producers):*

| Slot | Produced by |
|------|-------------|
| `[IDENTITY]` card + knowledge_boundary | per-chapter card snapshot (ADR 0013) |
| `[SELF]` mood · emotions · motivation | internal state (ADR 0014) |
| `[SNAPSHOT]` edges, value×awareness folded | edges (ADR 0002) + fold (ADR 0007) |
| `[MASKS]` topic_flags + global/state masks | edge `topic_flags` (ADR 0002) + masks (ADR 0014) |
| `[DIRECTIVES]` resolved register | registers (ADR 0006, compiled ADR 0013) |
| `[NUDGE]` directed-pressure, leak-checked | nudge derivation (ADR 0015 / 0008) |
| `[SCENE RULES]` POV / tone | scene (ADR 0009) |
| `[LOREBOOK]` knowledge-bounded world facts | keyword match clamped by knowledge_boundary (ADR 0013) |
| user `[SCENE EXCERPT]` | recorder `surface` (ADR 0010), witness-filtered + POV-projected (ADR 0009), decoded via reads_target (ADR 0006) |

In-loop order: **recorder → appraisal (over each character's projected `surface`) → review gate → ruptures in-scene / drift batched** (ADR 0016 §4).

This inventory is now **backed by data**: the [`prompt_blocks` registry](../adr/0020-prompt-block-registry.md) (ADR 0020) defines each block's purpose, source producers, fold instruction, order, and the **leak rules** that gate it — the assembler reads it to build the prompt, and the human block reference renders from the same rows (one definition, two surfaces). Every LLM call here goes through the [OpenRouter `LlmClient`](../adr/0017-llm-orchestration-openrouter.md) (ADR 0017), routed by **role** (`narrator_prose`, `recorder`, `npc_major`, `npc_minor`, `compiler`, …) and logged to `llm_calls`.

---

## 7. Three leak guards + one review gate

| Guard | Stops leaking | Where |
|-------|---------------|-------|
| Awareness-fold | the character's *own* capped feelings | ADR 0007 |
| Nudge compile | authorial / plot omniscience | ADR 0008 |
| POV projection | other characters' interiority + narrator omniscience | ADR 0009 / 0010 |

**Safety** (no leak) is structural and model-independent — it holds even on Haiku, enforced by the **hedged-attribution rule** (unhedged "is sad / is lying" rejected) + `knowledge_boundary` blocks on hidden facts. **Fidelity** (correct emotional read) is best-effort and human-backstopped.

One **review gate** (`propose → review → commit`) serves the producers: deltas + **emotion proposals** (ADR 0003 / 0014), nudge-compile (ADR 0008), beat records (ADR 0010), **card compiles** (ADR 0013), **bible generation** (ADR 0018), and **outline compiles** (ADR 0019). The human is the fidelity floor.

---

## 8. Delta engine (how relationships change)

| | DRIFT (ordinary) | RUPTURE (high-impact, flagged) |
|---|---|---|
| Source | per-scene appraisal | betrayal, confession, profound understanding |
| Magnitude | tiny, rate-scaled | large, explicit |
| Clamped to | soft bounds | hard bounds |
| Rewrite bounds / latch? | no | yes (= character development) |
| Flip register? | no | yes |
| Applied | batched at scene boundary | immediately, in-scene |

Appraisal **proposes**; the review gate **commits** with a mandatory `trigger` + append-only audit log. Triggers come from **universal priors** (shared) + **card sensitivities** (match-only salience; multiple matches → multiple proposals, which manufacture meaningful contradictions). Ruptures can install new sensitivities (scar triggers). Decay runs on **narrative time** and stops at latched scar floors.

---

## 9. Context-memory layers

```
IMMEDIATE CONTEXT    raw exchanges, last ~2000 tokens
SCENE SUMMARY        compressed after each scene unit (batched drift applies here; decay + emotion gap-drift when the boundary declares a real elapsed gap)
CHAPTER LOG          key beat events for continuity
LOREBOOK             world facts, injected on keyword match
```

---

## 10. What's open

The original flow gaps are **closed at the design level** (all ADRs `Proposed`): **O1** narrator loop → [ADR 0016](../adr/0016-narrator-agent-and-turn-loop.md), **O2** beat document + boundaries → [ADR 0015](../adr/0015-beat-document-and-boundaries.md), **O3** internal-state schema → [ADR 0014](../adr/0014-internal-state-schema.md), and the authoring/compile pipeline → [ADR 0013](../adr/0013-authoring-and-compile-pipeline.md). Tech stack + persistence are designed ([ADR 0011](../adr/0011-tech-stack.md) / [0012](../adr/0012-persistence-schema.md)); the full schema is in [DATABASE.md](./DATABASE.md). A second design cluster adds the **LLM client** ([ADR 0017](../adr/0017-llm-orchestration-openrouter.md): OpenRouter + `LlmClient`, model-role tiering, `llm_calls`), **character creation** + archetype library ([ADR 0018](../adr/0018-character-creation-pipeline.md), O5), **outline compilation** ([ADR 0019](../adr/0019-outline-compilation.md), O6), and the **prompt block registry** ([ADR 0020](../adr/0020-prompt-block-registry.md), O7).

**Genuinely open (O4 + audit):** the UI (review-gate surface, relationship viewer, player input + delivery channel); the **compile→act orchestration** *sequencing/queues* — the LLM **client** is now settled (ADR 0017), only call-batching/caching remain; the **interaction-queue** mechanics (referenced by ADR 0016, no ADR yet); a **home/format for the shared tunable config** (severity rubric, elapsed buckets, drift caps — the LLM tier→slug part now homes in `model_profiles`); and authoring content (only Luna's bible exists). See [../adr/GAPS.md](../adr/GAPS.md).

---

## 11. Application foundation (Phase 1 / Sprint 1)

The runtime above is the *product*; this section is the **shell** it runs inside — the part stood up in Sprint 1 (scaffold + sign-in). After this sprint the app boots, themes, authenticates, and navigates — it just has no stories yet.

**Stack (ADR 0011).** One integrated server-driven application — Laravel 13 (PHP 8.4) + Inertia v3 + Vue 3, **Wayfinder** typed routes (never Ziggy), Tailwind 4 + shadcn-vue, pnpm + Vite. Routing is typed and generated at build time, so a reference to a removed route fails the build, not production.

**Authentication.** Laravel **Fortify** (via the Vue starter kit). Sign-in issues an authenticated session and redirects to the workspace home (`/dashboard`); sign-out invalidates the session. Failed sign-ins are throttled (5/min per `email|ip`) and rejected with a single generic message that never discloses which field was wrong (no user enumeration). Auth surfaces are the only public pages — every authoring/play page sits behind the `auth` middleware and returns the user to their **intended destination** after login. Account *isolation* (owner-scoping, not RBAC) and configurable registration land in Sprint 2 (E2.2).

**Project standards (S-1.1.2).** Timestamps are **stored in UTC** and **rendered in Asia/Jakarta (WIB)**; provider cost is **rendered in Rupiah**. `config/app.php` exposes `display_timezone`, `display_locale`, and `currency`; `App\Http\Middleware\HandleInertiaRequests` shares them as a `standards` prop, and `resources/js/composables/useFormat.ts` (`formatDateTime` / `formatCurrency`) is the **single client-side formatter** the whole UI uses. The USD→IDR conversion source for provider cost is not yet decided (tracked PH-12, arrives with the call log in E5.3).

**Persistence engine.** MariaDB 11.7 (MySQL-8-compatible, JSON columns) — default connection `mariadb`; tests run against `novel_engine_test`. Migrations are reversible (up/down verified). The two engine realms (authoring/save) and global libraries land in Sprint 3–4; today only the foundation tables exist (users, auth/2FA, passkeys, cache, jobs, sessions).

Sign-in + route-protection flow: [Diagrams/App/Auth_Signin_Flow.md](./Diagrams/App/Auth_Signin_Flow.md). Endpoint/props contract: [../api/auth.md](../api/auth.md). Setup & troubleshooting: [../runbooks/local-setup-diagnostics.md](../runbooks/local-setup-diagnostics.md).
