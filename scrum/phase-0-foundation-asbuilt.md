# Phase 0: Foundation & Authoring Shell — AS-BUILT BASELINE
## Directed Interactive Novel Engine (DINE)

**Status:** ✅ **DONE / already built.** This document is **not a build backlog** — it is the accurate **as-built baseline** a fresh, stateless agent reads first so it builds Phases 1–6 against reality (existing tables, services, routes, and pages) instead of re-creating them.
**Covers (old v1):** all of old Phase 1 (Foundation, Auth & App Shell) + old Phase 2 Epics E1–E4 (Story Management, Authoring Workspace, Lorebook, Reveal Ledger).
**Governing ADRs:** 0011 (tech stack), 0012 (persistence), 0017 (LLM/OpenRouter), 0020 (prompt blocks), 0013 (lorebook/reveal ledger).

> **How to use this doc.** Before implementing any Phase 1–6 story, confirm its `Preconditions` against the inventory below. If a precondition table/service/route is listed here, it exists — wire into it, do not rebuild it. If you believe something here is missing or wrong, verify in the codebase before assuming.

---

## 1. Stack & standards (built)

- **Backend:** Laravel 13, PHP 8.4. Logic lives in `app/Services/` (pragmatic Service pattern; `app/Actions/` holds only Fortify actions).
- **Frontend:** Vue 3 + Inertia v3, **Wayfinder** typed routes (import from `@/actions` / `@/routes`; Ziggy `route()` is forbidden), Tailwind 4, shadcn-vue. pnpm + Vite.
- **DB:** MariaDB (MySQL-8-compatible, JSON columns).
- **Standards wired:** timestamps stored UTC, rendered Asia/Jakarta (WIB); provider cost rendered in Rupiah (Rp); `pnpm lint` is the lint gate.

---

## 2. Auth & account (built)

- **Fortify** auth: sign in / out, route protection, login throttle, configurable self-registration, account profile + password, optional **passkeys**, two-factor. Pages under `resources/js/pages/auth/`, `resources/js/pages/settings/{Profile,Security,Appearance}.vue`.
- **Account isolation** is structural: `app/Models/Concerns/BelongsToOwner.php` + `app/Models/Scopes/OwnerScope.php`; every owned row is owner-scoped; cross-owner access fails closed. Negative test: `tests/Feature/Auth/OwnershipIsolationTest.php`.
- **Append-only** invariant helper: `app/Models/Concerns/AppendOnly.php` (audit tables carry only `created_at`; no UPDATE/DELETE). Test: `tests/Feature/Database/AppendOnlyInvariantTest.php`.

---

## 3. Persistence — BOTH realms migrated (built)

> Critical for v2: the **save realm is already fully migrated**, so Phases 1–5 mostly **wire behavior onto existing tables** rather than writing migrations. Several of these tables (edges, deltas, scars, internal state) have **no behavior yet** — their producers arrive in Phases 4–5. That is expected; do not treat an empty-but-migrated table as missing.

**Authoring realm** (immutable at runtime) — migrations `100001`–`100011`:
`stories`, `chapters`, `characters`, `scenes`, `beats`, `character_cards` (per character × chapter), `reveal_ledger`, `lorebook_entries`, `registers`, `sensitivities`, `chapter_outlines`. Models: `Story, Chapter, Scene, Beat, Character, CharacterCard, RevealLedger, LorebookEntry, Register, Sensitivity, ChapterOutline`.

**Global libraries** (story-independent) — migrations `100012`–`100016`:
`register_archetypes`, `universal_priors`, `character_archetypes`, `prompt_blocks`, `model_profiles`. Models: `RegisterArchetype, UniversalPrior, CharacterArchetype, PromptBlock, ModelProfile`.

**Save realm** (mutable, session-scoped) — migrations `100020`–`100035`:
`play_sessions`, `review_items`, `relationship_edges`, `edge_axes`, `axis_deltas`, `internal_states`, `active_emotions`, `acquired_sensitivities`, `beat_records`, `beat_true_states`, `beat_witnesses`, `nudges`, `scene_summaries`, `chapter_logs`, `events`, `llm_calls`. Models match.

