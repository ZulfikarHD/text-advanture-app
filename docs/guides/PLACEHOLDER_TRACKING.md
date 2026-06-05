# Placeholder Tracking

Open placeholders and design divergences. Each entry: what, why it's a placeholder, where it resolves.

> Update this whenever you leave a "to be decided / temporary" mark in docs or code.

## Active placeholders

| ID | Placeholder | Why | Resolves in |
|----|-------------|-----|-------------|
| PH-6 | Source bibles live at repo root (`luna-archi.md`); the standard home is now `content/bibles/` ([ADR 0018](../adr/0018-character-creation-pipeline.md)) but `luna-archi.md` is not yet moved | Authoring source; home now decided, migration pending | migrate `luna-archi.md` → `content/bibles/` at the build round |
| PH-7 | `testing/` and `reviews/` are still empty skeletons | Build started in Sprint 1: `api/auth.md`, `runbooks/local-setup-diagnostics.md`, and `manual-qa-check/ui/S-1-foundation-auth.md` are now populated; `testing/` (test plans) and `reviews/` (UX review notes) remain empty | as features land — test plans per story, UX reviews when a structured review is done |
| PH-8 | Severity rubric + drift/elapsed **tunables** have no config home | `universal_priors` exists (DATABASE.md §3.8) and **`model_profiles`** now homes the LLM tier→slug config ([ADR 0017](../adr/0017-llm-orchestration-openrouter.md)); the rubric values + drift/elapsed tunables (0005/0014/0015) still need a home | config ADR / seeders (see [GAPS](../adr/GAPS.md) audit) |
| PH-9 | Manual-mode cards may have no source bible (`bible_path` null) | [ADR 0018](../adr/0018-character-creation-pipeline.md) allows a hand-authored card as its own source — a deliberate divergence from [ADR 0001](../adr/0001-character-data-three-layer-separation.md)'s "bible is the single source of truth" (`knowledge_boundary` still mandatory) | accepted; revisit only if manual cards prove hard to maintain |
| PH-11 | `resources/js/composables/useFormat.ts` (WIB date + Rupiah) has no UI consumer or end-to-end test yet | Foundation utility built ahead of its first data surface; no JS test runner configured | first consumer is provider-cost rendering (Phase 1 E5.3) — add a Vitest unit test then |
| PH-12 | Provider cost will be stored in the provider-reported value (USD micro-units) but must render in Rupiah — no USD→IDR FX rate/source is decided | Cost data does not exist until the LLM call log lands; `formatRupiah` formats IDR amounts but no conversion source exists | Phase 1 E5.3 (call log / cost rendering) — decide FX source (config rate vs lookup) |
| PH-15 | Primary navigation surfaces only **Workspace** + **Settings**; there is no **Play** entry | The Play reading surface does not exist yet, and the standing rule forbids dead/un-reachable nav items | Phase 5 (narrator loop / session) — add **Play** to the shell when the reader surface ships |
| PH-17 | DATABASE.md §4.1 names the save root **`sessions`**, but it is built as **`play_sessions`** (model `PlaySession`); child FK columns keep the spec name `session_id` | Laravel reserves the `sessions` table for the database session driver, so the save-realm root would collide. Renaming the table avoids the clash while keeping the documented FK column name | Accepted naming divergence; revisit only if the framework session driver is dropped (then the table could reclaim `sessions`). DATABASE.md §4.1 + Persistence_Erd.md carry the note |
| PH-18 | The LLM key is stored as a **per-owner encrypted DB row** (`provider_credentials`, DATABASE.md §7), not the single **`.env`** key sketched in [ADR 0017](../adr/0017-llm-orchestration-openrouter.md) §1. Only the gateway `base_url` stays in config | The program NFR ("API keys encrypted at rest, scoped to owner") and the `BelongsToOwner` docblock (which already names "API keys") supersede the ADR sketch; a multi-account app cannot share one env key | Accepted divergence; fold into [ADR 0017](../adr/0017-llm-orchestration-openrouter.md) when it moves from `Proposed` to `Accepted` (note the `provider_credentials` store + drop the `.env`-key line) |

## Resolved

| ID | Placeholder | Resolved by |
|----|-------------|-------------|
| PH-1 | `DATABASE.md` was DRAFT, not an ADR | [ADR 0012](../adr/0012-persistence-schema.md) (2026-06-04); DATABASE.md is now its living snapshot |
| PH-2 | Tech stack had no ADR | [ADR 0011](../adr/0011-tech-stack.md) (2026-06-04) |
| PH-3 | DB driver MySQL vs MariaDB unpinned | [ADR 0011](../adr/0011-tech-stack.md): MariaDB 11.7 (dev) / MySQL-8-compatible schema |
| PH-4 | `beats` / `scenes` / `chapters` tables were skeletal | [ADR 0015](../adr/0015-beat-document-and-boundaries.md) + [DATABASE.md](../architecture/DATABASE.md) §3.10–3.12 |
| PH-5 | `internal_states` / `active_emotions` columns were skeletal | [ADR 0014](../adr/0014-internal-state-schema.md) + [DATABASE.md](../architecture/DATABASE.md) §4.5–4.6 |
| PH-10 | Email verification was enabled but a no-op (`User` never implemented `MustVerifyEmail`); `verified` guarded `dashboard` without effect | Sprint 2 (S-2.2.3): verification **removed** entirely — Fortify feature + `verifyEmailView` + `verified` middleware + UI/tests dropped; no mailer needed to sign in. `email_verified_at` column retained for a future opt-in |
| PH-13 | Starter-kit landing `Welcome.vue` used hardcoded hex colors and sub-44px controls | Sprint 3 (S-3.1.2): `Welcome.vue` rebuilt with semantic tokens only, full dark parity, `h-11` targets, and a single primary CTA (Hick's Law) |
| PH-14 | Account-isolation foundation had no production owned model (only the `OwnedFixture`) | Sprint 3 (S-4.1.1): **stories** adopt `BelongsToOwner` + a concrete `StoryPolicy` and are now the canonical owned model. The `OwnedFixture` is retained only to test the abstract `OwnerPolicy`/`OwnerScope` in isolation |
| PH-16 | Three authoring columns carried a nullable FK **value** but **no FK constraint**: `character_cards.review_item_id` + `chapter_outlines.review_item_id` (→ save-realm `review_items`) and `registers.archetype_id` (→ global `register_archetypes`) | Sprint 4: a single ALTER migration declares all three constraints once `review_items` (S-4.2.1) and `register_archetypes` (S-4.1.2) exist; enforced by `tests/Feature/Database/DeferredForeignKeysTest.php`. Authoring children still carry no `user_id` (transitive isolation through `Story`) — by design, not a placeholder |
