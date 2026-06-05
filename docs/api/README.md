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
| [account.md](./account.md) | Profile / password / appearance (`appearance.edit`) + account deletion (Sprint 2) |
| [provider.md](./provider.md) | Provider API-key management (`provider.edit/update/destroy`) + connection test (`provider.test`, Sprint 5) |
| [model-roles.md](./model-roles.md) | Global role→model mapping editor (`model-roles.edit/update`, Sprint 5) |
| [usage.md](./usage.md) | Owner-scoped LLM usage / cost log (`usage.index`, deferred prop, Sprint 5) |
| [reviews.md](./reviews.md) | Owner-scoped review gate (`reviews.index/accept/update/reject`, deferred prop, Sprint 6) |
| [stories.md](./stories.md) | Story CRUD: workspace list, create/edit/delete (`stories.store/edit/update/destroy`, Sprint 7) |
| [story-overview.md](./story-overview.md) | Per-story overview: derived counts + play-readiness (`stories.show`, E1.2) |
| [story-settings.md](./story-settings.md) | Per-story settings: default POV + model-role overrides (`stories.settings.edit/update`, E1.2) |

> **Sprint 3 added no HTTP endpoints.** S-4.1.1 is a data-layer change only — the authoring-realm schema, models, and `StoryPolicy` exist, but **story/authoring CRUD endpoints arrive in Phase 2** (story & world management). Theming stays cookie-based on the static `appearance.edit` surface (see [account.md](./account.md)); the new shell theme toggle calls no backend route.
>
> **Sprint 4** added the **provider-key** endpoints ([provider.md](./provider.md)); the save-realm + global-library work (S-4.1.2 / S-4.2.x) is data-layer only (no HTTP surface this sprint).
>
> **Sprint 5** added the LLM-client surfaces: the provider **connection test** ([provider.md](./provider.md) §6), the **model-role** editor ([model-roles.md](./model-roles.md)), and the **usage log** ([usage.md](./usage.md)). The `LlmClient` itself is a backend service with no direct HTTP surface (it has no engine caller until Phase 2+).
>
> **Sprint 6** seeded the five global libraries (so the **Model roles** editor now ships with defaults) and added the first **review-gate** surface ([reviews.md](./reviews.md)) — a top-level `/reviews` queue with accept/edit/reject; the per-producer commit handlers are deferred to Phase 7.

## Expected first contracts (when O1/O4 begin)

- `session/` — start/resume session, advance turn, player input + delivery channel
- `review-gate/` — list pending proposals (deltas / nudge-compile / beat-records), accept/edit/reject
- `relationship-viewer/` — read the per-edge audit log (fed by ADR 0003)

Each contract documents request, response, error shape, and the Inertia props it powers (Wayfinder-typed).