**Cross-cutting / amendments** — `100040` deferred authoring FKs; `100050` `provider_credentials`; `100060` user_id on `llm_calls`; `100070` user_id on `review_items` (null-session proposals); `100080` per-owner story-slug uniqueness.

**Structural isolation invariant (built):** `beat_true_states` is a **separate child table** of `beat_records`, so a "read `surface` only" query physically cannot return another character's private `true_state`. Tests: `tests/Feature/Database/SaveRealmSchemaTest.php`, `SaveRealmMigrationTest.php`, `AuthoringRealmSchemaTest.php`.

---

## 4. LLM client & cost log (built)

- `app/Services/Llm/OpenRouterClient.php` — provider-agnostic `LlmClient` (text + structured), callers pass a **role** not a slug. DTOs in `Llm/Data/`.
- `Llm/ModelRoleResolver.php` — role → model slug + params; **per-story override → global default**.
- `Llm/ConnectionTester.php` — provider connection test (pass/fail + reachable models).
- `Llm/LlmCallLogger.php` + `LlmCall` model — append-only `llm_calls`: role, model, tokens, provider cost, latency, status; cost stored provider-reported, rendered Rupiah; **full message bodies debug-gated, never agent-readable**.
- Structured output is parse-validated, retried with bounded backoff, then surfaced as failed — never trusted. Test: `tests/Feature/Llm/StructuredOutputRetryTest.php`.
- `ProviderCredential` (encrypted at rest, masked after save, never logged) + `Settings/ProviderController.php`. Settings pages: `engine/Provider.vue`, `engine/ModelRoles.vue`, `engine/Usage.vue`.

**Engine roles** the resolver knows (default model profiles seeded): `narrator_prose`, `recorder`, `npc_major`, `npc_minor`, `compiler`, `appraiser`, `beat_judge`, `nudge_compiler`. Routes: `provider.*`, `model-roles.*`, `usage.index`.

---

## 5. Seeded global libraries (built)

Idempotent seeders populated:

- **Universal priors** — baseline human reactions (insult, kindness, threat, broken promise…) with affected axes, default weight, channel. (ADR 0005)
- **Register archetypes** — reusable grammar skeletons over the fixed canonical dimension set (one-way-mirror, romantic-deflection, unguarded, wary…). (ADR 0006)
- **Character archetypes** — whole-character seed shapes (e.g. koakuma): base_opacity, suggested live axes, default disposition priors, default registers, default sensitivities, voice scaffold. (ADR 0018)
- **`prompt_blocks` — the ~15 engine blocks** for narrator and NPC, each with `key, agent, section, label, purpose, source_producers, compile_instruction, leak_rules, order_index, is_active`. **This is the assembler contract Phases 1–5 consume.** (ADR 0020). Test: `tests/Feature/Database/GlobalLibrariesSeedTest.php`.
- **Model profiles** — a default slug + params for every engine role. (ADR 0017)

### The seeded prompt_blocks (the final-prompt contract)

| key | agent | section | leak_rules | lit in phase |
|-----|-------|---------|-----------|--------------|
| `POV_CONTRACT` | narrator | system | `none` | 1 |
| `BEAT` | narrator | system | `omniscient_authoring` | 1 (minimal) / 4 (full) |
| `SCENE_STATE` | narrator | system | `none` | 1 |
| `LOREBOOK` (narrator) | narrator | system | `none` | 1 |
| `RESUME_ANCHOR` | narrator | user | `none` | 1 |
| `MESH_AWARENESS` | narrator | system | `hedged_attribution` | 4 |
| `DIRECTOR_STATE` | narrator | system | `none` | 4 |
| `IDENTITY` | npc | system | `knowledge_boundary` | 2 |
| `SCENE_RULES` | npc | system | `none` | 2 |
| `SCENE_EXCERPT` | npc | user | `hedged_attribution` + `knowledge_boundary` | 2 |
| `LOREBOOK` (npc) | npc | system | `knowledge_boundary` | 2 |
| `NUDGE` | npc | system | `omniscient_authoring` + `knowledge_boundary` | 4 |
| `SELF` | npc | system | `none` (own private truth) | 5 |
| `SNAPSHOT` | npc | system | `awareness_fold` + `own_perspective_only` | 5 |
| `MASKS` | npc | system | `own_perspective_only` | 5 |
| `DIRECTIVES` | npc | system | `none` | 5 |

