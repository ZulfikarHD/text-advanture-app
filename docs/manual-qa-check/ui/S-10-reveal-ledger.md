# Manual QA — E4.1: Reveal Ledger CRUD

> **Domain:** `ui` · **Story:** S-4.1.1 (per-story reveal-ledger entry CRUD)
> **Date:** 2026-06-06 (Asia/Jakarta) · **Tester:** _____________ · **Build:** E4.1
> **Rule:** every step uses a visible navigation action (link/button/tab). **No step types a URL to reach a page.** The reveal ledger is reached by opening a **story card** on the Workspace dashboard, then the **Reveal ledger** tab.

## Preconditions

- App running (`composer run dev`) on MariaDB; assets built (`pnpm build` or `pnpm dev`).
- Signed in with an account that owns **at least one story** (create one from the Workspace dashboard if needed).
- Because authoring chapters/characters lands in a later phase, there is no UI to create them yet. To exercise full CRUD (a reveal point is required), seed a chapter/character via factory/tinker, or verify the **gated empty state** below on a fresh story.

---

## TC-1 — Chapter-gated empty state teaches the next step · S-4.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Open a story with **no chapters**, click the **Reveal ledger** tab | The Reveal ledger surface opens inside the workspace shell with a heading + a one-line spoiler-safety description |
| 2 | Observe the empty state | A teaching empty state ("Add a chapter first") with a single primary action **Go to Structure** — never a blank screen, and **no** disabled/dead "New entry" button |

**Pass criteria:** because a reveal point is required, creation is gated behind a reachable teaching empty state that points at Structure (Hick's Law: one primary action).
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-2 — Empty state with chapters present · S-4.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | On a story that **has ≥1 chapter** but no entries, open the **Reveal ledger** tab | A teaching empty state ("No reveal-ledger entries yet") with a single **New entry** primary action |

**Pass criteria:** with chapters available, the surface offers exactly one primary action to create the first entry.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-3 — Create an entry · S-4.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Click **New entry** | A dialog opens: Fact, Reveal chapter (required), About (optional), Who knows before the reveal (slug chips), Notes |
| 2 | Pick a **Reveal chapter** from the dropdown | The dropdown lists this story's chapters as "Chapter N — Title" |
| 3 | Leave **About** unset | It defaults to **World secret** (or, with no characters, shows the world-secret hint) |
| 4 | Type a character slug into **Who knows** and press **Enter** | The slug becomes a removable chip; repeat to add a few |
| 5 | Enter a Fact + reveal chapter, click **Create entry** | The dialog closes, a success toast shows, and the entry appears as a card (fact in mono, a World-secret/character badge, who-knows chips, a Chapter N badge) |

**Pass criteria:** the entry is created; "About" defaults to a world secret; who-knows renders as slug chips; the reveal chapter is required.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-4 — Validation · S-4.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Open **New entry**, leave **Fact** blank, submit | An error: "Name the secret so it can be tracked." — no save |
| 2 | Add a Fact but leave **Reveal chapter** unset, submit | An error: "Choose the chapter where this fact becomes known." — no save |

**Pass criteria:** both required-field errors show; nothing is saved on an invalid submit.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-5 — Edit an entry · S-4.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | On an entry card, click the **edit** (pencil) button | The dialog opens **pre-filled** with that entry's fact, reveal chapter, about, who-knows, and notes |
| 2 | Change the fact and remove a who-knows chip, click **Save changes** | The dialog closes, a success toast shows, and the card reflects the edits |

**Pass criteria:** edit pre-fills correctly and persists; no draft leaks from a previous create/edit.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-6 — Delete an entry (confirmed) · S-4.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | On an entry card, click the **delete** (trash) button | A **confirmation dialog** appears (destructive styling + a clear Cancel) — never a native browser alert |
| 2 | Click **Cancel** | Nothing is deleted |
| 3 | Click **delete** again, then confirm | The card disappears, a success toast shows; the entry is gone after refresh |

**Pass criteria:** deletion is always confirmed; cancel is safe; confirm removes the entry.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-7 — Ownership isolation (automated-backed)

Cross-story / cross-owner access (404) would require typing another scope's URL, which this checklist forbids, so it is locked by automated tests instead:

- `tests/Feature/Stories/RevealLedgerCrudTest.php` — owner CRUD; world-secret create; who_knows storage + normalization; required fact/reveal-chapter validation; reveal-chapter and "about" character must belong to this story; index lists only this story's entries; **404 on a foreign story**; **404 on a cross-story entry** (scoped binding) for update/delete; guest redirect to login.

**Manual evidence:** while signed in, no visible navigation exposes another story's reveal ledger.
**Pass criteria:** the RevealLedger suite is green; no navigation leaks cross-scope content.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## UX / Law-of-UX observations

- **Single primary action** (Hick's): one **New entry** (or **Go to Structure** when gated) per view. ✔
- **Confirm destructive actions:** delete uses `useConfirm`, not `window.confirm`. ✔
- **Four states:** empty (teaching, two variants), success (toast), error (inline validation); the list is the success state. ✔
- **Graceful degradation:** the "About" field degrades to a world-secret hint with no characters; creation is gated with no chapters. ✔
- **Touch targets:** dialog buttons and card actions meet ≥44px (`h-11` / `size-9` icon affordances). ✔
- **Tokens + dark parity:** all surfaces use semantic tokens (no hardcoded colors). ✔

## UX Critical Violation check

> **None found.** The reveal ledger is reached through visible navigation (story card → Reveal ledger tab). Create/edit happen in a dialog; deletes are confirmed. The cross-scope 404 isolation, which would require typing another scope's URL to demo, is validated by automated tests.

## Sign-off

- Overall: ☐ Pass ☐ Fail
- Critical/High defects: ____________________
- Tester signature / date: ____________________

## Related

- [../../api/reveal-ledger.md](../../api/reveal-ledger.md)
- [../../architecture/ARCHITECTURE.md](../../architecture/ARCHITECTURE.md) (E4.1) · [../../architecture/Diagrams/Authoring/Reveal_Ledger_Crud_Flow.md](../../architecture/Diagrams/Authoring/Reveal_Ledger_Crud_Flow.md)
- [S-9-lorebook.md](./S-9-lorebook.md) (the sibling world-fact CRUD) · [S-2.1-workspace-shell.md](./S-2.1-workspace-shell.md) (the shell this surface lives in)
