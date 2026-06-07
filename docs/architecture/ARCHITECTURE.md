# Architecture — Directed Interactive Novel Engine

> **Living snapshot** distilled from the [architecture brief](../directed_interactive_novel_engine_v2.html) and [ADR 0001–0016](../adr/README.md) (all `Proposed` — planning stage). · **Last Updated:** 2026-06-05
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

**Authentication.** Laravel **Fortify** (via the Vue starter kit). Sign-in issues an authenticated session and redirects to the workspace home (`/dashboard`); sign-out invalidates the session. Failed sign-ins are throttled (5/min per `email|ip`) and rejected with a single generic message that never discloses which field was wrong (no user enumeration). Auth surfaces are the only public pages — every authoring/play page sits behind the `auth` middleware and returns the user to their **intended destination** after login. Account *isolation* (owner-scoping, not RBAC) and configurable registration landed in Sprint 2 — see the *Sprint 2* subsection below.

**Project standards (S-1.1.2).** Timestamps are **stored in UTC** and **rendered in Asia/Jakarta (WIB)**; money renders in **Rupiah**. `config/app.php` exposes `display_timezone`, `display_locale`, and `currency`; `App\Http\Middleware\HandleInertiaRequests` shares them as a `standards` prop, and `resources/js/composables/useFormat.ts` (`formatDateTime` / `formatCurrency`) is the **single client-side formatter** the whole UI uses. **Exception — provider cost (Sprint 5, PH-12 resolved):** OpenRouter spend renders in **USD** via `formatUsdFromMicros`, not Rupiah, because the provider balance is held in USD, so showing USD reconciles directly against the provider dashboard and needs no FX rate.

**Persistence engine.** MariaDB 11.7 (MySQL-8-compatible, JSON columns) — default connection `mariadb`; tests run against `novel_engine_test`. Migrations are reversible (up/down verified). The two engine realms (authoring/save) and global libraries land in Sprint 3–4; today only the foundation tables exist (users, auth/2FA, passkeys, cache, jobs, sessions).

### Sprint 2 — users, ownership & app shell

Sprint 2 turns the single-tenant scaffold into a **multi-user** shell. "Multi-user" means **account isolation only** — there is no role or admin hierarchy.

- **Account isolation foundation (S-2.2.2).** Owned models opt into `App\Models\Concerns\BelongsToOwner`, which applies the `App\Models\Scopes\OwnerScope` global scope and stamps `user_id` on create. The scope constrains every query to `Auth::id()` **only while a user is authenticated** (console, seeders, and queued jobs are unaffected; web owned-resources always sit behind `auth`), so another user's row is invisible — route-model binding resolves to **404** and never leaks existence. `App\Policies\OwnerPolicy` is the abstract base that future owned policies extend; it authorizes `view`/`update`/`delete` by ownership alone (cross-owner → **403**). The first real owned model (stories) lands in Phase 2; the foundation is proven now by a fixture model + `tests/Feature/Auth/OwnershipIsolationTest.php`. Diagram: [Diagrams/App/Account_Ownership_Isolation.md](./Diagrams/App/Account_Ownership_Isolation.md).
- **Configurable registration (S-2.2.3).** `config('app.registration_enabled')` (env `REGISTRATION_ENABLED`, default `true`) gates self-registration. The Fortify `register` route stays registered so the typed route survives the build; when the toggle is off the GET view and the `CreateNewUser` POST path both `abort(404)`, and the shared `canRegister` Inertia prop hides the sign-up affordances on Welcome + Login. Existing users can always sign in.
- **Email verification removed (PH-10).** The Sprint-1 `verified` guard was a no-op (`User` never implemented `MustVerifyEmail`). Rather than carry a dormant feature, verification is dropped entirely: the Fortify feature, `verifyEmailView`, the `verified` middleware, and the verification UI/tests are gone, so **no mailer is required to sign in**. The `email_verified_at` column survives (reset on email change) should opt-in verification return later.
- **App shell & navigation (S-3.1.1).** The sidebar surfaces two primary destinations — **Workspace** (`/dashboard`) and **Settings** (active across the whole `/settings/*` area) — with an active-area indicator; the starter-kit external links are removed. The workspace home is a token-driven **empty state** that teaches the next step ("No stories yet") behind a clearly-disabled "New story · coming soon" control — navigation-reachable, no dead links. **Play** is deferred to Phase 5. Diagram: [Diagrams/App/App_Shell_Navigation.md](./Diagrams/App/App_Shell_Navigation.md).

