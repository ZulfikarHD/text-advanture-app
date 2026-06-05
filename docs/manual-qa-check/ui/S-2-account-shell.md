# Manual QA — Sprint 2: Users, Ownership & App Shell

> **Domain:** `ui` · **Stories:** S-2.2.1, S-2.2.2, S-2.2.3, S-2.1.3, S-3.1.1
> **Date:** 2026-06-05 (Asia/Jakarta) · **Tester:** _____________ · **Build:** Sprint 2
> **Rule:** every step uses a visible navigation action (link/button). **No step types a URL to reach a page.** Opening the app at its base address is the entry point, not a page jump.

## Preconditions

- App running (`composer run dev`) on MariaDB; assets built (`pnpm dev`/`pnpm build`).
- A known account exists. If not: `php artisan tinker --execute 'App\Models\User::factory()->create(["email" => "qa@example.com"]);'` (password: `password`).
- Start signed in (complete Sprint 1 TC-1 if needed). `REGISTRATION_ENABLED=true` for TC-1–TC-6.

---

## TC-1 — App shell & primary navigation · S-3.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | After signing in, look at the sidebar | Primary nav shows **Workspace** and **Settings** (no "Play", no external "Repository/Documentation" links); **Workspace** is highlighted as the active area |
| 2 | Click **Settings** | Settings opens (Profile tab); the **Settings** nav item is now highlighted as active |
| 3 | Within Settings, switch to **Security**, then **Appearance** | The **Settings** sidebar item stays highlighted across all `/settings/*` sub-pages |
| 4 | Click **Workspace** | Returns to the workspace home; **Workspace** is highlighted again |

**Pass criteria:** exactly two primary destinations; active-area indicator follows the current section; no dead/placeholder nav items.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-2 — Workspace empty state · S-3.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | On the **Workspace** home, observe the main area | A token-styled empty state renders: an icon, **"No stories yet"**, guidance text, and a single primary **New story** button labelled/affixed **Coming soon** |
| 2 | Try to click **New story** | The button is **disabled** (no navigation, no error) — it teaches the next step without linking to an unbuilt page |
| 3 | Toggle the OS/browser dark mode | The empty state remains legible in both light and dark (semantic tokens, sufficient contrast) |

**Pass criteria:** empty state teaches the next step; no dead link; dark-mode parity; one (disabled) primary action.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-3 — Update profile (name + email) · S-2.2.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Open **Settings → Profile** (sidebar **Settings**, or the **user menu → Settings**) | Profile form renders with current **Name** and **Email**; **no** "email unverified" banner appears |
| 2 | Change the Name, click **Save** | A success toast appears (no native alert); the new name is reflected (e.g. in the user menu) |
| 3 | Change the Email to a new valid address, click **Save** | Saves successfully; you are **not** asked to verify the new email (verification removed) |

**Pass criteria:** profile updates persist; success feedback via toast; no verification step; no native alert.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-4 — Change password · S-2.2.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Open **Settings → Security** | If prompted, confirm your current password (password-confirm gate), then the security page renders |
| 2 | Enter current password + a new password + confirmation, click **Save** | Success toast; the password is updated (subsequent sign-in uses the new password) |
| 3 | Enter a wrong current password and submit | Inline validation error on the current-password field; no native alert; password unchanged |

**Pass criteria:** password change succeeds with valid input and fails gracefully (inline) otherwise.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-5 — Passkey register & sign-in · S-2.1.3

> WebAuthn needs a platform/security-key authenticator; run on a device/browser that offers one.

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | On **Settings → Security**, click **Add passkey** (passkeys section) | The browser's native passkey prompt appears; on completion the new passkey is listed with its name + "Added …" time |
| 2 | Click the **remove** (trash) action on a passkey | A confirmation **dialog** appears (not a native `confirm()`); **Cancel** dismisses, the destructive button is in destructive color |
| 3 | Sign out, then on the **Login** page click **Sign in with passkey** | The browser passkey prompt appears; on success you are signed in and land on the Workspace |

**Pass criteria:** passkey can be registered, listed, removed (with dialog confirmation), and used to sign in; no native alerts.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-6 — Delete account (destructive confirmation) · S-2.2.1

> Use a throwaway account — this permanently deletes it.

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | On **Settings → Profile**, scroll to **Delete account**, click **Delete account** | A confirmation **dialog** opens asking for your password (not a native `confirm()`) |
| 2 | Click **Cancel** | Dialog closes; nothing is deleted |
| 3 | Reopen, enter your password, confirm **Delete account** | Account is deleted; session ends; you land on the public landing page |

**Pass criteria:** deletion always requires explicit dialog confirmation + password; cancel is safe.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-7 — Configurable registration · S-2.2.3

| # | Action (navigation / config) | Expected |
|---|------------------------------|----------|
| 1 | With `REGISTRATION_ENABLED=true`, sign out and open the app; check the Welcome page and the Login page | A **Register** / **Sign up** affordance is visible on both |
| 2 | Click **Register**, complete the form | A new account is created and signed in (lands on Workspace) |
| 3 | Set `REGISTRATION_ENABLED=false` in `.env`, run `php artisan config:clear`, reload the app | The **Register** / **Sign up** links are **gone** from Welcome and Login; **Log in** still works for existing users |

**Pass criteria:** the toggle hides sign-up affordances and never blocks sign-in. The server-side 404 on the disabled `/register` GET+POST is locked by `RegistrationToggleTest` (no URL-typing step needed).
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-8 — Account isolation (automated-backed) · S-2.2.2

Account isolation is a *negative*/security invariant: another user's data must be invisible (404) and un-mutable (403). Verifying it manually would require typing a foreign resource URL, which this checklist forbids — so the assertions are locked by automated tests instead:

- `tests/Feature/Auth/OwnershipIsolationTest.php` — owner sees only their own rows; a cross-owner direct lookup returns null (scope → 404 on binding); `OwnerPolicy` denies cross-owner update/delete; create stamps the current owner.

**Manual evidence:** while signed in, no visible navigation exposes another user's content. (First real owned model — stories — arrives in Phase 2.)
**Pass criteria:** the ownership suite is green; no navigation leaks cross-owner content.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## UX / Law-of-UX observations

- **Single primary action** (Hick's): the workspace empty state has one (disabled) primary; Settings forms each have one primary **Save**. ✔
- **Empty state teaches the next step** (not a blank screen). ✔
- **Confirm destructive actions:** account delete + passkey removal both go through a dialog; no `window.confirm`. ✔
- **No native alerts:** all feedback is inline or via `sonner` toasts. ✔
- **Active-area indicator** (Jakob's): Settings stays highlighted across `/settings/*`. ✔

## UX Critical Violation check

> **None found.** Every Sprint 2 product page (Workspace, Settings → Profile/Security/Appearance, Register when enabled) is reachable through a visible link or button. No QA step types a URL to reach a page. The cross-owner isolation invariant (which would require a foreign URL to demo) is validated by automated tests rather than a manual URL-typing step.

## Sign-off

- Overall: ☐ Pass ☐ Fail
- Critical/High defects: ____________________
- Tester signature / date: ____________________

## Related

- [../../api/account.md](../../api/account.md) · [../../api/auth.md](../../api/auth.md)
- [../../architecture/Diagrams/App/Account_Ownership_Isolation.md](../../architecture/Diagrams/App/Account_Ownership_Isolation.md) · [../../architecture/Diagrams/App/App_Shell_Navigation.md](../../architecture/Diagrams/App/App_Shell_Navigation.md)
- [../../runbooks/local-setup-diagnostics.md](../../runbooks/local-setup-diagnostics.md)
