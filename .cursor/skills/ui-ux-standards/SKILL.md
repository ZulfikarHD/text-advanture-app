---
name: ui-ux-standards
description: The single source of truth for DINE's UI/UX. Applies the Laws of UX and an iOS-grade interaction model to every screen, component, and state. Activates when building or editing any Vue page or component, designing layouts, choosing colors/spacing/typography/motion, building forms, lists, modals, sheets, navigation, or empty/loading/error/success states, or when the user mentions UI, UX, design, look-and-feel, theme, polish, or consistency.
license: MIT
metadata:
  author: Zulfikar Hidayatullah
---

# DINE UI/UX Standards — "Quiet Editorial"

The **Directed Interactive Novel Engine (DINE)** is two products in one skin: a *calm, literary reading surface* for the player and a *precise authoring/review workbench* for the author. The design language is **Quiet Editorial**: reading is sacred and undecorated; the chrome around it is iOS-grade — soft, translucent, spring-driven, and predictable.

Two non-negotiable goals govern every decision:

1. **Follow the Laws of UX.** Every layout/interaction choice must be defensible by a named law (Hick's, Fitts's, Jakob's, Miller's, Doherty, Aesthetic-Usability, Peak-End, etc.). No vibes-only design. → [laws-of-ux.md](laws-of-ux.md)
2. **Behave like iOS.** Bottom sheets, action sheets, segmented controls, system toggles, large-title-to-inline nav, momentum, swipe affordances, and tap feedback — adapted faithfully to web/Vue. → [ios-interactions.md](ios-interactions.md)

## When to apply

Activate for **any** front-end work in `resources/js/**`: new pages/components, restyling, layout, forms, lists, modals/sheets, navigation, motion, theming, or any of the four required states (loading / empty / error / success). If you are writing Tailwind classes in a `.vue` file, this skill is in force.

## The locked stack (do not fight it)

- **Vue 3 + Inertia v3**, Wayfinder routes (**never** Ziggy `route()`).
- **Tailwind 4** with CSS-variable tokens; dark mode via the `.dark` class. **Tailwind-first**: only drop to a scoped `<style>` / custom CSS when a custom component genuinely can't be expressed in utilities (and then drive it from tokens).
- **Animation & transitions via `motion-v`** (Motion for Vue, motion.dev) — `<motion.*>`, `AnimatePresence`, gesture props, springs, `useReducedMotion()`. Not hand-written CSS transitions. (shadcn/reka primitives keep their built-in `tw-animate-css` `data-[state]` animations.)
- **shadcn-vue** (`new-york-v4`, neutral base) on **reka-ui** primitives, **lucide** icons.
- Class merging via `cn()` from `@/lib/utils`. Toaster via `sonner` / `@/lib/flashToast`.

**Reuse before you build.** Available UI primitives today: `button, card, dialog, sheet, dropdown-menu, select, checkbox, input, input-otp, label, alert, badge, avatar, breadcrumb, navigation-menu, sidebar, separator, skeleton, spinner, tooltip, collapsible, sonner`. Need a `switch`, `tabs`/segmented control, `radio-group`, or `popover`? Add it with the shadcn-vue CLP and match the existing `ui/` folder convention — do not hand-roll a one-off.

## The 10 standards (always true)

These are the consistency floor. The reference files give the *how*; this list is the *what*.

1. **Token-only styling.** Use semantic tokens (`bg-background`, `text-foreground`, `text-muted-foreground`, `bg-primary`, `border-border`, `ring-ring`, `bg-card`…). **Never** hard-code hex/`rgb()` or raw palette utilities like `bg-zinc-800` in components. New colors go through `app.css` tokens. → [design-system.md](design-system.md)
2. **Dark-mode parity.** Every surface ships light + dark with sufficient contrast (≥ 4.5:1 body text). If you can't see it in both, it isn't done.
3. **44px touch targets.** Interactive controls are **≥ 44×44px** (`h-11`/`min-h-11`, `size-11` for icon buttons). Fitts's Law is law, not a suggestion.
4. **One primary action per view.** A single filled `primary` button; everything else is `secondary`/`outline`/`ghost`. Hick's Law — don't present a wall of equal buttons.
5. **iOS surfaces for overflow.** Secondary content, forms, and detail go in a **bottom Sheet** (mobile/tablet) or centered **Dialog** (desktop). Discrete choices use an **action sheet**. Never cram a second job onto the reading screen.
6. **Four states, every time.** Every async surface defines **loading, empty, error, success**. Loading uses **skeletons** (not spinners) when the wait can exceed the Doherty threshold (~400ms). Empty states *teach the next step*; they are never a blank screen.
7. **Spring motion via `motion-v`, reduced-motion safe.** Animate with `motion-v` (springs/iOS easing); `whilePress` for tap feedback. Always gate with `useReducedMotion()` + the CSS `prefers-reduced-motion` net.
8. **Confirm destructive actions.** Deletes/discards go through an action sheet/dialog with the destructive option in **`destructive`** color and a clear Cancel. Never delete on a single tap.
9. **Reading is sacred.** Prose uses the **serif reading font**, a constrained measure (`max-w-prose`/~66ch), generous leading (`leading-[1.75]`), and scalable size. No UI chrome, accent colors, or motion intrudes on the prose column.
10. **Keyboard + a11y.** Visible focus rings (`focus-visible:ring-ring`), logical tab order, labelled controls, semantic landmarks, single root element per component. Tablet + desktop responsive is mandatory (DoD).