> A block whose producer is not yet built is simply **absent from the assembled prompt** (no placeholder filler). Lighting up a block = building its producer + wiring it into the assembler + turning on its `leak_rule`. **No new guard is ever invented** — `leak_rules` name only the set above.

---

## 6. Story & world authoring surfaces (built)

- **Story CRUD + dashboard** (`StoryController`, `dashboard`, `stories.*`), per-story **settings** (default POV, model-role overrides) (`StorySettingsController`), **overview** with derived counts + play-readiness gate (`StoryOverviewController`). Services: `StoryService`, `StorySettingsService`, `StoryOverviewService`.
- **Per-story authoring workspace** with nav (characters / structure / lorebook / settings / saves), all story-scoped. `characters`, `structure`, `saves` currently render a **`ComingSoon` placeholder** (`StoryPlaceholderController`) — these are the surfaces Phases 1–5 fill.
- **Lorebook** CRUD + keyword-match preview (`LorebookController`, `LorebookService`, `LorebookKeywordMatcher`, `InteriorityHeuristic` for world-fact discipline). Routes `stories.lorebook.*`.
- **Reveal ledger** CRUD (`RevealLedgerController`, `RevealLedgerService`) — load-bearing secrets `{ fact, reveal_chapter, who_knows }`. Routes `stories.reveal-ledger.*`. **Consumer not built yet** — the per-chapter spoiler clamp that uses it lands in Phase 5.

---

## 7. ⚠️ Known orphans / to repurpose (the v1 mis-ordering residue)

These exist because the old subsystem-first order specced them before their host existed. **Phases 1–6 must repurpose, not duplicate, them.**

- **Standalone `/reviews` page is an empty stub.** `ReviewController`, `resources/js/pages/reviews/Index.vue`, `ReviewItem` model + `review_items` table, and `app/Services/ReviewGateService.php` (with `propose()` / accept / edit / reject + `pending → accepted|edited|rejected` state machine) all exist — but **no producer calls `propose()`**, so the page renders an empty teaching state.
  - **Phase 2** wires the **first real producer** (the recorder's `beat_record`) into `ReviewGateService` and surfaces its decision **inline in play** — not on the standalone page.
  - **Phase 6** repurposes the standalone page into the **unified** review-gate surface across all producer types.
- **Save-realm psychology tables are migrated but behaviorless** (`relationship_edges`, `edge_axes`, `axis_deltas`, `internal_states`, `active_emotions`, `acquired_sensitivities`, `nudges`). Their producers arrive in Phases 4–5. Reuse the existing tables; do not re-migrate.

---

## 8. Test coverage already present

`tests/Feature/`: `Auth/*` (incl. `OwnershipIsolationTest`), `Settings/*` (provider, model-role, usage), `Stories/*` (CRUD, workspace, overview, settings, lorebook crud/preview/discipline, reveal-ledger crud), `Reviews/ReviewGateTest`, `Llm/*` (client, structured-output retry, role resolver), `Database/*` (both realms, global libraries seed, deferred FKs, append-only). `tests/Unit/Services/*` (`LorebookKeywordMatcherTest`, `InteriorityHeuristicTest`).

---

## 9. What Phase 0 deliberately does NOT include (the gap Phase 1 opens)

There is **no runtime yet**: no session fork executes, no state machine runs, no narrator/NPC prompt is ever assembled or sent, and no prose is generated. `PlaySession` is a table/model only. **Phase 1 builds the first thing a human can actually *play*.**

---

*Document Version: 2.0 (as-built baseline) · Author: Zulfikar Hidayatullah · Created: June 2026*
