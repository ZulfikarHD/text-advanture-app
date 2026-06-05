# Design System — "Quiet Editorial"

The design language for DINE. Built **on top of** the existing shadcn-vue neutral token architecture in `resources/css/app.css` — it extends, it does not replace. Everything here is expressed as **semantic tokens** so components never hard-code values and dark mode is automatic.

> The grayscale neutrals already in `app.css` are correct and stay. This system adds: **one brand accent (Ink Violet)**, a **serif reading font**, **iOS semantic colors**, **larger radii**, **soft elevation**, and **motion tokens**.

---

## 0. CSS & motion strategy

- **Tailwind-first.** Express styling with Tailwind utility classes wherever possible. Reach for a scoped `<style>` block / custom CSS **only** when Tailwind genuinely can't express it (e.g., a complex custom-component need with no utility) — and even then, drive it from the design tokens, never raw values.
- **Motion via `motion-v`** (motion.dev), not hand-written CSS transitions — see §5.
- Never inline hex/`rgb()` or raw palette utilities in a component; colors flow through the `app.css` tokens.

---

## 1. Color scheme

### Brand accent — "Ink Violet"

A restrained indigo/violet for interactive accents. It reads literary and premium, stays out of the prose column, and holds contrast in both themes. It maps onto the existing `--primary` / `--ring` tokens so all shadcn components inherit it.

**Recommended `:root` (light) overrides**

```css
--primary: hsl(243 75% 58%);          /* Ink Violet */
--primary-foreground: hsl(0 0% 100%);
--ring: hsl(243 75% 58%);
```

**Recommended `.dark` overrides**

```css
--primary: hsl(245 90% 72%);          /* lifted for dark contrast */
--primary-foreground: hsl(245 60% 12%);
--ring: hsl(245 90% 72%);
```

Keep `--secondary`, `--muted`, `--accent`, `--border`, `--card` neutrals as they are.

### iOS semantic colors (new tokens)

Add these for status/feedback. `--destructive` already exists (red) — reuse it for danger.

```css
/* :root */
--success: hsl(142 71% 40%);   --success-foreground: hsl(0 0% 100%);
--warning: hsl(38 92% 48%);    --warning-foreground: hsl(0 0% 100%);
--info:    hsl(211 92% 52%);   --info-foreground:    hsl(0 0% 100%);

/* .dark */
--success: hsl(142 64% 52%);   --warning: hsl(38 95% 58%);   --info: hsl(211 95% 62%);
```

Expose them in the `@theme inline` block as `--color-success`, `--color-warning`, `--color-info` so `bg-success`, `text-warning`, etc. work.

### Reading surface (optional warmth)

Prose may sit on a faintly warm "paper" rather than pure white to reduce glare during long reads:

```css
/* :root */ --reading: hsl(40 30% 98.5%);  --reading-foreground: hsl(30 8% 15%);
/* .dark  */ --reading: hsl(30 6% 9%);      --reading-foreground: hsl(40 12% 88%);
```

Use `--reading-measure: 66ch;` for the prose column width. If you skip the warm paper, prose simply uses `background`/`foreground` — but always keep the measure + serif + leading.

### Usage rules

- Components reference **tokens only**: `bg-primary text-primary-foreground`, `text-muted-foreground`, `bg-card`, `border-border`, `bg-destructive`, `bg-success`…
- **Never** put raw palette utilities (`bg-zinc-700`, `text-slate-500`) or hex/`rgb()` in a component.
- New color need? Add a token in `app.css` (both `:root` and `.dark`) + the `@theme inline` map, then use the semantic class.

---

## 2. Typography

Two families. UI stays as-is; reading gets a serif.

| Role | Token | Stack |
|---|---|---|
| UI / chrome | `--font-sans` (existing) | Instrument Sans → system sans |
| Reading / prose | `--font-serif` (**add**) | `'Newsreader', 'Source Serif 4', Georgia, 'Times New Roman', serif` |

Add to `app.css` `@theme inline` and load `Newsreader` (variable, optical sizing) via the existing font pipeline. Apply prose with `font-serif`.

**Reading scale (the prose column):**

- Size: `text-lg` (18px) base, scalable; offer a font-scale control where reading is primary.
- Leading: `leading-[1.75]`.
- Measure: `max-w-prose` or `[max-width:var(--reading-measure)]` (~66ch). Never full-bleed body text.
- Paragraph rhythm: space paragraphs (`space-y-5`), don't indent-and-cram.

**UI type scale (sans):**

| Use | Classes |
|---|---|
| Page title (large title) | `text-3xl font-semibold tracking-tight` |
| Section heading | `text-lg font-semibold` |
| Body | `text-sm` |
| Secondary / meta | `text-sm text-muted-foreground` |
| Caption / label | `text-xs font-medium text-muted-foreground` |

Numerals in dashboards/cost views: `tabular-nums` for alignment.

---

## 3. Spacing, radius & layout

- **4px grid** (Tailwind default). Use the scale; never arbitrary one-offs like `mt-[13px]`.
- **Radius** (iOS-soft): bump the base token.

```css
--radius: 0.75rem;   /* was 0.5rem */
```

