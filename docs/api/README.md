# API Contracts

Endpoint and Inertia-props contracts, grouped by resource (or subsystem).

> **Status: empty skeleton.** No HTTP API or Inertia pages exist yet — the app has not been built (see [../adr/GAPS.md](../adr/GAPS.md) O4). This folder fills in once the narrator → player loop and the review-gate surface get routes.

## Naming

```
api/{resource}.md                 # cross-cutting resource
api/{domain}/{domain}-{resource}.md   # subsystem-scoped (e.g. session/session-review-gate.md)
```

## Expected first contracts (when O1/O4 begin)

- `session/` — start/resume session, advance turn, player input + delivery channel
- `review-gate/` — list pending proposals (deltas / nudge-compile / beat-records), accept/edit/reject
- `relationship-viewer/` — read the per-edge audit log (fed by ADR 0003)

Each contract documents request, response, error shape, and the Inertia props it powers (Wayfinder-typed).
