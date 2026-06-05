# Manual QA — Sprint 4: Provider Key & Two-Realm Schema

> **Domain:** `ui` · **Stories:** S-5.1.1, S-5.1.3 (provider key) · schema S-4.1.2 / S-4.2.1 / S-4.2.2 delegated to the automated suite (TC-5)
> **Date:** 2026-06-05 (Asia/Jakarta) · **Tester:** _____________ · **Build:** Sprint 4
> **Rule:** every step uses a visible navigation action (link/button). **No step types a URL to reach a page.** The provider page is reached through the **Settings → Provider** sidebar item.

## Preconditions

- App running (`composer run dev`) on MariaDB; assets built (`pnpm build` or `pnpm dev`).
- Signed in with a known account (see [S-2-account-shell.md](./S-2-account-shell.md) preconditions if you need one).
- **No live LLM call is made this sprint** — saving only stores the key; the `LlmClient` / "test connection" is Sprint 5. Any OpenRouter-shaped string (e.g. `sk-or-v1-test-1234`) is fine for these flows.

---

## TC-1 — Add a provider key (first time) · S-5.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | From the sidebar, click **Settings**, then the **Provider** tab | Provider settings opens ("OpenRouter API key"); the **Provider** sub-nav item is highlighted active |
| 2 | With no key stored, observe the status area | An **empty state** renders ("No provider key yet") with guidance to add one — not a blank screen |
| 3 | In **API key**, paste a key (e.g. `sk-or-v1-test-1234`); leave **Base URL** blank | The key field is a masked password input; the Base URL placeholder shows the default gateway URL |
| 4 | Click **Save key** | A success **toast** appears (no native alert); the page now shows **Key on file** with a masked value (`••••••••1234`) and an "Updated … ago" line |

**Pass criteria:** key persists; UI shows only the masked value; success via toast; empty→success state transition is clear.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-2 — Masked-only display (key never shown) · S-5.1.3

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | With a key on file, reload **Settings → Provider** via the sidebar | The **Key on file** card shows only the masked form (`••••••••` + last 4); the full key is **never** rendered |
| 2 | Open the browser devtools → Network, find the page's Inertia props (page load / partial) | The props contain `maskedKey` only — **no** raw `api_key` field anywhere in the payload |

**Pass criteria:** neither the rendered page nor the JSON props ever expose the plaintext key.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-3 — Replace the key · S-5.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | On **Settings → Provider** with a key on file, note the masked last-4 | The primary button now reads **Replace key**; the field label reads **Replace API key** |
| 2 | Enter a different key (different last 4 digits), click **Replace key** | Success toast; the **Key on file** masked value updates to the new last 4; "Updated … ago" refreshes |

**Pass criteria:** replacing overwrites the stored key in place (still one key per account); masked display reflects the new value.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-4 — Remove the key (destructive confirmation) · S-5.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | On **Settings → Provider** with a key on file, click **Remove key** | A confirmation **dialog** appears ("Remove provider key?", not a native `confirm()`); the confirm button is in destructive color |
| 2 | Click **Cancel** | Dialog closes; the key stays on file (nothing removed) |
| 3 | Reopen, click **Remove key** to confirm | Success toast; the page returns to the **empty state** ("No provider key yet") |

**Pass criteria:** removal always goes through the dialog; Cancel is safe; post-remove returns to the empty state.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-5 — Empty / invalid key rejected · S-5.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | On **Settings → Provider**, click **Save key** with the field blank | Inline validation error ("Enter your provider API key.") via `InputError` + the summary `AlertError`; no native alert; nothing stored |
| 2 | Enter a too-short value (under 8 chars), click Save | Inline error ("That key looks too short to be valid."); still nothing stored |
| 3 | Enter a malformed **Base URL** (e.g. `not a url`) with a valid key, click Save | Inline error on Base URL (must be a URL); valid key + blank/valid URL is required to succeed |

**Pass criteria:** invalid input fails gracefully inline; only a valid key is accepted.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-6 — Security & two-realm schema invariants (automated-backed) · S-5.1.3 / S-4.2.2

These are *negative*/security and structural invariants. Demonstrating them manually would require typing foreign URLs, raw SQL, or reading at-rest storage — which this checklist forbids — so they are locked by automated tests instead:

- `tests/Feature/Settings/ProviderCredentialTest.php` — key **encrypted at rest** (stored DB value ≠ plaintext); owner isolation (user A cannot read/replace/remove user B's key); empty key rejected; key never serialized into props or model output.
- `tests/Feature/Settings/ProviderSettingsTest.php` — page renders for an auth user with **masked-only** props; redirects to login when unauthenticated.
- `tests/Feature/Database/SaveRealmSchemaTest.php` — all 16 save tables + key columns exist; **headline:** `beat_records` has no `private_text` and a surface-only read cannot reach `beat_true_states.private_text` (structural isolation).
- `tests/Feature/Database/AppendOnlyInvariantTest.php` — `UPDATE`/`DELETE` throws on each append-only model; no `updated_at`.
- `tests/Feature/Database/GlobalLibrariesSchemaTest.php` — 5 library tables; no `story_id` except `model_profiles` (nullable).
- `tests/Feature/Database/DeferredForeignKeysTest.php` — the 3 PH-16 FKs are now enforced.
- `tests/Feature/Database/SaveRealmMigrationTest.php` — full both-realm `migrate:fresh → rollback → migrate` is clean.

**Manual evidence:** while signed in, no visible navigation exposes another user's key or any save-realm private state.
**Pass criteria:** the provider + database suites are green; no navigation leaks cross-owner or private content.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## UX / Law-of-UX observations

- **Single primary action** (Hick's): one **Save key** / **Replace key** primary; **Remove key** is a secondary, destructively-tinted button. ✔
- **Empty state teaches the next step** ("No provider key yet, add one") instead of a blank panel. ✔
- **Confirm destructive actions:** removing the key goes through the shared `ConfirmDialog`; no `window.confirm`. ✔
- **No native alerts:** all feedback is inline (`InputError`/`AlertError`) or via `sonner` toasts. ✔
- **Reveal least** (security): only the masked last-4 is ever shown; the input is a password field with `autocomplete="off"`. ✔
- **Touch targets:** all controls are `h-11` (≥44px). ✔

## UX Critical Violation check

> **None found.** The provider page is reached through the visible **Settings → Provider** sidebar item — no QA step types a URL. The security (encryption / cross-owner) and two-realm schema invariants, which would require foreign URLs / raw SQL / at-rest inspection to demo, are validated by automated tests rather than manual URL-typing (the S-2 TC-8 precedent).

## Sign-off

- Overall: ☐ Pass ☐ Fail
- Critical/High defects: ____________________
- Tester signature / date: ____________________

## Related

- [../../api/provider.md](../../api/provider.md)
- [../../architecture/DATABASE.md](../../architecture/DATABASE.md) §4 (save realm) · §7 (`provider_credentials`)
- [../../architecture/Diagrams/Data/Persistence_Erd.md](../../architecture/Diagrams/Data/Persistence_Erd.md)
- [../../runbooks/local-setup-diagnostics.md](../../runbooks/local-setup-diagnostics.md) §10
