# Manual QA — Sprint 5: LLM Client, Role Tiering, Call Log & Connection Test

> **Domain:** `ui` · **Stories:** S-5.1.2 (connection test), S-5.2.2 (model roles), S-5.3.1 (usage log) · the client/retry/structured-output/security invariants (S-5.2.1 / S-5.2.3 / S-5.3.2) are delegated to the automated suite (TC-6)
> **Date:** 2026-06-05 (Asia/Jakarta) · **Tester:** _____________ · **Build:** Sprint 5
> **Rule:** every step uses a visible navigation action (link/button). **No step types a URL to reach a page.** All three surfaces are reached through the **Settings** sidebar (**Provider**, **Model roles**, **Usage**).

## Preconditions

- App running (`composer run dev`) on MariaDB; assets built (`pnpm build` or `pnpm dev`).
- Signed in with a known account (see [S-2-account-shell.md](./S-2-account-shell.md) preconditions if you need one).
- A **valid OpenRouter key** stored via **Settings → Provider** (see [S-4-provider-key.md](./S-4-provider-key.md)) for the connection-test happy path (TC-1). An invalid/expired key is useful for TC-2.
- No engine caller exists yet, and the connection test never logs — so the **Usage** log is empty on a fresh account. Rows appear only once the engine starts calling, so the empty state (TC-5 step 3) is the expected result there.

---

## TC-1 — Connection test succeeds · S-5.1.2

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | From the sidebar, click **Settings**, then the **Provider** tab | Provider settings opens; the **Provider** sub-nav item is highlighted active |
| 2 | With a valid key on file, locate the **Test connection** button in the **Key on file** card | The button is a secondary action beside **Remove key** (single primary action stays the Save form) |
| 3 | Click **Test connection** | The button shows a **spinner** + "Testing…" (loading state); the page does **not** reload |
| 4 | Wait for the result | An inline **success** panel appears ("Connection successful — N models reachable") with a small set of model-slug **badges** — no native alert |

**Pass criteria:** the probe runs without a page reload; a clear loading→success transition; reachable model count + sample slugs render inline.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-2 — Connection test surfaces a failure reason · S-5.1.2

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | On **Settings → Provider**, replace the key with an invalid one (e.g. `sk-or-v1-bad-key`) via **Replace key** | Success toast confirms the (bad) key is stored |
| 2 | Click **Test connection** | Loading state, then an inline **error** panel ("Connection failed.") carrying the provider's reason via `AlertError` — no native alert, no stack trace |
| 3 | Remove the key entirely (**Remove key** → confirm), then revisit and re-add a valid key | With no key, a test reports the "No API key is stored" reason rather than throwing |

**Pass criteria:** failures render as a calm inline reason; the page never errors out or shows a raw exception.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-3 — Model roles renders every engine role · S-5.2.2

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | From the sidebar, click **Settings**, then the **Model roles** tab | The Model-roles screen opens; **Model roles** is highlighted active |
| 2 | On a fresh account (no profiles seeded), observe the top of the list | A first-run **hint** explains no roles are configured yet, with an example slug — not a blank screen |
| 3 | Scan the cards | One card **per engine role** (8), each with a human label + description, a **model slug** field, **temperature** + **max tokens**, an **Active** checkbox, and a "Not configured" badge while empty |

**Pass criteria:** all roles listed with friendly labels; empty/unconfigured state is taught, not blank.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-4 — Save a global role→model mapping · S-5.2.2

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | On **Settings → Model roles**, set a slug for one role (e.g. `anthropic/claude-sonnet-4`), adjust temperature/max tokens, leave **Active** checked | Inputs accept the values; numeric fields enforce their min/max |
| 2 | Click **Save model roles** | A success **toast** appears (no native alert); the page stays on Model roles (preserve scroll) |
| 3 | Reload **Settings → Model roles** via the sidebar | The saved slug + params persist; the role's "Not configured" badge is gone |
| 4 | Clear a required slug and Save | An inline validation error ("Choose a model slug for every role.") via `InputError` + the `AlertError` summary; nothing saved for that row |

**Pass criteria:** a single primary **Save** persists the global defaults; values round-trip; invalid input fails gracefully inline.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-5 — Usage log: deferred load + four states · S-5.3.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | From the sidebar, click **Settings**, then the **Usage** tab | The Usage screen opens immediately (shell renders); **Usage** is highlighted active |
| 2 | Watch the list area on first paint | A **skeleton** (pulsing rows) shows briefly while the deferred prop loads — the page is never blank/blocked |
| 3 | On a fresh account (no calls yet) | An **empty state** renders ("No model calls yet") explaining rows appear once the engine calls — not a blank table |
| 4 | (If rows exist) inspect a row | Columns: **Role**, **Model**, **Status** (badge), **Tokens**, **Cost (USD)**, **Latency**, **Time (WIB)**; failed rows show a destructive status badge |
| 5 | (If >15 rows) use **Previous / Next** | Pagination uses in-place partial reloads (`only: ['calls']`), preserving scroll; no full navigation |

