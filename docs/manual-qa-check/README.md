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

(`{domain}` = `npc-behaviour` | `directing` | `narrator` | `session` | `review-gate` | `ui` — this engine has no role-based `*-side/` split.)

## What to capture

- The exact UI path taken (navigate, **no direct-URL** jumps).
- Screenshots / prose excerpts as evidence.
- Pass/fail per acceptance criterion from the feature doc.
- Any leak observed (mark as a **safety** defect, highest priority).
