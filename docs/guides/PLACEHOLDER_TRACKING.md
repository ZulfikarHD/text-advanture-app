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
| PH-10 | Email verification is enabled (`Features::emailVerification()`) and `verified` middleware guards `dashboard`, but `User` does not implement `MustVerifyEmail` so `verified` is a no-op | Sprint 1 has no verification story and enabling it adds friction to the sign-in→dashboard happy path; factory users are verified so behavior is correct now (see [OWASP F-01](../security_logs/OWASP-audit-2026-06-05-s1-foundation-auth.md), [BL F-5](../business_logic_logs/BL-audit-2026-06-05-s1-foundation-auth.md)) | Sprint 2 (E2.2 account & registration): either implement `MustVerifyEmail` or remove the feature + `verified` guard |
| PH-11 | `resources/js/composables/useFormat.ts` (WIB date + Rupiah) has no UI consumer or end-to-end test yet | Foundation utility built ahead of its first data surface; no JS test runner configured | first consumer is provider-cost rendering (Phase 1 E5.3) — add a Vitest unit test then |
| PH-12 | Provider cost will be stored in the provider-reported value (USD micro-units) but must render in Rupiah — no USD→IDR FX rate/source is decided | Cost data does not exist until the LLM call log lands; `formatRupiah` formats IDR amounts but no conversion source exists | Phase 1 E5.3 (call log / cost rendering) — decide FX source (config rate vs lookup) |
| PH-13 | Starter-kit landing `resources/js/pages/Welcome.vue` uses hardcoded hex colors and default control sizes (< 44px), diverging from the design-token system and Fitts's-Law targets in `ui-ux-standards` | Public marketing page inherited from the starter kit; redesign is theming/responsive work, not Sprint 1 auth scope | Sprint 3 (E3.1 theming + E3.3 responsive/accessible shell) |

## Resolved

| ID | Placeholder | Resolved by |
|----|-------------|-------------|
| PH-1 | `DATABASE.md` was DRAFT, not an ADR | [ADR 0012](../adr/0012-persistence-schema.md) (2026-06-04); DATABASE.md is now its living snapshot |
| PH-2 | Tech stack had no ADR | [ADR 0011](../adr/0011-tech-stack.md) (2026-06-04) |
| PH-3 | DB driver MySQL vs MariaDB unpinned | [ADR 0011](../adr/0011-tech-stack.md): MariaDB 11.7 (dev) / MySQL-8-compatible schema |
| PH-4 | `beats` / `scenes` / `chapters` tables were skeletal | [ADR 0015](../adr/0015-beat-document-and-boundaries.md) + [DATABASE.md](../architecture/DATABASE.md) §3.10–3.12 |
| PH-5 | `internal_states` / `active_emotions` columns were skeletal | [ADR 0014](../adr/0014-internal-state-schema.md) + [DATABASE.md](../architecture/DATABASE.md) §4.5–4.6 |