Sign-in + route-protection flow: [Diagrams/App/Auth_Signin_Flow.md](./Diagrams/App/Auth_Signin_Flow.md). Account isolation: [Diagrams/App/Account_Ownership_Isolation.md](./Diagrams/App/Account_Ownership_Isolation.md). App shell & nav: [Diagrams/App/App_Shell_Navigation.md](./Diagrams/App/App_Shell_Navigation.md). Endpoint/props contracts: [../api/auth.md](../api/auth.md) + [../api/account.md](../api/account.md). Setup & troubleshooting: [../runbooks/local-setup-diagnostics.md](../runbooks/local-setup-diagnostics.md).

### Sprint 3 — theming, responsive shell & authoring schema

Sprint 3 finishes the UX foundation (E3) and stands up the first half of the persistence engine (E4.1) — the app now *looks* finished and has somewhere to put stories, even before authoring CRUD exists.

- **Theming polish (S-3.1.2).** Theme persistence already existed (cookie + `localStorage`, light/dark/system via `useAppearance`); the root Blade view applies the cookie's `.dark` class **before hydration** so there is no flash-of-wrong-theme. Sprint 3 added a **quick theme toggle in the shell user menu** (reusing `AppearanceTabs` in `block` mode, so theme is reachable without visiting Settings), rebuilt the public `Welcome.vue` on semantic tokens with full dark parity and 44px targets (resolves PH-13), and converted `AppearanceTabs` itself from raw palette utilities to tokens.
- **Responsive & accessible shell (S-3.1.3).** A **skip-to-content** link (first focusable element) jumps keyboard users to a focusable `#main-content` target inside the `<main>` landmark; active nav items expose `aria-current="page"`; focus rings come from the shared button tokens. The reka-ui sidebar already collapses to a mobile offcanvas with breadcrumbs.
- **Four-state components (S-3.2.1).** Reusable `EmptyState.vue` and `ErrorState.vue` (token-only, JSDoc'd, single-CTA slot) standardize the empty/error surfaces; `Dashboard.vue` now consumes `EmptyState`. Deferred props were intentionally **not** wired this sprint — no real async list exists yet (authoring CRUD is Phase 2).
- **Destructive-action confirmation (S-3.2.2).** A shadcn-vue **`alert-dialog`** primitive plus a promise-based `useConfirm()` composable (rendered once via `ConfirmDialog` in the shell) is the standard `await confirm({ … })` path — destructive styling + clear Cancel, **never** a native `confirm()`/`alert()`. Account deletion keeps its stronger password-re-entry dialog.
- **Authoring realm schema (S-4.1.1).** The 11 authoring tables are migrated with their PHP enums, Eloquent models, and factories (see DATABASE.md §3 + Build-status). **`stories` is the first owner-scoped product model** — it adopts `BelongsToOwner` + `StoryPolicy`, pulling the Phase-2 ownership work forward (resolves PH-14). Authoring **child** rows carry no `user_id`; they inherit isolation **transitively** through their story (`cascadeOnDelete` from `stories`). Three cross-realm/library FK constraints are deferred to Sprint 4 (PH-16). Migrations are reversible (a dedicated `migrate:fresh → rollback → migrate` test proves it).

### Sprint 4 — save realm, global libraries & provider key

Sprint 4 completes the persistence engine (E4.2 / E4.1.2) and adds secure provider-key management (E5.1) — the database now models both realms end-to-end and an account can store its own encrypted LLM key.

- **Global shared libraries (S-4.1.2).** Five app-wide tables — `register_archetypes`, `universal_priors`, `character_archetypes` (ADR 0018), `prompt_blocks` (ADR 0020), `model_profiles` (ADR 0017) — migrated with enums, models, `casts()`, and factories. They carry **no `story_id`** (shared), except `model_profiles.story_id` which is nullable so a story can override a global role→model default. **No seeders this sprint** — seeding is Sprint 6 (E6.1).
- **Save realm (S-4.2.1).** All 16 per-save tables migrated in FK order and scoped to a save via `session_id`. The save "session" is built as **`play_sessions`** (model `PlaySession`) because the framework reserves the `sessions` table for the database session driver — a naming-only divergence from DATABASE.md §4.1 (PH-17); child FK columns keep `session_id`. Full parity: migration + model + `casts()` + factory + any new enum for each table.
- **Isolation & audit invariants (S-4.2.2).** `beat_true_states.private_text` lives in a **separate table** from `beat_records.surface`, so a surface-only read physically cannot reach a character's private state (structural isolation, asserted by a headline test). Six append-only tables (`axis_deltas`, `beat_records` + `beat_true_states` + `beat_witnesses`, `nudges`, `llm_calls`) use a new **`App\Models\Concerns\AppendOnly`** trait that throws on `UPDATE`/`DELETE` and drops `updated_at` — the audit guarantee enforced at the data layer, not just by convention. **PH-16 resolved:** the three deferred FKs are now real constraints.
- **Provider key management (S-5.1.1 / S-5.1.3).** A new owner-scoped **`provider_credentials`** table stores one **encrypted** API key per user per provider (`encrypted` cast + `#[Hidden]`; only `last_four` is kept for a masked display). Managed from a new **Settings → Provider** screen (`ProviderController` + `<Form>` via Wayfinder, masked display, confirm-on-remove via `useConfirm`, four states, no native alerts) over a thin `ProviderCredentialService`. This is a deliberate **divergence from ADR 0017 §1** (which sketched a single `.env` key) in favour of the per-owner-encrypted NFR (PH-18); only the provider `base_url` stays in config. **Out of scope:** the `LlmClient` and connection test (Sprint 5).

### Sprint 5 — LLM client, role tiering, logging & connection test

Sprint 5 builds the LLM client on top of the Sprint-4 schema (E5.2 / E5.3 / S-5.1.2), governed by [ADR 0017](../adr/0017-llm-orchestration-openrouter.md). The engine now has the single chokepoint every future call flows through — but no caller yet (the narrator loop is Phase 2+).

- **Thin `LlmClient` over `Http` (S-5.2.1).** A provider-agnostic `App\Contracts\Llm\LlmClient` interface (`complete` / `completeStructured`) with one implementation, `App\Services\Llm\OpenRouterClient`, bound in `AppServiceProvider`. It uses the Laravel `Http` facade (full visibility into the exact bytes sent — ADR 0017 §1), authenticates with the **owner's** decrypted key from `provider_credentials` (not env, PH-18), and carries bounded **retry + backoff** on 429/5xx (config `services.openrouter.*`: `timeout`, `connect_timeout`, `max_retries`, `retry_base_delay_ms`). **`laravel/ai` was removed** — it has no per-request DB-key support ([#105](https://github.com/laravel/ai/issues/105)); it stays a future swap *behind the interface* only (PH-21).
- **Role tiering (S-5.2.2).** `App\Services\Llm\ModelRoleResolver` maps an `LlmRole` (+ optional `Story`) → `ModelProfile` (per-story override → global default; inactive profiles skipped; unmapped fails closed via `UnresolvedModelRoleException`). The **global** mapping is now UI-editable at **Settings → Model roles** (`ModelRoleController`); per-story overrides await story management (PH-19).
- **Structured output never trusted (S-5.2.3).** `completeStructured` requests a JSON-schema response and **parse-validates** it (dependency-free: required keys + property types). A malformed/non-conforming payload is retried to the bound, then recorded as a `Failed` `llm_calls` row and surfaced as `LlmStructuredOutputException` — the parsed data is never returned. Headline test: `tests/Feature/Llm/StructuredOutputRetryTest.php`.
- **Owner-scoped call log (S-5.3).** `App\Services\Llm\LlmCallLogger` writes the append-only `llm_calls` row (role, model, tokens, USD-micro cost, latency, status, session/story/review links). `llm_calls` gains a nullable **`user_id`** + `BelongsToOwner` so the log is owner-scoped (PH-20); message bodies are persisted only behind the `services.openrouter.log_messages` debug gate (S-5.3.2) and stay `#[Hidden]`. The **Settings → Usage** screen (`UsageController`) renders the owner's calls via an `Inertia::defer()` paginated prop (skeleton fallback), with cost in **USD** and time in **WIB**.
- **Connection test (S-5.1.2).** `App\Services\Llm\ConnectionTester` probes OpenRouter `/models` with the owner's key and returns reachable models or the provider's failure reason. Surfaced on the Provider page through Inertia v3 `useHttp` (inline result, no native alert). It is **key validation, not a role call** — it never writes `llm_calls`. Throttled (6/min).

Diagram: [Diagrams/Engine/Llm_Client_Flow.md](./Diagrams/Engine/Llm_Client_Flow.md). Endpoint/props contracts: [../api/provider.md](../api/provider.md) (connection test) + [../api/model-roles.md](../api/model-roles.md) + [../api/usage.md](../api/usage.md). Setup & config: [../runbooks/local-setup-diagnostics.md](../runbooks/local-setup-diagnostics.md) §11.

### Sprint 7 — Story CRUD & workspace list (Phase 2 kickoff)

Sprint 7 is the first Phase 2 sprint: the workspace becomes a live authoring surface. Stories are the first owned model with an **HTTP + UI surface** — everything before this was schema/data-layer only.

- **Per-owner slug uniqueness.** The global `stories_slug_unique` constraint is relaxed to a composite `(user_id, slug)` unique index. Two different owners can now hold the same slug; a single owner still cannot. Slug is derived from title via `Str::slug()` when omitted, auto-suffixed (`-2`, `-3`, …) on collision.
- **Story CRUD (S-1.1.1 / S-1.1.2).** `StoryController` (index/store/edit/update/destroy) with route-model binding by `{story:slug}` under the owner scope — foreign stories resolve to 404. Business logic is in `StoryService` (atomic via `DB::transaction`; slug derivation + uniqueness centralised). Validation via `StoreStoryRequest` / `UpdateStoryRequest` with per-owner slug uniqueness rules.
- **Workspace dashboard (S-1.1.2).** `/dashboard` now renders via `StoryController@index`, passing the owner's stories as an Inertia prop. Empty state teaches the next step; populated state is a card grid. Story creation opens a **Dialog** (desktop) from the dashboard — no page navigation for the fast create path. Edit is a dedicated `/stories/{slug}/edit` page (room for per-story settings/overview tabs in S-1.2.x).
- **Delete with confirmation (S-1.1.2).** `useConfirm()` dialog, destructive styling, toast on success. Cascade to authoring children handled by FK `cascadeOnDelete`. Never `window.confirm()`.
- **Sidebar nav.** Workspace `isActive` now also matches `/stories/*` paths.

Routes: `stories.store` / `stories.edit` / `stories.update` / `stories.destroy`. Endpoint/props contracts: [../api/stories.md](../api/stories.md). Diagram: [Diagrams/Authoring/Story_Crud_Flow.md](./Diagrams/Authoring/Story_Crud_Flow.md).

### Sprint 8 — Per-story settings & overview (E1.2)

E1.2 turns the single edit page into a **per-story workspace** with an **Overview · Details · Settings** sub-nav. The workspace shell (`resources/js/layouts/stories/Layout.vue`, wired via `name.startsWith('stories/')` in `app.ts`) reads a shared `story` prop and renders the tab bar; **Details** is the existing edit form.

- **Settings (S-1.2.1).** `StorySettingsController@edit/@update` + `StorySettingsService`. The **default POV** is stored in `stories.settings.default_pov` (new `App\Enums\PovMode`, default `third_limited`); per-role **model overrides** are stored as `model_profiles` rows scope=`Story`. A save runs atomically (`DB::transaction`): write the POV, then per role `updateOrCreate` the story profile when its override is on, else **delete** it so the role falls back to the global default. `ModelRoleResolver` already prefers the story row (per-story override → global default), so no resolver change was needed. Validation: `UpdateStorySettingsRequest` (`default_pov` enum + `roles[]` with `required_if` override). **Resolves PH-19** (per-story overrides now have a UI) and **PH-28** (the workspace now has a Settings tab).
- **Overview (S-1.2.2).** `StoryOverviewController@show` + `StoryOverviewService`. Seven **derived counts** (characters / chapters / scenes / beats / lorebook / reveal-ledger / saves — the last via the new `Story::playSessions()` relation) and a **play-readiness gate** recomputed on every read: ≥ 1 character, a chapter with a scene and a beat, and a resolvable model for every `LlmRole`. The gate enumerates what's missing and is built to be reused by the full readiness checklist UI (E2.1 / S-2.1.2). Nothing here is stored.
- **Entry point.** The dashboard story card title now opens the **Overview** (`stories.show`); edit/delete actions are unchanged.

Per-story **rubric/tunable** overrides stay deferred to E5.1 (**PH-29**) — they need a global rubric config home first (PH-8). Routes: `stories.show` / `stories.settings.edit` / `stories.settings.update`. Endpoint/props contracts: [../api/story-overview.md](../api/story-overview.md) · [../api/story-settings.md](../api/story-settings.md). Diagram: [Diagrams/Authoring/Story_Settings_Overview_Flow.md](./Diagrams/Authoring/Story_Settings_Overview_Flow.md).

### Sprint 7 — Authoring workspace shell (E2.1)

E2.1 grows the per-story workspace from the three live tabs (Overview · Details · Settings) into the **full authoring surface set**: **Overview · Characters · Structure · Lorebook · Settings · Saves · Details**. The shell is unchanged in mechanism — `resources/js/layouts/stories/Layout.vue` still reads the shared `story` prop and renders the tab bar — but the tab list now spans every surface S-2.1.1 names, with a Lucide icon per tab for scannability.

- **Placeholder surfaces (S-2.1.1).** Characters, Structure, Lorebook, and Saves have no feature yet (they land in Phases 3–5 / E3.1). Rather than leave dead nav items, each is a reachable **`stories/ComingSoon`** page rendered by `StoryPlaceholderController` (one method per surface, each `Gate::authorize('view', $story)` then renders the shared page with a `{ key, title, description, phase }` descriptor). The page is a teaching empty state — surface icon, what it will do, a "Coming in <phase>" badge, and a "Back to overview" link. Owner-scoped like every other surface: `{story:slug}` binds under `OwnerScope`, so a foreign story 404s. Tracked as **PH-30**; each route is repointed at its real controller when the feature ships.
- **Play-readiness (S-2.1.2).** Already delivered in E1.2 as a derived gate (`StoryOverviewService::readiness()`), surfaced on the **Overview** tab — the default workspace surface, reachable by opening a story. Requirements (≥ 1 character; a chapter with a scene and a beat; a resolvable model for every `LlmRole`) are recomputed on read and enumerated when unmet. No new surface; E2.1 only adds an edge-case test (a scene with no beat is not play-ready).
- **Scope is per-story.** Every surface resolves through `{story:slug}`, so switching stories re-scopes the whole shell; nothing leaks across stories.

Routes: `stories.characters.index` / `stories.structure.index` / `stories.lorebook.index` / `stories.saves.index`. Endpoint/props contracts: [../api/stories.md](../api/stories.md). Diagram: [Diagrams/Authoring/Story_Workspace_Shell.md](./Diagrams/Authoring/Story_Workspace_Shell.md).

### Sprint 9 — Lorebook CRUD (E3.1)

E3.1 turns the **Lorebook** placeholder into a live authoring surface — per-story world facts the runtime injects on keyword match (ADR 0013 §5). It is the first **nested, scope-bound** owned resource.

- **Lorebook CRUD (S-3.1.1).** `LorebookController` (index/store/update/destroy) over a thin `LorebookService` (atomic via `DB::transaction`; keyword normalisation — trim, drop empties, de-dupe — centralised). The parent `{story:slug}` binds under `OwnerScope` (foreign story → 404); the child `{lorebookEntry}` uses **`->scopeBindings()`** so an entry from another story → 404 without an entry-level policy — authorization stays on the parent `Story` (`view` to read, `update` to write). Validation via `StoreLorebookEntryRequest` / `UpdateLorebookEntryRequest`: ≥ 1 keyword, required content, and an optional `min_reveal_chapter_id` constrained to a chapter **of this story**. Writes are throttled (30/min).
- **Workspace surface.** `resources/js/pages/stories/Lorebook.vue` (inside the per-story workspace layout) lists entries as cards (keyword `Badge`s, content preview, reveal-chapter badge) with a single primary **New entry** action; create/edit share one responsive `LorebookEntryDialog` driven by `useForm` (the keyword chip input is the shadcn-vue **`tags-input`** over reka-ui); delete goes through `useConfirm`. Reveal-chapter selection degrades to a disabled hint until chapters exist (Phase 4). **Resolves the lorebook half of PH-30.**
- **Deferred.** Runtime keyword injection (narrator + knowledge-bounded NPC context) stays out of scope (PH-31); world-fact discipline (S-3.1.2) and the keyword-match preview (S-3.2.1) land in Sprint 10 below.

Routes: `stories.lorebook.index` / `stories.lorebook.store` / `stories.lorebook.update` / `stories.lorebook.destroy`. Endpoint/props contracts: [../api/lorebook.md](../api/lorebook.md). Diagram: [Diagrams/Authoring/Lorebook_Crud_Flow.md](./Diagrams/Authoring/Lorebook_Crud_Flow.md).

### Sprint 10 — Lorebook discipline + preview (E3.1)

Two authoring-quality stories on top of the CRUD foundation, both governed by ADR 0013 §5 (lorebook = **world facts only**; preview must match runtime).

- **World-fact discipline (S-3.1.2).** `InteriorityHeuristic` is a deterministic, offline linter (no LLM) whose curated regex signals (feeling / intent / concealment / private-state) flag a character's interiority while ignoring world facts that merely contain an emotive word ("the gloves *feel* cold"). The store/update requests run it in an `after()` hook via a shared `GuardsWorldFactDiscipline` trait as a **soft gate**: flagged content fails with a synthetic `interiority` error (naming the phrases, steering to the character cards) unless the author sets the transient `acknowledge_interiority`. The dialog renders that error as a distinct **warning** panel (new `warning` token + `Alert` variant) with a "Save as world fact anyway" override and a link to the character cards. The signal list is an authored, tunable default (**PH-33**); **resolves PH-32**.
- **Keyword match preview (S-3.2.1).** `LorebookKeywordMatcher` is the **canonical** match (case-insensitive substring, multi-word phrases) — the same implementation runtime injection will reuse (PH-31). `LorebookController@preview` (`PreviewLorebookRequest`, `Gate::authorize('view')`) returns JSON `{ triggered, withheld }`, applying the `min_reveal_chapter` clamp when a chapter is previewed. The surface is `LorebookPreviewDialog.vue`, opened by a secondary **Test keywords** action and transported with **`useHttp`** (a standalone JSON request, no page visit) so the arbitrary-length sample rides in the POST body; it renders loading / empty / triggered / reveal-withheld states. Because the app renders web redirects for non-`api/*` validation errors (`bootstrap/app.php` `shouldRenderJsonWhen`), the request forces a JSON `422` via `failedValidation()` for the `useHttp` client.

Routes add `stories.lorebook.preview` (POST, `throttle:30,1`). Endpoint/props contracts: [../api/lorebook.md](../api/lorebook.md). Diagram: [Diagrams/Authoring/Lorebook_Crud_Flow.md](./Diagrams/Authoring/Lorebook_Crud_Flow.md).

### Sprint 10 — Reveal ledger CRUD (E4.1)

E4.1 adds the **Reveal ledger** workspace tab — a per-story list of load-bearing secrets `{ fact, reveal_chapter, character?, who_knows }` that makes spoiler-safety **explicit rather than inferred** (ADR 0013 §3). It is the second nested, scope-bound owned resource, built as a close sibling of the lorebook.

- **Reveal-ledger CRUD (S-4.1.1).** `RevealLedgerController` (index/store/update/destroy) over a thin `RevealLedgerService` (atomic via `DB::transaction`; `who_knows` slug normalisation — trim, drop empties, de-dupe — centralised). The parent `{story:slug}` binds under `OwnerScope` (foreign story → 404); the child `{revealLedgerEntry}` uses **`->scopeBindings()`** through `Story::revealLedgerEntries()` so an entry from another story → 404 without an entry-level policy — authorization stays on the parent `Story` (`view` to read, `update` to write). Validation via `StoreRevealLedgerEntryRequest` / `UpdateRevealLedgerEntryRequest`: required `fact`; a **required** `reveal_chapter_id` constrained to a chapter **of this story**; an optional `character_id` (null = world secret) likewise in-story; a `who_knows` slug list; optional `notes`. Writes are throttled (30/min).
- **Workspace surface.** `resources/js/pages/stories/RevealLedger.vue` (inside the per-story workspace layout) lists entries as cards (fact in mono, a world-secret/character badge, who-knows slug chips, a reveal-chapter `Lock` badge) with a single primary **New entry** action; create/edit share one responsive `RevealLedgerEntryDialog` driven by `useForm` (the who-knows chip input is the shadcn-vue **`tags-input`**); delete goes through `useConfirm`. Because the reveal point is **required** and chapters land in Phase 4, creation is **gated behind a teaching empty state** ("Add a chapter first" → Structure) rather than degrading a single field; the optional "about" character selector degrades to a world-secret hint when no characters exist.
- **Deferred.** The compile clamp that consumes the ledger (exclude a fact from a card before its reveal chapter → explicit `does_not_know` on `knowledge_boundary`) lands with the character-card pipeline (Phase 3); the reveal-clamp **preview** (S-4.1.2) lands in Sprint 11. `who_knows` slugs are stored free-text (not existence-checked) because characters are authored later. Tracked as **PH-34**.

Routes: `stories.reveal-ledger.index` / `stories.reveal-ledger.store` / `stories.reveal-ledger.update` / `stories.reveal-ledger.destroy`. Endpoint/props contracts: [../api/reveal-ledger.md](../api/reveal-ledger.md). Diagram: [Diagrams/Authoring/Reveal_Ledger_Crud_Flow.md](./Diagrams/Authoring/Reveal_Ledger_Crud_Flow.md).

### Sprint 11 — Minimal manual character (E1.1)

E1.1 turns the **Characters** placeholder into a live authoring surface — the first **minimal manual** creation slice (S-1.1.1 / S-1.1.2, ADR 0018 §2): a hand-authored cast with **no LLM call and no API key**. It is the third nested, scope-bound owned resource, built as a close sibling of the lorebook / reveal ledger.

- **Character CRUD (S-1.1.1).** `CharacterController` (index/store/update/destroy) over a thin `CharacterService` (atomic via `DB::transaction`; per-`(story_id, slug)` unique slug derivation; `knowledge_boundary` list normalisation centralised). The parent `{story:slug}` binds under `OwnerScope` (foreign story → 404); the child `{character}` uses **`->scopeBindings()`** through `Story::characters()` so a character from another story → 404 without a character-level policy — authorization stays on the parent `Story` (`view` to read, `update` to write). Validation via `StoreCharacterRequest` / `UpdateCharacterRequest` (shared `GuardsCharacterFields` trait): always-required `name` / `appearance` / `base_opacity` (0–100). Writes are throttled (30/min).
- **Chapter-1 anchor (the scrum fix).** A character's minimal fields (`appearance`, `folded_identity`, `knowledge_boundary`) live only on the per-`(character, chapter)` `character_card`, whose `chapter_id` is `NOT NULL` — so a character cannot exist without a chapter (characters are tied to chapters — Novel-Crafter model). `CharacterService` `firstOrCreate`s a default **Chapter 1** (`pov_default` = the story's resolved default POV) on the first commit and writes the chapter-1 card under it; later characters reuse it. E1.2 (Structure) refines that same chapter rather than re-creating it. The E1.1 scrum *Technical Notes* preconditions were corrected to list the `chapters` table + this anchor.
- **Player vs NPC (S-1.1.2).** Exactly one **player** per story (enforced in the request `after()` hook, excluding the bound character on update). A player carries appearance + `base_opacity` only — no simulated interiority (`folded_identity = ''`, empty `knowledge_boundary`, `model_tier = Minor`, `live_axes = []`). An **NPC** requires a `folded_identity` and a **mandatory** `knowledge_boundary` (≥ 1 entry across `knows` / `does_not_know`) — captured now because Phase 2's NPC `IDENTITY`/`SCENE_EXCERPT` blocks and Phase 4's `NUDGE` leak-check depend on it (`model_tier = Major`).
- **Workspace surface.** `resources/js/pages/stories/Characters.vue` (inside the per-story workspace layout) lists the cast as cards (player `Badge`, appearance + folded-identity previews, knowledge-boundary size, opacity badge) with a single primary **New character** action; create/edit share one responsive `CharacterDialog` driven by `useForm`. The `is_player` toggle is a new shadcn-vue **`switch`** (booleans → switch, per the UI/UX rule) that reveals/hides the NPC-only fields; the knowledge-boundary inputs are **`tags-input`** chip lists; delete goes through `useConfirm`. **Resolves the characters portion of PH-30.**
- **Deferred.** Edges / registers / sensitivities (`live_axes` content) are not authored here (Phase 5); the AI / hybrid creation pipeline + bible→card compile (ADR 0018 §2–3) lands in Phase 5; the runtime consumers of `knowledge_boundary` are separate and later.

Routes: `stories.characters.index` / `stories.characters.store` / `stories.characters.update` / `stories.characters.destroy`. Endpoint/props contracts: [../api/characters.md](../api/characters.md). Diagram: [Diagrams/Authoring/Character_Crud_Flow.md](./Diagrams/Authoring/Character_Crud_Flow.md).
