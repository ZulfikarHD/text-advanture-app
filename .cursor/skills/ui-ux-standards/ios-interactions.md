# iOS Interaction Patterns (for web/Vue)

How to make DINE *behave* like iOS using shadcn-vue + reka-ui + Tailwind. These are faithful adaptations, not pixel clones — the app runs on desktop + tablet, so every pattern stays keyboard-accessible and responsive.

> Rule of thumb: **present, don't navigate.** On small/tablet widths, secondary tasks slide up as sheets over the current context instead of full page swaps. On desktop, the same content may be a centered dialog or an inline panel.

> **Implementation note:** style with **Tailwind** (utility-first); animate with **`motion-v`** (motion.dev) — see [design-system.md §5](design-system.md). The shadcn/reka primitives below (Sheet, Dialog, DropdownMenu) keep their built-in `data-[state]` animations; use `motion-v` for bespoke motion (collapsing titles, swipe, tap feedback, custom sheets).

---

## 1. Bottom sheet (the workhorse)

Use for: player input composer, authoring forms, item detail, spin edit, settings panels.

- Use shadcn `Sheet` with `side="bottom"`.
- `rounded-t-2xl`, a **grabber** handle at top (`mx-auto h-1.5 w-10 rounded-full bg-muted` ), translucent header (`bg-background/80 backdrop-blur-xl`).
- Dim, tappable backdrop; **swipe-down / drag-to-dismiss** affordance (drag the grabber). Always also provide a visible Close and `Esc`.
- Slide in over `--motion-slow` with `--ease-ios`.
- Pad bottom for the home indicator: `pb-[env(safe-area-inset-bottom)]`.
- **Desktop fallback:** render as a centered `Dialog` instead of a bottom sheet when viewport ≥ `md`.

```vue
<Sheet>
  <SheetTrigger as-child><Button>Edit</Button></SheetTrigger>
  <SheetContent side="bottom" class="rounded-t-2xl pb-[env(safe-area-inset-bottom)]">
    <div class="mx-auto mb-3 h-1.5 w-10 rounded-full bg-muted" aria-hidden="true" />
    <!-- header + content -->
  </SheetContent>
</Sheet>
```

---

## 2. Action sheet (discrete choices)

Use for: **inaction prompt** (Continue / Skip to next beat / Direct a character), confirm-destructive, "more" menus.

