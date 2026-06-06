# Manual QA — E3.1: Lorebook CRUD

> **Domain:** `ui` · **Story:** S-3.1.1 (per-story lorebook entry CRUD)
> **Date:** 2026-06-06 (Asia/Jakarta) · **Tester:** _____________ · **Build:** E3.1
> **Rule:** every step uses a visible navigation action (link/button/tab). **No step types a URL to reach a page.** The lorebook is reached by opening a **story card** on the Workspace dashboard, then the **Lorebook** tab.

## Preconditions

- App running (`composer run dev`) on MariaDB; assets built (`pnpm build` or `pnpm dev`).
- Signed in with an account that owns **at least one story** (create one from the Workspace dashboard if needed).
- The story has **no chapters** (default today) — this is the expected reveal-gate state.

---

## TC-1 — Empty state teaches the next step · S-3.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Open a story, click the **Lorebook** tab | The Lorebook surface opens inside the workspace shell with a heading + a one-line world-facts description |
| 2 | Observe an empty story | A teaching empty state (book icon, "No lorebook entries yet", a description) with a single **New entry** primary action — never a blank screen |

**Pass criteria:** the empty state teaches the next step and offers exactly one primary action (Hick's Law).
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-2 — Create an entry · S-3.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Click **New entry** | A dialog opens: Title (optional), Keywords (chip input), Content, Minimum reveal chapter, and a "world facts only" reminder |
| 2 | Type a keyword and press **Enter** | The keyword becomes a removable chip; repeat to add a few |
| 3 | With **no chapters** in the story, look at the reveal field | It is **not** a dropdown — it shows a disabled hint: "Add chapters in Structure to gate when this entry is revealed." |
| 4 | Leave Title blank, add ≥1 keyword + content, click **Create entry** | The dialog closes, a success toast shows, and the entry appears as a card (title falls back to the first keyword) |

**Pass criteria:** the entry is created without a title; keywords render as chips; the reveal field degrades gracefully with no chapters.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-3 — Validation · S-3.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Open **New entry**, add content but **no keyword**, submit | An inline error: "Add at least one keyword so the entry can be matched at runtime." — no save |
| 2 | Add a keyword but **clear content**, submit | An inline error: "Lorebook content is required." — no save |

**Pass criteria:** both required-field errors show inline; nothing is saved on an invalid submit.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-4 — Edit an entry · S-3.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | On an entry card, click the **edit** (pencil) button | The dialog opens **pre-filled** with that entry's title, keywords, and content |
| 2 | Change the title and remove a keyword chip, click **Save changes** | The dialog closes, a success toast shows, and the card reflects the edits |

**Pass criteria:** edit pre-fills correctly and persists; no draft leaks from a previous create/edit.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-5 — Delete an entry (confirmed) · S-3.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | On an entry card, click the **delete** (trash) button | A **confirmation dialog** appears (destructive styling + a clear Cancel) — never a native browser alert |
| 2 | Click **Cancel** | Nothing is deleted |
| 3 | Click **delete** again, then confirm | The card disappears, a success toast shows; the entry is gone after refresh |

**Pass criteria:** deletion is always confirmed; cancel is safe; confirm removes the entry.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-6 — Ownership isolation (automated-backed)

Cross-story / cross-owner access (404) would require typing another scope's URL, which this checklist forbids, so it is locked by automated tests instead:

- `tests/Feature/Stories/LorebookCrudTest.php` — owner CRUD; required-keyword/content validation; reveal-chapter must belong to this story; index lists only this story's entries; **404 on a foreign story**; **404 on a cross-story entry** (scoped binding) for update/delete; guest redirect to login.

**Manual evidence:** while signed in, no visible navigation exposes another story's lorebook.
**Pass criteria:** the Lorebook suite is green; no navigation leaks cross-scope content.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## UX / Law-of-UX observations

- **Single primary action** (Hick's): one **New entry** button per view. ✔
- **Confirm destructive actions:** delete uses `useConfirm`, not `window.confirm`. ✔
- **Four states:** empty (teaching), success (toast), error (inline validation); the list is the success state. ✔
- **Graceful degradation:** the reveal-chapter field degrades to a hint when no chapters exist. ✔
- **Touch targets:** dialog buttons and card actions meet ≥44px (`h-11` / `size-9` icon affordances within reach). ✔
- **Tokens + dark parity:** all surfaces use semantic tokens (no hardcoded colors). ✔

## UX Critical Violation check

> **None found.** The lorebook is reached through visible navigation (story card → Lorebook tab). Create/edit happen in a dialog; deletes are confirmed. The cross-scope 404 isolation, which would require typing another scope's URL to demo, is validated by automated tests.

## Sign-off

- Overall: ☐ Pass ☐ Fail
- Critical/High defects: ____________________
- Tester signature / date: ____________________

## Related

- [../../api/lorebook.md](../../api/lorebook.md)
- [../../architecture/ARCHITECTURE.md](../../architecture/ARCHITECTURE.md) (E3.1) · [../../architecture/Diagrams/Authoring/Lorebook_Crud_Flow.md](../../architecture/Diagrams/Authoring/Lorebook_Crud_Flow.md)
- [S-2.1-workspace-shell.md](./S-2.1-workspace-shell.md) (the shell this surface lives in)
