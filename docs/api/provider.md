# API Contract — Provider key management (Sprint 4)

Endpoint and Inertia-props contract for the **provider API-key** settings surface (S-5.1.1 / S-5.1.3): an account stores its own **encrypted** LLM-provider key so generation runs on its own quota. Routes are consumed through **Wayfinder** typed helpers (`@/routes/provider`, `@/actions/App/Http/Controllers/Settings/ProviderController`). Everything here requires the `auth` middleware.

> This sprint is **storage + management + security only**. The `LlmClient` and a live "test connection" call are Sprint 5 (S-5.1.2 / S-5.2.x). Storage is UTC; timestamps render in Asia/Jakarta.

## 1. Endpoints

| Method | URI | Route name | Auth | Purpose |
|--------|-----|------------|------|---------|
| GET | `/settings/provider` | `provider.edit` | auth | Render provider settings (`settings/Provider`) |
| PUT | `/settings/provider` | `provider.update` | auth (throttle 6/min) | Store or replace the API key |
| DELETE | `/settings/provider` | `provider.destroy` | auth | Remove the stored key |

Reached from the **Settings → Provider** sidebar entry — the page is fully nav-reachable (no URL typing). Removal is confirmed through the shared `ConfirmDialog` (`useConfirm`), never a native `confirm()`/`alert()`; success raises a `sonner` toast via the shared `toast` flash.

## 2. PUT `/settings/provider` (provider.update)

| Field | Type | Rules |
|-------|------|-------|
| `api_key` | string | required, min 8, max 255 |
| `base_url` | string | nullable, url, max 255 |

`ProviderCredentialService::store()` upserts the row for `(user_id, openrouter)`, encrypting `api_key` and recording `last_four`. On success: flash `toast` (success) and redirect to `provider.edit`.

## 3. Inertia props (`settings/Provider`)

| Prop | Type | Notes |
|------|------|-------|
| `provider` | `string` | `"openrouter"` |
| `defaultBaseUrl` | `string` | `config('services.openrouter.base_url')` |
| `credential` | `object \| null` | `null` when no key is stored |
| `credential.maskedKey` | `string` | `••••••••<last4>` — **never** the raw key |
| `credential.baseUrl` | `string \| null` | per-account override, if set |
| `credential.updatedAtForHumans` | `string \| null` | e.g. "2 hours ago" |

The raw `api_key` is **never** serialized into props (model `#[Hidden]` + an `encrypted` cast); only the computed `masked_key` is exposed.

## 4. Security contract

- The key is **encrypted at rest** (Laravel `encrypted` cast) and **owner-scoped** via `BelongsToOwner` — one user can never read or mutate another's credential (route/query resolves to "not found"; `ProviderCredentialPolicy` extends `OwnerPolicy`).
- Props and JSON responses expose **only** `masked_key`; the plaintext key leaves the server only as the provider `Authorization` header (Sprint 5).
- `llm_calls.messages` may embed save-realm-sensitive content, so it is debug-gated and `#[Hidden]` (DATABASE.md §5) — keys never land in the call log.
- `provider.update` is throttled (6/min). Unauthenticated requests redirect to `login`.

## 5. Storage divergence (PH-18)

[ADR 0017](../adr/0017-llm-orchestration-openrouter.md) §1 sketched a single `.env` key. The program NFR ("API keys encrypted at rest, scoped to owner") and the `BelongsToOwner` docblock (which already names "API keys") take precedence: the key is a **per-owner encrypted DB record** (`provider_credentials`, DATABASE.md §7). Only the provider `base_url` stays in `.env`/config.

## Related

- [../architecture/DATABASE.md](../architecture/DATABASE.md) §7 — `provider_credentials` schema
- [../architecture/ARCHITECTURE.md](../architecture/ARCHITECTURE.md) §11 — Sprint 4 subsection
- [account.md](./account.md) — the broader settings surface + ownership convention
- [../manual-qa-check/ui/S-4-provider-key.md](../manual-qa-check/ui/S-4-provider-key.md) — manual QA path
