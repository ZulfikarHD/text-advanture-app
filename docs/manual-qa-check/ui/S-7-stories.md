# Manual QA — Sprint 7: Story CRUD & Workspace Dashboard

> **Domain:** `ui` · **Stories:** S-1.1.1 (create), S-1.1.2 (list, open, edit, delete)
> **Date:** 2026-06-05 (Asia/Jakarta) · **Tester:** _____________ · **Build:** Sprint 7
> **Rule:** every step uses a visible navigation action (link/button). **No step types a URL to reach a page.**

## Preconditions

- App running (`composer run dev`) on MariaDB; assets built (`pnpm build` or `pnpm dev`).
- Signed in with a known account (see [S-2-account-shell.md](./S-2-account-shell.md) preconditions if you need one).
- No stories exist for the current user (fresh account or delete all first).

---

## TC-1 — Empty workspace · S-1.1.2

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | From the sidebar, click **Workspace** | The dashboard opens; "Workspace" is highlighted in the sidebar |
| 2 | Observe the page content | An **EmptyState** surface displays: icon, "No stories yet", description text, and a single "New story" button |
| 3 | No other primary actions are visible | Only one CTA exists (Hick's Law compliance) |

**Pass criteria:** empty state renders with teaching copy and a single primary "New story" CTA.

---

## TC-2 — Create a story via dialog · S-1.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Click the **New story** button on the empty state | A **Dialog** opens (centered on desktop) with title "Create a new story" |
| 2 | Leave all fields empty, click **Create story** | Validation error appears for "title" — no native alert, inline error |
| 3 | Enter title "The Crystal Hollow", leave slug blank, add a description | All fields accept input; slug hint says "Leave blank to derive from title" |
| 4 | Click **Create story** | Dialog closes; dashboard shows the new story card; toast "Story created." appears |
| 5 | Verify the card | Title shows "The Crystal Hollow", slug shows `the-crystal-hollow` (derived), description is visible |

**Pass criteria:** dialog-based create flow; slug auto-derived from title; card appears in the grid with correct data; success toast.

---

## TC-3 — Create with explicit slug + collision · S-1.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Click **New story** in the page header | Dialog opens |
| 2 | Title "Another Story", slug `the-crystal-hollow`, submit | Error: "That slug is already used by another of your stories." (inline, no native alert) |
| 3 | Change slug to `another-story`, submit | Story created; card appears |

**Pass criteria:** explicit slug collision rejected with inline error; unique slug accepted.

---

## TC-4 — Auto-suffix on derived slug collision · S-1.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Click **New story** | Dialog opens |
| 2 | Title "The Crystal Hollow" (duplicate of TC-2), leave slug blank, submit | Story created with slug `the-crystal-hollow-2`; card appears |

**Pass criteria:** derived slug gets `-2` suffix; no validation error.

---

## TC-5 — Story grid layout · S-1.1.2

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | With 3+ stories, observe the dashboard | Cards in a responsive grid (1 col mobile, 2 cols sm, 3 cols lg) |
| 2 | Each card shows | Title, slug (monospace), description (or "No description" italic), relative timestamp, edit + delete icons |
| 3 | The "New story" button is in the page header (not in empty state) | Header layout: title + description left, button right |

**Pass criteria:** grid responsive; cards have all data fields; header CTA replaces empty-state CTA.

---

## TC-6 — Edit a story · S-1.1.2

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Click the **edit icon** (pencil) on a story card | Navigates to `/stories/{slug}/edit`; breadcrumbs show "Workspace > Edit story" |
| 2 | Workspace sidebar item stays highlighted | `isActive` matches `/stories/*` |
| 3 | The form is pre-filled with the story's current title, slug, and description | All fields populated correctly |
| 4 | Change the title to "Updated Title", click **Save changes** | Page stays on edit; toast "Story updated." appears; updated title shown |
| 5 | Try changing slug to one that collides with another story | Inline validation error for slug; no native alert |

**Pass criteria:** dedicated edit page; pre-filled form; update persists + toast; slug collision caught inline.

---

## TC-7 — Delete a story · S-1.1.2

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | From the dashboard, click the **delete icon** (trash) on a story card | A **confirmation dialog** opens (not a native `confirm()`) with the story title, warning about cascade, destructive styling |
| 2 | Click **Cancel** | Dialog closes; story still exists |
| 3 | Click the delete icon again, then **Delete story** | Story removed; redirected to dashboard; toast "Story deleted." appears; card is gone |

**Pass criteria:** useConfirm dialog (never native `confirm()`); cancel works; delete + cascade + redirect + toast.

---

## TC-8 — Cross-owner isolation · S-1.1.2

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Sign in as User A, create a story | Story visible in User A's workspace |
| 2 | Sign out, sign in as User B | User B's workspace shows empty state (or only their own stories) |
| 3 | User B manually navigates to `/stories/{user-a-slug}/edit` | **404** — foreign story is not found; existence not leaked |

**Pass criteria:** complete owner isolation; foreign stories resolve to 404.

---

## Signature

| Field | Value |
|-------|-------|
| All TCs passed | [ ] Yes / [ ] No |
| Issues found | |
| Tester signature | |