## Build workflow

```
UI Task Progress:
- [ ] 1. Identify the surface type (reading / list / form / review / nav / modal)
- [ ] 2. Pick the iOS pattern for it (ios-interactions.md)
- [ ] 3. Pull the right tokens + components (design-system.md); reuse existing ui/
- [ ] 4. Lay out the happy path with one primary action (Hick's)
- [ ] 5. Add the four states: loading (skeleton) / empty / error / success
- [ ] 6. Wire motion + tap feedback; guard prefers-reduced-motion
- [ ] 7. Verify: 44px targets, dark parity, focus rings, measure (if prose)
- [ ] 8. Self-audit against laws-of-ux.md for the surface
- [ ] 9. pnpm lint clean; Wayfinder types regenerate
```

## Surface playbook (DINE-specific)

Match the surface to its pattern and governing laws. Detail in the reference files.

| Surface | iOS pattern | Lead laws |
|---|---|---|
| **Play / prose reader** (Phase 7 E1) | Translucent collapsing top bar; serif measure; streaming shimmer; momentum scroll | Flow, Aesthetic-Usability, Doherty (first chunk < 3s) |
| **Player input + delivery** (E2.1) | Bottom sheet composer; optional tone tag as segmented control; ambiguity = action sheet | Postel's Law, Selective Attention |
| **Inaction prompt** (E2.2) | **Action sheet**: Continue / Skip to next beat / Direct a character | Hick's, Jakob's |
| **Spin / regenerate** (E3) | Segmented/paged alternatives; "Keep" = primary; inline edit in a sheet | Von Restorff (selected), Serial Position |
| **Review gate** (E4) | Grouped inset lists by `producer_type`; accept/edit/reject; safety-critical badged | Common Region, Hick's, Von Restorff |
| **Relationship viewer** (E5) | Grouped lists; per-axis history timeline; each delta shows its trigger | Law of Proximity, Miller's, Serial Position |
| **Cost/latency dashboard** (E6) | Grouped stat lists; Rupiah display; status pills | Miller's, Chunking |
| **Authoring forms** (Phases 2–4) | Sectioned/stepped sheets; ≤ ~7 fields per step; progress | Miller's, Goal-Gradient, Tesler's |
| **App shell / nav** (Phase 1 E3) | Sidebar (desktop) / sheet drawer (tablet); active area indicated; theme = segmented control | Jakob's, Aesthetic-Usability |

## Project standards to honor in the UI

- **Currency**: provider cost rendered in **Rupiah (Rp)** for display (stored as provider value).
- **Time**: render in **Asia/Jakarta (WIB)**; store UTC.
- **Copy**: UI in English; concise, action-oriented labels.
- **Privacy in UI**: never render a character's private `true_state` in any player/review view — surface/hedged-attribution only (a hard requirement, not a style choice).

## Anti-patterns (reject these)

- ❌ Hard-coded colors / raw palette utilities in components (breaks theming + dark parity).
- ❌ Spinners for waits > 400ms instead of skeletons; flicker on instant waits.
- ❌ Multiple competing primary buttons; dense walls of equal-weight actions.
- ❌ Tap targets < 44px; controls with no visible focus ring.
- ❌ Hand-rolled modal/dropdown/toggle when a `ui/` primitive exists.
- ❌ Hand-written CSS keyframes/transitions for bespoke motion instead of `motion-v`; custom CSS where a Tailwind utility exists.
- ❌ Motion with no `useReducedMotion()` / `prefers-reduced-motion` guard; jarring full-page transitions.
- ❌ Accent color, borders, or motion bleeding into the prose reading column.
- ❌ Deleting/discarding without confirmation.
- ❌ Inventing new spacing/radius scales instead of the token grid.

## Reference files

- **[design-system.md](design-system.md)** — palette & tokens, typography (UI + serif reading), spacing/radius, elevation, motion tokens, the recommended `app.css` values.
- **[ios-interactions.md](ios-interactions.md)** — iOS behavior patterns (sheets, action sheets, segmented controls, switches, grouped lists, swipe actions, collapsing titles, tap feedback) mapped to shadcn-vue + Tailwind.
- **[laws-of-ux.md](laws-of-ux.md)** — the Laws of UX with detection cues and DINE-surface fixes.