- A bottom sheet whose body is a **vertical stack of full-width options**, each a ≥ 44px row.
- Destructive option uses `text-destructive` (or a `destructive` button); a **separated Cancel** sits at the bottom.
- One tap = one decision. Keep to ≤ ~5 options (Hick's Law). For 2 inline choices, prefer a segmented control instead.

```vue
<SheetContent side="bottom" class="rounded-t-2xl">
  <div class="space-y-2">
    <Button variant="ghost" class="h-12 w-full justify-center">Continue</Button>
    <Button variant="ghost" class="h-12 w-full justify-center">Skip to next beat</Button>
    <Button variant="ghost" class="h-12 w-full justify-center">Direct a character</Button>
  </div>
  <Separator class="my-2" />
  <Button variant="secondary" class="h-12 w-full">Cancel</Button>
</SheetContent>
```

---

## 3. Segmented control

Use for: theme (Light/Dark/System), review gate mode, spin alternative tabs, any 2–4 mutually-exclusive options.

- A pill track (`rounded-full bg-muted p-1`) with equal-width segments; the **selected** segment gets `bg-background shadow-sm` and slides.
- Build on shadcn `Tabs` (add it via CLI if missing) styled as a segmented control, or a small custom component in `ui/`. Keep it keyboard-navigable (arrow keys, roving tabindex — reka-ui handles this).

```vue
<div class="inline-flex rounded-full bg-muted p-1">
  <button
    v-for="opt in options" :key="opt.value"
    class="h-9 rounded-full px-4 text-sm font-medium transition-colors duration-150"
    :class="model === opt.value ? 'bg-background text-foreground shadow-sm' : 'text-muted-foreground'"
    @click="model = opt.value"
  >{{ opt.label }}</button>
</div>
```

---

## 4. System toggle switch

Use for: boolean settings (registration on/off, debug gate, budget cap enabled).

- Add shadcn `switch` via CLI (not present yet). Style as the iOS pill toggle: `bg-primary` when on, `bg-muted` when off, with a sliding thumb.
- Min 44px hit area; label is clickable; reflects state for screen readers.

---

## 5. Grouped inset lists

Use for: settings, the **review gate** queue, **relationship viewer**, cost dashboard, any list of records.

- Group related rows in a `rounded-2xl border border-border bg-card` container; rows divided by `divide-y divide-border`.
- Each row: ≥ 44px, `px-4`, leading icon/title on the left, value/`chevron-right` on the right, whole row tappable.
- Group **header** above the card in `text-xs font-medium uppercase tracking-wide text-muted-foreground`.
- Use **Common Region** (the card boundary) to bind related items; separate groups with vertical space.

```vue
<p class="px-1 pb-2 text-xs font-medium uppercase tracking-wide text-muted-foreground">Pending review</p>
<div class="divide-y divide-border overflow-hidden rounded-2xl border border-border bg-card">
  <button v-for="item in items" :key="item.id" class="flex min-h-12 w-full items-center justify-between px-4 active:scale-[0.99] transition-transform">
    <span class="text-sm">{{ item.title }}</span>
    <ChevronRight class="size-4 text-muted-foreground" />
  </button>
</div>
```

---

## 6. Swipe actions on rows

Use for: list rows with a quick destructive/secondary action (e.g., reject a review item, delete a save).

- Reveal trailing actions on horizontal swipe (touch) — destructive in red on the trailing edge. Implement the drag with **`motion-v`** drag/pan gestures (`drag="x"` + constraints, snap back with a spring).
- **Always** mirror the action as a visible control (kebab/`dropdown-menu`) for mouse/keyboard; swipe is an enhancement, not the only path.
- Destructive swipe still routes through confirmation (action sheet) unless it's trivially undoable (offer Undo toast).

---

## 7. Collapsing large title

Use for: top of major scrollable screens (workspace, library, dashboard, reader).

- On load: a **large title** (`text-3xl font-semibold tracking-tight`) sits in the content.
- On scroll: it collapses into a compact, **translucent blurred** sticky bar (`sticky top-0 bg-background/80 backdrop-blur-xl border-b`) showing the title inline.
- Drive via scroll position / `IntersectionObserver` (or `@vueuse/core`); animate the title scale/opacity with a **`motion-v`** `<motion.*>` bound to scroll progress, gated by `useReducedMotion()`.

---

## 8. Tap feedback & momentum

- **Every** tappable: a **`motion-v`** `whilePress` scale (~0.97) over ~150ms — the closest web analog to haptic feedback (`<motion.button :while-press="{ scale: 0.97 }">`). For a primitive you can't wrap, a Tailwind `active:scale-[0.97] transition-transform` fallback is acceptable.
- Use native momentum scrolling; avoid hijacking scroll. `overscroll-contain` on sheets so the page behind doesn't scroll.
- **Tap nav item again → scroll its view to top** (iOS tab-bar behavior) where a primary scroll view exists.
- Pull-to-refresh only where a manual refresh is meaningful (e.g., dashboard); otherwise rely on Inertia/poll updates.

---

## 9. Navigation model (Jakob's Law)

- **Desktop:** persistent `sidebar` (already in the shell) with the three areas — authoring workspace, play, settings — active area clearly indicated.
- **Tablet/narrow:** sidebar collapses to a sheet drawer; primary actions remain reachable in the thumb zone (bottom).
- Provide a predictable **back** affordance; never trap the user. Breadcrumbs (`ui/breadcrumb`) for deep authoring trees.
- Follow platform conventions users already know — don't invent novel nav metaphors.

---

## 10. Feedback: toasts, loading, validation

- **Toasts:** transient confirmations/errors via `sonner` / `@/lib/flashToast`. Auto-dismiss; offer **Undo** for reversible destructive actions.
- **Loading (Doherty ~400ms rule):**
  - < ~400ms expected → no indicator (avoid flicker).
  - Longer → **skeleton** matching final layout (use `ui/skeleton`); `spinner` only for tiny inline/button-busy states.
  - **Streaming prose** → render partial text with a soft shimmer/caret; show "generating" affordance; never a frozen screen (Phase 7 requires first chunk < 3s, 0 frozen-screen incidents).
- **Validation:** inline, next to the field, on blur/submit; never a full-page error wall. Be lenient on input formatting (Postel's Law), strict on storage.
- **Empty states:** icon + one-line explanation + a single primary CTA toward the first action (e.g., "Create your first story"). Never an unexplained blank.
- **Error states:** say what went wrong + how to recover + a retry control. LLM/timeout failures must be recoverable without losing the session.

---

## 11. Modals vs sheets vs inline (decision)

| Situation | Use |
|---|---|
| Short task / form, mobile-tablet | **Bottom sheet** |
| Short task / form, desktop | **Centered Dialog** |
| 2–5 discrete choices | **Action sheet** (or segmented for 2–4 inline) |
| Confirm destructive | **Action sheet / Dialog** with `destructive` + Cancel |
| Contextual menu on a row | **dropdown-menu** (+ swipe on touch) |
| Persistent secondary content (desktop) | **inline panel / split**, not a modal |

Keep modality shallow — avoid sheet-over-sheet stacks. If you need a second step, make it a step *within* the same sheet (segmented/stepper), not a new layer.
