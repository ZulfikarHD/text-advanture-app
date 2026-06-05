# Manual QA Check

Evidence from manual QA passes, one file per story, grouped by subsystem domain.

## Naming

```
manual-qa-check/{domain}/{CODE}-{slug}.md   # e.g. narrator/O1-narrator-loop.md
```

## Index

| Check | Stories |
|-------|---------|
| [ui/S-1-foundation-auth.md](./ui/S-1-foundation-auth.md) | S-1.1.1/2/3, S-2.1.1/2/4 — scaffold, standards, sign-in/out, route protection |
| [ui/S-2-account-shell.md](./ui/S-2-account-shell.md) | S-2.2.1/2/3, S-2.1.3, S-3.1.1 — profile/password/delete, ownership, registration toggle, app shell |
| [ui/S-3-theming-states-schema.md](./ui/S-3-theming-states-schema.md) | S-3.1.2/3, S-3.2.1/2, S-4.1.1 — theming, accessible shell, four-state components, destructive confirm, authoring schema |

(`{domain}` = `npc-behaviour` | `directing` | `narrator` | `session` | `review-gate` | `ui` — this engine has no role-based `*-side/` split.)

## What to capture

- The exact UI path taken (navigate, **no direct-URL** jumps).
- Screenshots / prose excerpts as evidence.
- Pass/fail per acceptance criterion from the feature doc.
- Any leak observed (mark as a **safety** defect, highest priority).
