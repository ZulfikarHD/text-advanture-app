# API Contract — Model-role settings (Sprint 5)

Endpoint and Inertia-props contract for the **model-role mapping** settings surface (S-5.2.2): the global `LlmRole` → model-slug + params mapping the engine routes by. Routes are consumed through **Wayfinder** typed helpers (`@/routes/model-roles`, `@/actions/App/Http/Controllers/Settings/ModelRoleController`). Everything requires the `auth` middleware.

> Engine calls are routed by **role**, never a hard-coded slug ([ADR 0017](../adr/0017-llm-orchestration-openrouter.md) §2). This screen edits the **global** defaults (`model_profiles` scope=`Global`, `story_id` null). Per-story overrides (scope=`Story`) await story management (Phase 2, PH-19). Defaults are **now seeded** (Sprint 6 `ModelProfileSeeder` — one global row per `LlmRole`, the "mid" appraiser tier seeds `anthropic/claude-3.5-sonnet`, PH-26), so the screen ships pre-filled rather than empty; the values stay author-editable.

## 1. Endpoints

| Method | URI | Route name | Auth | Purpose |
|--------|-----|------------|------|---------|
| GET | `/settings/model-roles` | `model-roles.edit` | auth | Render the model-role editor (`settings/ModelRoles`) |
| PUT | `/settings/model-roles` | `model-roles.update` | auth (throttle 12/min) | Upsert the global profile for each submitted role |

Reached from the **Settings → Model roles** sidebar entry — fully nav-reachable. Success raises a `sonner` toast via the shared `toast` flash.

## 2. Inertia props (`settings/ModelRoles`)

A `roles` array with one row per `LlmRole` case (8 roles), merging any stored global profile:

| Prop | Type | Notes |
|------|------|-------|
| `roles[].role` | `string` | The `LlmRole` value (e.g. `narrator_prose`) |
| `roles[].label` | `string` | Human label (e.g. "Narrator prose") |
| `roles[].description` | `string` | What the role is used for + its default tier |
| `roles[].modelSlug` | `string` | Stored slug, or `''` when unconfigured |
| `roles[].temperature` | `number` | Stored param, default `0.7` |
| `roles[].maxTokens` | `number` | Stored param, default `2048` |
| `roles[].isActive` | `boolean` | Whether the profile is active (default `true`) |
| `roles[].configured` | `boolean` | `false` when no global profile exists yet |

## 3. PUT `/settings/model-roles` (model-roles.update)

Body: `roles[]`, each `{ role, model_slug, temperature, max_tokens, is_active }`.

| Field | Rules |
|-------|-------|
| `roles` | required, array, min 1 |
| `roles.*.role` | required, `LlmRole` enum |
| `roles.*.model_slug` | required, string, max 120 |
| `roles.*.temperature` | required, numeric, 0–2 |
| `roles.*.max_tokens` | required, integer, 1–200000 |
| `roles.*.is_active` | required, boolean |

For each row, `ModelProfile::updateOrCreate` upserts the unique `(scope=Global, story_id=null, role)` profile, storing `temperature`/`max_tokens` into the `params` JSON. On success: flash `toast` (success) and redirect to `model-roles.edit`.

## Related

- [../architecture/DATABASE.md](../architecture/DATABASE.md) — `model_profiles` schema · [../adr/0017-llm-orchestration-openrouter.md](../adr/0017-llm-orchestration-openrouter.md) §2 — role tiering
- [provider.md](./provider.md) — provider key + connection test · [usage.md](./usage.md) — the call/cost log
- [../manual-qa-check/ui/S-5-llm-client.md](../manual-qa-check/ui/S-5-llm-client.md) — manual QA path
