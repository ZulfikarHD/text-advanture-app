# Manual QA — Sprint 1: Foundation, Auth & App Shell

> **Domain:** `ui` · **Stories:** S-1.1.1, S-1.1.2, S-1.1.3, S-2.1.1, S-2.1.2, S-2.1.4
> **Date:** 2026-06-05 (Asia/Jakarta) · **Tester:** _____________ · **Build:** Sprint 1
> **Rule:** every step uses a visible navigation action (link/button). **No step types a URL to reach a page.** Opening the app at its base address is the entry point, not a page jump.

## Preconditions

- App running (`composer run dev`) on MariaDB; assets built (`pnpm dev`/`pnpm build`).
- A known account exists. If not, create one: `php artisan tinker --execute 'App\Models\User::factory()->create(["email" => "qa@example.com"]);'` (password: `password`).
- Start signed out (if a session exists, complete TC-3 first).

---

## TC-1 — Sign in (happy path) · S-2.1.1, S-1.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Open the app at its base address (`/`) | Welcome page renders without errors; a **Log in** link is visible (top-right) |
| 2 | Click **Log in** | Sign-in page renders: Email, Password, Remember me, and a single primary **Log in** button |
| 3 | Enter the known email + correct password, click **Log in** | A brief spinner shows; you are signed in and land on the **Dashboard**; the app shell (sidebar with **Dashboard**, user menu) is visible |

**Pass criteria:** authenticated session granted; redirected to the workspace home; no console errors.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-2 — Sign in with wrong password (no field disclosure) · S-2.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | From the Welcome page, click **Log in** | Sign-in page renders |
| 2 | Enter the known email + an **incorrect** password, click **Log in** | You are **not** signed in; a single generic message appears ("These credentials do not match our records."); the message does **not** say whether the email or the password was wrong |

**Pass criteria:** stays on sign-in; generic error only; no native browser alert; no hint about which field failed.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-3 — Sign out · S-2.1.2

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | While signed in, open the **user menu** (avatar/name in the sidebar footer) | A dropdown opens with **Settings** and **Log out** |
| 2 | Click **Log out** | Session ends; you are returned to the public landing page; the top-right now shows **Log in** (not Dashboard) |

**Pass criteria:** session invalidated; the workspace is no longer reachable from any visible navigation (the Dashboard link is gone).
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-4 — Route protection · S-2.1.4

Route protection is a *negative*/security behavior; its assertions are locked by automated tests (`DashboardTest`, `AuthenticationTest::test_users_are_redirected_to_their_intended_destination_after_login`). The **manual evidence** is the absence of any navigation path to protected content while signed out — verified without typing a URL.

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | While signed **out**, inspect the Welcome page and any visible navigation | There is **no** link/button that leads to the Dashboard or settings; only Log in / Register are offered |
| 2 | Sign in (TC-1), confirm the Dashboard link appears, then sign out (TC-3) | Protected navigation appears only while authenticated and disappears on sign-out |

**Pass criteria:** protected areas are unreachable via navigation when signed out; intended-destination redirect is green in the automated suite.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-5 — Standards & typed routes (spot check) · S-1.1.2, S-1.1.3

| # | Action | Expected |
|---|--------|----------|
| 1 | Confirm config: `php artisan tinker --execute 'echo config("app.timezone")."/".config("app.display_timezone")."/".config("app.currency");'` | Prints `UTC/Asia/Jakarta/IDR` (store UTC, display WIB, money Rupiah) |
| 2 | Confirm typed routes/lint: `pnpm lint:check && pnpm types:check` | Both pass with no errors (Wayfinder types resolve; a removed route reference would fail the build) |

**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## UX / Law-of-UX observations

- **Single primary action** (Hick's Law): the sign-in page has exactly one primary button; the passkey option is a secondary (`outline`) control. ✔
- **Feedback** (Doherty): the Log in button shows a spinner while processing. ✔
- **No native alerts:** errors render inline via the page, never `window.alert`. ✔
- **Known divergences (not Sprint 1 scope, tracked):** the Welcome landing uses hardcoded hex colors and default (<44px) control sizes instead of design tokens/Fitts targets — see PH-13; reconciled with Sprint 3 theming. The login status banner uses a raw `text-green-600` rather than a token.

## UX Critical Violation check

> **None found.** Every product page in Sprint 1 (Welcome, Login, Dashboard, Settings, sign-out) is reachable through a visible link or button. No QA step requires typing a URL to reach a page. Deep-link/bookmark behavior (intended-destination redirect) is validated by automated tests rather than a manual URL-typing step.

## Sign-off

- Overall: ☐ Pass ☐ Fail
- Critical/High defects: ____________________
- Tester signature / date: ____________________

## Related

- [../../api/auth.md](../../api/auth.md) · [../../architecture/Diagrams/App/Auth_Signin_Flow.md](../../architecture/Diagrams/App/Auth_Signin_Flow.md) · [../../runbooks/local-setup-diagnostics.md](../../runbooks/local-setup-diagnostics.md)
