# Laws of UX — Applied to DINE

The psychological principles (Jon Yablonski, lawsofux.com) that justify every design decision. **Cite the law** when you make or defend a choice. This file gives each law a one-line definition, a detection cue, and the DINE-specific application.

> If a `laws-of-ux-review` audit is requested, that skill drives the *audit*; this file is the *building* reference so what you ship already passes.

---

## Cognitive load & memory

**Miller's Law** — people hold ~7±2 items in working memory.
- *Cue:* a form/list dumping 12+ fields or stats at once.
- *DINE:* chunk authoring forms into sections/steps of ≤ ~7 fields (character creation, story config). Group the relationship viewer's axes into labelled clusters. Cost dashboard: group metrics, don't list 20 raw numbers.

**Cognitive Load** — total mental effort to use the interface; minimize the extraneous kind.
- *Cue:* the reading screen carrying buttons, meters, and chrome.
- *DINE:* the **prose column is undecorated** — controls live in the collapsing top bar or a sheet. Defer engine internals (cost, deltas) to dedicated surfaces.

**Tesler's Law (Conservation of Complexity)** — irreducible complexity lands somewhere; the system should absorb it, not the user.
- *DINE:* the engine is dense (isolation, deltas, registers). Absorb it with **seeded defaults, archetypes, AI/hybrid creation, and sensible auto-derivation** so the author isn't forced to hand-fill everything.

**Chunking** — group content into digestible units.
- *DINE:* grouped inset lists; prose paragraphed with rhythm; review items grouped by `producer_type`.

---

## Decision speed

**Hick's Law** — decision time grows with the number/complexity of choices.
- *Cue:* many equal-weight buttons; a screen asking for several decisions at once.
- *DINE:* **one primary action per view.** Inaction prompt = exactly 3 options in an action sheet. Review item = accept / edit / reject, nothing more. Demote everything secondary to `ghost`/overflow.

**Choice Overload** — too many options causes deferral/abandonment.
- *DINE:* progressive disclosure — advanced prompting/model-tier settings behind "Advanced", not on the main form.

**Occam's Razor / Pareto** — simplest sufficient design; the vital few actions carry most value.
- *DINE:* surface the 20% of actions (advance, input, spin, accept) that drive 80% of use; bury the rest.

---

## Familiarity & mental models

**Jakob's Law** — users expect your app to work like the others they know.
- *DINE:* honor **iOS conventions** (sheets, action sheets, segmented controls, switches, back affordance) and standard web/Inertia nav. Don't invent novel metaphors for common tasks. → [ios-interactions.md](ios-interactions.md)

**Mental Model** — match the user's existing understanding of how things work.
- *DINE:* "spin" = regenerate/alternative take (browse, keep one, discard rest) — present it like choosing between drafts, because that's the user's model.

**Postel's Law** — be liberal in what you accept, conservative in what you do.
- *DINE:* player input accepts free prose; delivery is **sourced** (prose → tone tag → ask-when-ambiguous), never silently decoded. Forms accept loose formatting, normalize on store.

---

## Visual perception (Gestalt)

**Law of Proximity** — near things are perceived as related.
- *DINE:* tight spacing within a record; generous space between groups (relationship axes, review groups).

**Common Region** — a shared boundary groups elements.
- *DINE:* the `rounded-2xl border bg-card` container binds a list/group; review items of one `producer_type` share one region.

**Law of Similarity / Uniform Connectedness** — like-styled / connected items read as a set.
- *DINE:* consistent row styling across all grouped lists; status uses one consistent pill grammar (`success`/`warning`/`info`/`destructive`).

**Von Restorff (Isolation Effect)** — the distinct item is remembered/noticed.
- *DINE:* the **selected** spin alternative pops (primary ring/fill); **safety-critical** review producers (`beat_record`, `nudge_compile`, `card_compile`) carry a distinct badge so they're never lost in a batch.

**Law of Prägnanz** — people read ambiguous images in their simplest form.
- *DINE:* simple, regular shapes; avoid decorative noise that competes with prose.

---

## Interaction mechanics

**Fitts's Law** — time-to-target depends on size and distance.
- *Cue:* tap targets < 44px, primary actions far from the thumb on tablet.
- *DINE:* **≥ 44px** targets; primary/confirm actions in the thumb zone (bottom) on tablet; large hit areas on list rows.

**Doherty Threshold** — keep system response < 400ms to maintain flow; mask longer waits.
- *DINE:* < ~400ms → no spinner; longer → skeleton; **streaming prose** keeps the user engaged during a ~10+ call beat (first chunk < 3s target). Never freeze.

**Selective Attention** — users filter out the irrelevant (and banner-blind the noisy).
- *DINE:* don't bury the active task; keep the reader focused on prose; surface ambiguity prompts *only* when genuinely unresolved.

---

## Emotion & memory

**Aesthetic-Usability Effect** — attractive interfaces are perceived as more usable (and forgive minor flaws).
- *DINE:* the calm editorial polish + soft iOS motion builds trust — *in both themes* (parity is required).

**Peak-End Rule** — people judge an experience by its peak and its end.
- *DINE:* invest in the peaks (a beautifully rendered beat, a satisfying "Keep this take") and the ends (clean save/resume, graceful failure recovery, an Undo toast).

**Goal-Gradient Effect** — motivation rises closer to the goal; show progress.
- *DINE:* show beat/scene progress in the reader; step progress in multi-step authoring forms; budget progress on the cost surface.

**Flow** — the state of immersed, undistracted engagement.
- *DINE:* protect reading flow above all — minimal chrome, no surprise modals mid-prose, smooth streaming, momentum scroll.

**Serial Position Effect** — first and last items are best remembered.
- *DINE:* put the most important action first/last in action sheets; in the relationship history, anchor the newest change clearly.

**Zeigarnik Effect** — unfinished tasks nag at memory.
- *DINE:* surface pending review items and unsaved/in-progress sessions as gentle, dismissible reminders — not guilt-tripping badges.

---

## Quick self-audit (run before finishing a surface)

```
- [ ] Hick's: one primary action; secondary actions demoted
- [ ] Fitts's: all targets ≥ 44px; primary in reach
- [ ] Miller's: ≤ ~7 fields/items per group or step
- [ ] Doherty: skeletons for >400ms; streaming never freezes
- [ ] Jakob's: iOS/web conventions, no novel metaphors
- [ ] Common Region + Proximity: related items grouped, groups spaced
- [ ] Von Restorff: selected / safety-critical items stand out
- [ ] Aesthetic-Usability: polished + dark-mode parity
- [ ] Flow: reading column stays calm and undecorated
- [ ] Peak-End: peaks delighted, failures/exits graceful
```
