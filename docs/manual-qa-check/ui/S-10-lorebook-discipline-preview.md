# Manual QA — E3.1: Lorebook discipline + keyword preview

> **Domain:** `ui` · **Stories:** S-3.1.2 (world-fact discipline) · S-3.2.1 (keyword match preview)
> **Date:** 2026-06-06 (Asia/Jakarta) · **Tester:** _____________ · **Build:** E3.1 (Sprint 10)
> **Rule:** every step uses a visible navigation action (link/button/tab). **No step types a URL to reach a page.** The lorebook is reached by opening a **story card** on the Workspace dashboard, then the **Lorebook** tab.

## Preconditions

- App running (`composer run dev`) on MariaDB; assets built (`pnpm build` or `pnpm dev`).
- Signed in with an account that owns **at least one story** with **≥1 lorebook entry** (create one first — see S-9-lorebook).
- For the reveal-clamp step (TC-5), the story should have **at least two chapters** (e.g. Chapter 1 and Chapter 3).

---

## TC-1 — Interiority is soft-gated on create · S-3.1.2

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Click **New entry**, add a keyword, and set content to `She secretly loves the archivist and would never say so.` | — |
| 2 | Click **Create entry** | The entry is **not** saved. A distinct **warning** panel (amber, not the red error alert) appears: "This looks like character interiority", naming the flagged phrase, with two actions |
| 3 | Read the panel actions | A **Go to character cards** link + a **Save as world fact anyway** button |

**Pass criteria:** interiority content is blocked by default; the warning is visually distinct from a destructive error and steers the author to the character cards.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-2 — Acknowledge override saves the entry · S-3.1.2

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | From the TC-1 warning, click **Save as world fact anyway** | The dialog closes, a success toast shows, and the entry appears as a card |

**Pass criteria:** the soft gate is overridable — a deliberate acknowledgement saves the entry (a false positive can never lock the author out).
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-3 — A clean world fact (with an emotive word) is not a false positive · S-3.1.2

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Open **New entry**, add a keyword, set content to `The suppressor gloves feel cold and dampen Aether resonance.` | — |
| 2 | Click **Create entry** | The entry saves immediately — **no** warning panel (the emotive word "feel" has no character subject, so it reads as a world fact) |

**Pass criteria:** the discipline is precise — it does not flag a world fact that merely contains an emotive word.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-4 — Keyword match preview: triggered vs no match · S-3.2.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | With entries present, click **Test keywords** in the header | A **Test keywords** dialog opens: a Sample text box + an optional "Previewed chapter" field + a **Run preview** button |
| 2 | Paste text that contains a keyword from one of your entries, click **Run preview** | A brief skeleton, then a **Triggered (n)** list — each matched entry with its matched keyword(s) as badges |
| 3 | Replace the text with something matching no keyword, **Run preview** | An empty state: "No entries match this text. Adjust the keywords or the sample to tune what triggers." |

**Pass criteria:** matching entries are listed with their matched keywords; non-matching text yields a clear empty state.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-5 — Reveal-chapter clamp in preview · S-3.2.1

> Requires an entry whose **Minimum reveal chapter** is set to a later chapter (e.g. Chapter 3) and whose keyword you can match.

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Open **Test keywords**, paste text matching that entry's keyword | — |
| 2 | Set **Previewed chapter** to an **earlier** chapter (e.g. Chapter 1), **Run preview** | The entry appears under **Withheld by reveal chapter** with a **Lock** badge ("Chapter 3"), not under Triggered |
| 3 | Set **Previewed chapter** to the reveal chapter (or later), **Run preview** | The entry now appears under **Triggered** |
| 4 | Set **Previewed chapter** back to **Any chapter (no reveal clamp)**, **Run preview** | The entry appears under **Triggered** (no clamp applied) |

**Pass criteria:** the preview's reveal clamp matches the documented runtime rule — gated entries are withheld before their reveal chapter and triggered at/after it.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-6 — Validation + isolation (automated-backed)

Cross-story / cross-owner access (404) and JSON validation would require typing another scope's URL or crafting requests, which this checklist forbids, so they are locked by automated tests instead:

- `tests/Feature/Stories/LorebookDisciplineTest.php` — interiority rejected without acknowledgement (nothing stored); stored when acknowledged; clean fact stored without ack; emotive-word false-positive guard; same on update.
- `tests/Feature/Stories/LorebookPreviewTest.php` — triggered/excluded entries; reveal clamp withheld vs triggered; `sample_text` required (JSON 422); `chapter_id` must belong to this story; **404 on a foreign story**; guest redirected to login.
- `tests/Unit/Services/InteriorityHeuristicTest.php` + `LorebookKeywordMatcherTest.php` — the heuristic and matcher in isolation.

**Pass criteria:** the discipline + preview suites are green; no navigation leaks cross-scope content.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## UX / Law-of-UX observations

- **Single primary action** (Hick's): **New entry** stays the only primary in the header; **Test keywords** is a secondary/outline action. ✔
- **Warning ≠ error:** interiority uses a distinct `warning`-token panel, not the destructive red alert. ✔
- **No native dialogs:** the warning + preview are in-app surfaces; no `window.alert`/`confirm`. ✔
- **Four states (preview):** loading (skeleton), empty ("no entries match"), success (triggered/withheld lists), error (inline validation). ✔
- **Graceful degradation:** the previewed-chapter field degrades to a hint when no chapters exist. ✔
- **Touch targets:** dialog actions meet ≥44px (`h-11`). ✔
- **Tokens + dark parity:** all surfaces use semantic tokens (the new `warning` token included). ✔

## UX Critical Violation check

> **None found.** Both surfaces are reached through visible navigation (story card → Lorebook tab → New entry / Test keywords). The discipline gate is overridable; the preview is read-only. Cross-scope 404 isolation and JSON validation, which would require typing URLs or crafting requests, are validated by automated tests.

## Sign-off

- Overall: ☐ Pass ☐ Fail
- Critical/High defects: ____________________
- Tester signature / date: ____________________

## Related

- [../../api/lorebook.md](../../api/lorebook.md)
- [../../architecture/ARCHITECTURE.md](../../architecture/ARCHITECTURE.md) (Sprint 10) · [../../architecture/Diagrams/Authoring/Lorebook_Crud_Flow.md](../../architecture/Diagrams/Authoring/Lorebook_Crud_Flow.md)
- [S-9-lorebook.md](./S-9-lorebook.md) (the CRUD foundation these build on)
