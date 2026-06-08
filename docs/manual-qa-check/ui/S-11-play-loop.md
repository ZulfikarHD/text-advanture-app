# Manual QA — E5: Writing/Play loop (player moment)

> **Domain:** `ui` · **Stories:** S-5.4.1 (Play reader), S-5.1.1 (player moment input), S-4.2.1 (narrator turn), S-3.1.2 (continue past a beat)
> **Date:** 2026-06-08 (Asia/Jakarta) · **Tester:** _____________ · **Build:** Sprint 16
> **Rule:** every step uses a visible navigation action (link/button/tab). **No step types a URL to reach a page.** The Play page is reached by opening a **story card** → **Saves** tab → **Start session** (or **Open** an existing save).

## Preconditions

- App running (`composer run dev`) on MariaDB; assets built (`pnpm build` or `pnpm dev`).
- A `narrator_prose` model is configured (Settings → Model roles) and the account has a provider key (Settings → Provider) — otherwise the narrator turn surfaces a configuration toast (expected, see TC-6).
- Signed in with an account that owns a **play-ready** story (≥1 character incl. the player, ≥1 chapter/scene/beat). Create one via Characters + Structure tabs if needed.

---

## TC-1 — Enter play (no visible session/fork step) · S-5.4.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Open a play-ready story → **Saves** tab → **Start session** | You land on the Writing/Play page (codex rail + serif reading column + one turn control) — never a "configure session" screen |
| 2 | Observe the empty scene | A teaching empty state ("Your scene awaits") with a single **Begin the scene** primary action |
| 3 | Read the header | The book + save name are shown, with a back arrow to the book and a branches/saves affordance |

**Pass criteria:** entry lands directly in the workspace with one primary action (Hick's); orientation is clear.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-2 — Narrator opens, then hands off · S-4.2.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | Click **Begin the scene** | A busy label ("The narrator is writing…"); then narrated prose renders in the serif reading column |
| 2 | After it returns | If the handoff is `player_moment`, the composer appears; if `beat_complete`, a **Continue to the next beat** button appears |

**Pass criteria:** prose renders in a readable measure; exactly one next control shows, chosen by whose turn it is.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-3 — Write back at a player moment · S-5.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | At a player moment, observe focus | The composer textarea is **autofocused** so you can type immediately |
| 2 | Type your character's action/dialogue | A live counter shows `N/5000`; it stays muted while under the cap |
| 3 | Press **⌘/Ctrl + Enter** (or click **Send**) | Your input appends to the scrollback (styled as your contribution), and the turn hands back to the narrator (the **Continue** control returns) |
| 4 | Continue the narrator turn | The narrator continues **from your input** — it does not restart the beat |

**Pass criteria:** input is committed, rendered, and the narrator continues from it; the human supplies the behaviour (the engine never writes the player's action).
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-4 — Input validation + cap · S-5.1.1

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | At a player moment, leave the composer empty | **Send** is disabled; pressing ⌘/Ctrl+Enter does nothing — no empty submit |
| 2 | Paste more than 5000 characters | The counter turns destructive-colored and **Send** is disabled (mirrors the server `max:5000`) |

**Pass criteria:** empty and over-cap input cannot be submitted; the limit is visible before a round-trip.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-5 — Continue past a beat / end of story · S-3.1.2

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | When the narrator hands off `beat_complete`, click **Continue to the next beat** | The save advances to the next beat and hands back to the narrator (a success toast) |
| 2 | At the final beat, click **Continue** | An "end of the story" state appears with a clear way **back to the book** — no dead-end |

**Pass criteria:** beat boundaries advance correctly; the terminal state is reachable and offers a next step.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-6 — Recoverable failure (never a native alert) · S-4.2.2

| # | Action (navigation) | Expected |
|---|---------------------|----------|
| 1 | With **no narrator model** configured, click **Begin the scene** | An **error toast** ("No narrator model is configured yet…") — never a native browser alert; prior prose stays readable |
| 2 | Simulate an interrupted call (provider error) | An error toast ("The narrator was interrupted…"); the save is **unchanged** and you can retry without losing your place |

**Pass criteria:** failures surface as toasts, the save is untouched, and prior prose stays readable (recoverable).
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## TC-7 — Off-turn + isolation (automated-backed)

Acting off-turn and cross-owner access would require crafting/typing a request this checklist forbids, so they are locked by automated tests:

- `tests/Feature/Sessions/PlayLoopTest.php` — input records to the scene log; the turn hands back to the narrator; **off-turn input is rejected and logs nothing**; content is required; **a failed mid-turn write rolls the whole player moment back** (atomicity); the Play page exposes timeline/codex/flow.
- `tests/Feature/Narrator/NarratorTurnTest.php` — narrator turn success/failure + off-turn rejection.

**Manual evidence:** while signed in, no visible navigation exposes another owner's save or lets you act out of turn (the composer/continue/advance controls are mutually exclusive by `flow`).
**Pass criteria:** the suites are green; no navigation leaks cross-scope content or off-turn actions.
**Result:** ☐ Pass ☐ Fail — Notes: ____________________

---

## UX / Law-of-UX observations

- **Single primary action** (Hick's): exactly one turn control shows per state (narrate · write · continue · ended). ✔
- **Reading is sacred:** prose uses `font-serif`, `max-w-prose`, relaxed leading; chrome stays at the edges. ✔
- **Flow / Doherty:** the composer is autofocused on the player's turn; busy labels cover waits. ✔
- **Four states:** empty (teaching), loading (busy labels), error (toast), success (rendered prose). ✔
- **No native alerts:** all feedback via toasts + inline/disabled controls. ✔
- **Touch targets:** turn controls are `h-11` (≥44px). ✔
- **Tokens + dark parity:** all surfaces use semantic tokens (no hardcoded colors). ✔

## UX Critical Violation check

> **None found.** The Play page is reached through visible navigation (story card → Saves → Start/Open). Exactly one turn control shows per loop node; failures are recoverable toasts; off-turn/cross-scope safety is validated by automated tests.

## Sign-off

- Overall: ☐ Pass ☐ Fail
- Critical/High defects: ____________________
- Tester signature / date: ____________________

## Related

- [../../api/saves.md](../../api/saves.md)
- [../../features/session/S-5.1.1-player-input.md](../../features/session/S-5.1.1-player-input.md) · [../../testing/S-5.1.1-player-input-test-plan.md](../../testing/S-5.1.1-player-input-test-plan.md)
- [../../architecture/Diagrams/Engine/Player_Moment_Flow.md](../../architecture/Diagrams/Engine/Player_Moment_Flow.md) · [../../architecture/Diagrams/Engine/Narrator_Turn_Prose_Call.md](../../architecture/Diagrams/Engine/Narrator_Turn_Prose_Call.md)
- [S-2.1-workspace-shell.md](./S-2.1-workspace-shell.md) (the shell this surface lives in)
