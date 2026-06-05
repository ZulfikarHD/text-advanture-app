# API Contracts

Endpoint and Inertia-props contracts, grouped by resource (or subsystem).

## Naming

```
api/{resource}.md                 # cross-cutting resource
api/{domain}/{domain}-{resource}.md   # subsystem-scoped (e.g. session/session-review-gate.md)
```

## Index

| Contract | Covers |
|----------|--------|
| [auth.md](./auth.md) | Sign-in / sign-out / route protection (Fortify) + shared Inertia props (Sprint 1) |

## Expected first contracts (when O1/O4 begin)

- `session/` — start/resume session, advance turn, player input + delivery channel
- `review-gate/` — list pending proposals (deltas / nudge-compile / beat-records), accept/edit/reject
- `relationship-viewer/` — read the per-edge audit log (fed by ADR 0003)

Each contract documents request, response, error shape, and the Inertia props it powers (Wayfinder-typed).
