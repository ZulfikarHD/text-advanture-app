# API Contract — Usage / activity log (Sprint 5)

Inertia-props contract for the **LLM usage & cost log** settings surface (S-5.3.1): the owner's append-only `llm_calls` history. The route is consumed through the **Wayfinder** typed helper (`@/routes/usage`). Requires the `auth` middleware.

> The list is **owner-scoped** (a user only ever sees their own calls, enforced by `LlmCall`'s `OwnerScope`) and arrives as a **deferred prop** — the shell renders instantly and the log query resolves in a follow-up request behind a skeleton. Cost renders in **USD** (PH-12); timestamps render in **WIB**. Message bodies are never exposed.

## 1. Endpoints

| Method | URI | Route name | Auth | Purpose |
|--------|-----|------------|------|---------|
| GET | `/settings/usage` | `usage.index` | auth | Render the usage log (`settings/Usage`) |

Reached from the **Settings → Usage** sidebar entry — fully nav-reachable.

## 2. Inertia props (`settings/Usage`)

| Prop | Type | Notes |
|------|------|-------|
| `calls` | deferred paginator | `Inertia::defer()` — **absent** on the initial response, loaded on a follow-up request (15/page) |
| `calls.data[]` | array | One presentable row per call (see below) |
| `calls.total` / `from` / `to` | int | Pagination meta |
| `calls.prev_page_url` / `next_page_url` | string \| null | Pagination links (partial-reload `only: ['calls']`) |

Each `calls.data[]` row:

| Field | Type | Notes |
|-------|------|-------|
| `id` | int | Call id |
| `role` / `roleLabel` | string | `LlmRole` value + human label |
| `modelSlug` | string | Resolved model |
| `status` | string | `ok` / `retry` / `failed` |
| `promptTokens` / `completionTokens` | int \| null | Token usage |
| `costMicrosUsd` | int \| null | Provider cost in USD micro-units → rendered with `formatUsdFromMicros` |
| `latencyMs` | int \| null | Wall-clock latency |
| `createdAt` | string \| null | ISO-8601 (UTC); rendered in WIB via `formatDateTime` |
| `error` | string \| null | Failure reason (failed calls) |

## 3. States

The page implements the four async states: a **skeleton** while the deferred prop loads (`<Deferred>` fallback), an **empty state** ("No model calls yet") when the owner has none, the **table** on success, and inline error reasons per failed row.

## 4. Security

`llm_calls` is **owner-scoped** (`user_id` + `BelongsToOwner`, PH-20) — the cross-owner negative is asserted by `tests/Feature/Settings/UsageLogTest.php`. The `messages` column is `#[Hidden]` and debug-gated (S-5.3.2), so message bodies never reach this surface; only counts/cost/latency are exposed.

## Related

- [../architecture/DATABASE.md](../architecture/DATABASE.md) §4.16 — `llm_calls` schema · [../adr/0017-llm-orchestration-openrouter.md](../adr/0017-llm-orchestration-openrouter.md) §4 — what gets logged
- [../architecture/Diagrams/Engine/Llm_Client_Flow.md](../architecture/Diagrams/Engine/Llm_Client_Flow.md) — the call → log flow
- [provider.md](./provider.md) · [model-roles.md](./model-roles.md)
- [../manual-qa-check/ui/S-5-llm-client.md](../manual-qa-check/ui/S-5-llm-client.md) — manual QA path
