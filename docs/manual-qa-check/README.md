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
| [ui/S-4-provider-key.md](./ui/S-4-provider-key.md) | S-5.1.1/3, S-4.1.2, S-4.2.1/2 — provider key add/replace/remove + masked display; save realm, global libraries & isolation/append-only invariants (automated-backed) |
| [ui/S-5-llm-client.md](./ui/S-5-llm-client.md) | S-5.1.2, S-5.2.2, S-5.3.1 — connection test, model-role mapping, usage log (USD cost/WIB time); client/retry/structured-output & cross-owner invariants (automated-backed) |
| [ui/S-7-stories.md](./ui/S-7-stories.md) | S-1.1.1/2 — story CRUD: workspace list, create dialog, edit page, delete confirm |
| [ui/S-1.2-story-settings-overview.md](./ui/S-1.2-story-settings-overview.md) | S-1.2.1/2 — per-story workspace: overview counts + play-readiness, default POV + model-role overrides |
| [ui/S-2.1-workspace-shell.md](./ui/S-2.1-workspace-shell.md) | S-2.1.1/2 — authoring workspace shell: full tab set, placeholder surfaces, play-readiness |
| [ui/S-9-lorebook.md](./ui/S-9-lorebook.md) | S-3.1.1 — per-story lorebook CRUD: empty state, create/edit/delete, validation, reveal-gate degradation |
| [ui/S-10-reveal-ledger.md](./ui/S-10-reveal-ledger.md) | S-4.1.1 — per-story reveal-ledger CRUD: chapter-gated empty state, create/edit/delete, who-knows slug chips, world-secret default |

(`{domain}` = `npc-behaviour` | `directing` | `narrator` | `session` | `review-gate` | `ui` — this engine has no role-based `*-side/` split.)

## What to capture

- The exact UI path taken (navigate, **no direct-URL** jumps).
- Screenshots / prose excerpts as evidence.
- Pass/fail per acceptance criterion from the feature doc.
- Any leak observed (mark as a **safety** defect, highest priority).