| Element | Radius |
|---|---|
| Cards, sheets, grouped lists | `rounded-2xl` |
| Buttons, inputs, selects | `rounded-xl` |
| Pills, badges, segmented controls, avatars | `rounded-full` |
| Sheet top edge (bottom sheet) | `rounded-t-2xl` |

- **Content width:** app content `max-w-5xl` to `max-w-6xl` centered; prose column `~66ch`.
- **Section gaps:** `gap-4`/`space-y-6` between blocks; `p-4`–`p-6` card padding.
- **Safe areas:** sticky bars/sheets pad for mobile safe areas: `pb-[env(safe-area-inset-bottom)]`.

---

## 4. Elevation

iOS elevation is *soft and layered*, not hard drop-shadows. Lean on translucency for floating chrome.

| Layer | Recipe |
|---|---|
| Resting card | `bg-card border border-border` (flat) or `shadow-xs` |
| Raised (hover/active card) | `shadow-sm` |
| Popover / dropdown / dialog | `shadow-lg` |
| Bottom sheet | `shadow-2xl` + `rounded-t-2xl` |
| **Floating bars** (top nav, sheet headers) | `bg-background/80 backdrop-blur-xl border-b border-border` |

Translucent + blurred bars are the iOS signature — prefer them over opaque bars for sticky headers and tab bars.

---

## 5. Motion — use `motion-v` (motion.dev)

**Animation and transition are done with `motion-v`** (Motion for Vue, installed), *not* hand-written CSS transitions/keyframes. Use `<motion.*>` components, `AnimatePresence` for enter/exit, gesture props (`whileHover` / `whilePress`), spring transitions, and `useReducedMotion()`. Consult the motion.dev Vue docs for exact prop names.

> Exception: the shadcn/reka-ui primitives (Sheet, Dialog, DropdownMenu…) ship their own `data-[state]` CSS animations via `tw-animate-css` — **leave those as-is**. Reach for `motion-v` for *your own* surfaces, gestures, and bespoke transitions, and use `tw-animate-css` only for a pure-CSS keyframe (e.g., a streaming-prose shimmer) where pulling in JS motion would be overkill.

### Motion tokens (shared values)

Keep timing/easing consistent by reusing these values inside `motion-v` `transition` configs (mirror them as CSS vars in `app.css` for the few CSS-only cases).

| Token | Value | Use |
|---|---|---|
| `motion-fast` | 150ms | taps, toggles, hovers |
| `motion-base` | 250ms | most enter/exit, fades |
| `motion-slow` | 350ms | sheets, large surfaces |
| iOS spring | `{ type: 'spring', stiffness: 300, damping: 30 }` | sheets, drags, springy moves |
| iOS ease | `cubic-bezier(0.32, 0.72, 0, 1)` | timed large moves |
| ease-out | `cubic-bezier(0.16, 1, 0.3, 1)` | entrances |

### Defaults

```vue
<script setup lang="ts">
import { motion, AnimatePresence, useReducedMotion } from 'motion-v';
const reduced = useReducedMotion();
const spring = { type: 'spring', stiffness: 300, damping: 30 };
</script>

<template>
  <!-- Tap feedback (the web analog of haptics) -->
  <motion.button :while-press="{ scale: 0.97 }" :transition="{ duration: 0.15 }">Save</motion.button>

  <!-- Enter / exit -->
  <AnimatePresence>
    <motion.div
      v-if="open"
      :initial="{ opacity: 0, y: 16 }"
      :animate="{ opacity: 1, y: 0 }"
      :exit="{ opacity: 0, y: 16 }"
      :transition="reduced ? { duration: 0 } : spring"
    />
  </AnimatePresence>
</template>
```

- Tap feedback: `whilePress` (scale ~0.97), ~150ms.
- Sheets/dialogs you build yourself: spring or `motion-slow` + iOS ease.
- Fades & state changes: `motion-base` + ease-out.
- Layout shifts: prefer `motion-v` `layout` animations over manual position juggling.

### Reduced motion — mandatory

- In `motion-v`: gate every non-trivial animation with `useReducedMotion()` (set `transition` to `{ duration: 0 }` or skip transforms when reduced).
- Keep the CSS safety net for primitives / CSS-only animations:

```css
@media (prefers-reduced-motion: reduce) {
  *, *::before, *::after {
    animation-duration: 0.01ms !important;
    transition-duration: 0.01ms !important;
  }
}
```

---

## 6. Iconography

- **lucide** only (project default), `size-5` (20px) in controls, `size-4` (16px) inline.
- Icons are decorative unless labelled; pair with text or `aria-label`.
- Match stroke to text color via `currentColor`; never recolor with raw palette.

---

## 7. Where this lives

- All tokens: `resources/css/app.css` (`:root`, `.dark`, and the `@theme inline` map).
- Fonts: existing pipeline (`app.css` `@theme` + font load).
- Class merge helper: `cn()` from `@/lib/utils`.
- Theme switching: `useAppearance()` composable (light / dark / system) — already wired.

> When asked to "apply the design", edit `app.css` tokens (both themes + the `@theme inline` map) — never sprinkle raw values across components.