**Pass criteria:** instant shell + skeleton (Doherty); empty/success states both handled; **cost shows in USD** and **time in WIB**; pagination is partial.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-6 — Client, retry, structured output & security invariants (automated-backed) · S-5.2.1 / S-5.2.3 / S-5.3.2

These are *negative*/security and transport invariants. Demonstrating them manually would require forcing provider 429/5xx, malformed JSON, foreign URLs, or reading at-rest storage — which this checklist forbids — so they are locked by automated tests instead:

- `tests/Feature/Llm/OpenRouterClientTest.php` — happy `complete` / `completeStructured` (`Http::fake`): key sent as **Bearer**, an **Ok** `llm_calls` row with tokens + `cost_micros_usd`, and the **key is never written** to the log.
- `tests/Feature/Llm/StructuredOutputRetryTest.php` — **headline (S-5.2.3):** malformed structured output is retried to the bound, then a **Failed** row + exception; parsed data is **never returned/trusted**.
- `tests/Feature/Llm/ModelRoleResolverTest.php` — per-story override beats the global default; an unmapped role throws (engine never guesses a model).
- `tests/Feature/Settings/ProviderConnectionTest.php` — success lists models; an invalid key surfaces the reason; the probe **never writes `llm_calls`**; guest redirect; throttled.
- `tests/Feature/Settings/ModelRoleSettingsTest.php` — renders; **upserts** the global profile; validation; guest redirect.
- `tests/Feature/Settings/UsageLogTest.php` — renders; **cross-owner negative** (user A never sees user B's calls); the deferred prop loads via `loadDeferredProps`; cost present; message bodies absent.
- `tests/Feature/Database/SaveRealmSchemaTest.php` / `AppendOnlyInvariantTest.php` — still green with the new nullable `llm_calls.user_id`; the log stays **append-only**.

**Manual evidence:** while signed in, no visible navigation exposes another user's calls, the raw provider key, or any logged message body.
**Pass criteria:** the LLM + settings + database suites are green; no navigation leaks cross-owner content or secrets.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## UX / Law-of-UX observations

- **Doherty Threshold:** the Usage shell paints instantly with a **skeleton** for the deferred log; the connection test shows an immediate spinner so the system never feels stalled. ✔
- **Single primary action** (Hick's): Model roles has one **Save**; Provider's **Test connection** is a secondary action and never competes with **Save key**. ✔
- **Empty states teach the next step** (Model roles "no roles configured", Usage "no model calls yet") instead of blank panels. ✔
- **No native alerts:** every result is inline (`InputError` / `AlertError` / success panel) or a `sonner` toast; the connection result uses `role="status"` `aria-live="polite"`. ✔
- **Standards exception (signposted):** provider **cost renders in USD** (matches the USD OpenRouter balance, PH-12) while time stays **WIB** — the only money-in-USD surface, and it is labelled "Cost (USD)". ✔
- **Reveal least** (security): the key is never echoed by the test; the Usage log shows counts/cost/latency only — never message bodies. ✔
- **Touch targets:** primary controls are `h-11` (≥44px). ✔

## UX Critical Violation check

> **None found.** All three surfaces are reached through visible **Settings** sidebar items (**Provider**, **Model roles**, **Usage**) — no QA step types a URL. The transport/retry/structured-output and security (cross-owner, key-never-logged, message-bodies-hidden) invariants, which would require forcing provider faults / foreign URLs / at-rest inspection to demo, are validated by automated tests rather than manual URL-typing (the S-4 TC-6 precedent).

## Sign-off

- Overall: ☐ Pass ☐ Fail
- Critical/High defects: ____________________
- Tester signature / date: ____________________

## Related

- [../../api/provider.md](../../api/provider.md) §6 (connection test) · [../../api/model-roles.md](../../api/model-roles.md) · [../../api/usage.md](../../api/usage.md)
- [../../architecture/ARCHITECTURE.md](../../architecture/ARCHITECTURE.md) §11 (Sprint 5) · [../../adr/0017-llm-orchestration-openrouter.md](../../adr/0017-llm-orchestration-openrouter.md)
- [../../architecture/Diagrams/Engine/Llm_Client_Flow.md](../../architecture/Diagrams/Engine/Llm_Client_Flow.md) · [../../architecture/DATABASE.md](../../architecture/DATABASE.md) §4.16 (`llm_calls`)
- [../../runbooks/local-setup-diagnostics.md](../../runbooks/local-setup-diagnostics.md) §11
