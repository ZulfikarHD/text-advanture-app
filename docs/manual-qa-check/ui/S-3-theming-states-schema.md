# Manual QA — Sprint 3: Theming, States & Authoring Schema

> **Domain:** `ui` · **Stories:** S-3.1.2, S-3.1.3, S-3.2.1, S-3.2.2, S-4.1.1
> **Date:** 2026-06-05 (Asia/Jakarta) · **Tester:** _____________ · **Build:** Sprint 3
> **Rule:** every step uses a visible navigation action (link/button). **No step types a URL to reach a page.** Opening the app at its base address is the entry point, not a page jump.

## Preconditions

- App running (`composer run dev`) on MariaDB; assets built (`pnpm dev`/`pnpm build`).
- A known account exists. If not: `php artisan tinker --execute 'App\Models\User::factory()->create(["email" => "qa@example.com"]);'` (password: `password`).
- Start signed in (complete Sprint 1 TC-1 if needed).

---

## TC-1 — Quick theme toggle from the user menu · S-3.1.2

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Open the **user menu** (click your name/avatar at the bottom of the sidebar) | The menu shows a **Theme** label with a **Light / Dark / System** segmented control |
| 2 | Click **Dark** | The whole app switches to dark instantly; the menu stays open and the selected option is highlighted |
| 3 | Click **Light**, then **System** | Theme follows the choice; **System** matches the current OS appearance |
| 4 | Pick **Dark**, then **reload** the page | The app paints **dark on first frame** — no white flash before hydration (cookie applied server-side) |
| 5 | Open **Settings → Appearance** | The Appearance tabs reflect the **same** current selection (single source of truth) |

**Pass criteria:** theme is changeable from the shell (not only Settings), persists across reloads, and there is no flash-of-wrong-theme.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-2 — Welcome page light/dark parity · S-3.1.2 (PH-13)

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Sign out (user menu → **Log out**) to reach the public **Welcome** page | The landing page renders with brand mark, a headline, supporting text, and **one** primary call-to-action (**Log in to start**) |
| 2 | Toggle OS dark mode (or revisit after setting Dark) | Welcome stays fully legible in both themes — no hardcoded light-only colors, sufficient contrast |
| 3 | Inspect the buttons (hover/focus, size) | Controls are comfortably tap-sized (≥44px height) with a visible focus ring |

**Pass criteria:** Welcome honors the theme in both modes, uses one primary action, and meets touch-target/contrast expectations.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-3 — Workspace empty state · S-3.2.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Sign back in and land on **Workspace** | A token-styled **EmptyState** renders: icon, **"No stories yet"**, guidance text, and a single primary **New story** action affixed **Coming soon** |
| 2 | Try to click **New story** | The button is **disabled** (no navigation, no error) — it teaches the next step without linking to an unbuilt page |
| 3 | Switch between light and dark (TC-1) | The empty state stays legible in both (semantic tokens) |

**Pass criteria:** the reusable empty-state component teaches the next step; one (disabled) primary action; dark-mode parity; no dead link.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-4 — Destructive confirmation (delete account) · S-3.2.2

> Use a throwaway account — this permanently deletes it.

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Open **Settings → Profile**, scroll to **Delete account**, click **Delete account** | A confirmation **dialog** opens asking for your password — **not** a native `confirm()`/`alert()`; the warning box uses destructive (not raw-red) styling |
| 2 | Click **Cancel** | Dialog closes; nothing is deleted |
| 3 | Reopen, enter your password, confirm **Delete account** | Account is deleted; session ends; you land on the public Welcome page |

**Pass criteria:** destructive actions always go through a dialog (never a native browser alert) with a clear Cancel and a destructive-colored confirm; cancel is safe.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-5 — Keyboard, focus & responsive shell · S-3.1.3

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | On any signed-in page, press **Tab** once from the top | A **"Skip to content"** link appears (it was visually hidden) and is the first focusable element; pressing **Enter** moves focus into the main content |
| 2 | Continue tabbing through the sidebar nav | Each item shows a visible focus ring; the active item is announced as current (`aria-current="page"`) |
| 3 | Narrow the window to tablet width (or use device emulation) | The sidebar collapses to a toggle; opening it shows the off-canvas nav; breadcrumbs/header remain usable |

**Pass criteria:** keyboard users can skip to content, every interactive element has a visible focus state, and the shell remains navigable at tablet width.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-6 — Authoring-realm schema (automated-backed) · S-4.1.1

S-4.1.1 is a **data-layer** story with **no UI** this sprint (story authoring CRUD arrives Phase 2), so its invariants are locked by automated tests rather than a manual step (no page exists to navigate to, and proving them manually would require typing URLs / using tinker):

- `tests/Feature/Database/AuthoringRealmSchemaTest.php` — all 11 authoring tables + key columns exist; `character_cards` is unique per `(character_id, chapter_id)`.
- `tests/Feature/Database/AuthoringRealmMigrationTest.php` — migrate → rollback → migrate runs cleanly (DoD: both directions reversible).
- `tests/Feature/Authoring/StoryOwnershipTest.php` — `stories` is owner-scoped (foreign story → not found / policy-denied; create stamps the owner).
- `tests/Feature/Authoring/AuthoringRelationshipsTest.php` — FK chain resolves to the owning story; per-parent slug uniqueness; deleting a story cascades to children.

**Manual evidence:** no visible navigation exposes authoring data yet (by design). Run `php artisan test --compact --filter='Authoring|AuthoringRealm'` and confirm green.
**Pass criteria:** the authoring schema/ownership suites are green; no navigation surfaces unbuilt authoring pages.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## UX / Law-of-UX observations

- **Single primary action** (Hick's): Welcome has one CTA; the workspace empty state has one (disabled) primary. ✔
- **Aesthetic-Usability / consistency** (Jakob's): theme toggle in the menu mirrors the Settings → Appearance control; one source of truth. ✔
- **Doherty (responsiveness):** theme switches instantly; no flash-of-wrong-theme on reload. ✔
- **Confirm destructive actions:** account delete goes through a dialog; no `window.confirm` anywhere; `useConfirm()` is the standard path for future deletes. ✔
- **Accessibility:** skip-to-content link, focus-visible rings, `aria-current` on active nav, ≥44px targets. ✔
- **No native alerts:** all feedback is inline or via `sonner` toasts / dialogs. ✔

## UX Critical Violation check

> **None found.** Every Sprint 3 product surface (Welcome, Workspace empty state, Settings → Appearance, the user-menu theme toggle, account-delete dialog) is reachable through a visible link or button. No QA step types a URL to reach a page. S-4.1.1 has no UI; its invariants are validated by automated tests rather than a manual URL-typing/tinker step.

## Sign-off

- Overall: ☐ Pass ☐ Fail
- Critical/High defects: ____________________
- Tester signature / date: ____________________

## Related

- [../../architecture/ARCHITECTURE.md](../../architecture/ARCHITECTURE.md) §11 — Sprint 3 subsection
- [../../architecture/DATABASE.md](../../architecture/DATABASE.md) §3 — authoring realm · [../../architecture/Diagrams/Data/Persistence_Erd.md](../../architecture/Diagrams/Data/Persistence_Erd.md)
- [../../guides/PLACEHOLDER_TRACKING.md](../../guides/PLACEHOLDER_TRACKING.md) — PH-13/PH-14 resolved, PH-16 added
- [../../runbooks/local-setup-diagnostics.md](../../runbooks/local-setup-diagnostics.md) §9 — authoring migrate/rollback
