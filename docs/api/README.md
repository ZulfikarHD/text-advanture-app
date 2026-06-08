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
| [characters.md](./characters.md) | Per-story minimal manual character CRUD: name/appearance/`is_player` + NPC `folded_identity`/`knowledge_boundary` (`stories.characters.index/store/update/destroy`, E1.1) |
| [structure.md](./structure.md) | Per-story minimal manual structure CRUD: hand-authored chapter → scene → beat, scene POV contract + present cast, beat goal (`stories.structure.index` + `chapters/scenes/beats.store/update/destroy`, E1.2) |
| [lorebook.md](./lorebook.md) | Per-story lorebook CRUD: keyword-injected world facts (`stories.lorebook.index/store/update/destroy`, E3.1) |
| [reveal-ledger.md](./reveal-ledger.md) | Per-story reveal-ledger CRUD: load-bearing secrets → reveal point (`stories.reveal-ledger.index/store/update/destroy`, E4.1) |
| [saves.md](./saves.md) | Per-story session fork: start a playthrough + list saves + open Play (`stories.saves.index/store/play`, E2.1 / S-2.1.1) |

> **Sprint 3 added no HTTP endpoints.** S-4.1.1 is a data-layer change only — the authoring-realm schema, models, and `StoryPolicy` exist, but **story/authoring CRUD endpoints arrive in Phase 2** (story & world management). Theming stays cookie-based on the static `appearance.edit` surface (see [account.md](./account.md)); the new shell theme toggle calls no backend route.
>
> **Sprint 4** added the **provider-key** endpoints ([provider.md](./provider.md)); the save-realm + global-library work (S-4.1.2 / S-4.2.x) is data-layer only (no HTTP surface this sprint).
>
> **Sprint 5** added the LLM-client surfaces: the provider **connection test** ([provider.md](./provider.md) §6), the **model-role** editor ([model-roles.md](./model-roles.md)), and the **usage log** ([usage.md](./usage.md)). The `LlmClient` itself is a backend service with no direct HTTP surface (it has no engine caller until Phase 2+).
>
> **Sprint 6** seeded the five global libraries (so the **Model roles** editor now ships with defaults) and added the first **review-gate** surface ([reviews.md](./reviews.md)) — a top-level `/reviews` queue with accept/edit/reject; the per-producer commit handlers are deferred to Phase 7.
>
> **Sprint 9** added the per-story **lorebook** CRUD ([lorebook.md](./lorebook.md)) — the first scoped-binding nested resource; runtime keyword injection, world-fact-discipline validation (S-3.1.2), and the keyword-match preview (S-3.2.1) are deferred.
>
> **Sprint 10** added the per-story **reveal-ledger** CRUD ([reveal-ledger.md](./reveal-ledger.md)) — load-bearing secrets `{ fact, reveal_chapter, who_knows }` that make spoiler-safety explicit; the compile clamp that consumes them (Phase 3) and the reveal-clamp preview (S-4.1.2) are deferred (PH-34).
>
> **Sprint 11** added the per-story **characters** CRUD ([characters.md](./characters.md), E1.1) — the first **minimal manual** authoring surface (no LLM call): name/appearance/`base_opacity` + exactly-one `is_player`, with NPC `folded_identity` and mandatory `knowledge_boundary`. Each character commits a chapter-1 `character_card`, so the surface auto-ensures a default **Chapter 1** to anchor it (characters are tied to chapters). The AI/hybrid creation + bible→card compile pipeline and `live_axes` edges stay deferred to Phase 5. **Resolves the characters portion of PH-30.**
>
> **Sprint 12** added the per-story **structure** CRUD ([structure.md](./structure.md), E1.2) — the second **minimal manual** authoring surface (no LLM call): a hand-authored chapter → scene → beat tree under a single `StructureController`, with scoped bindings down the `{story}→{chapter}→{scene}→{beat}` chain. A scene carries its POV contract (`pov_mode`/`pov_anchor`/`tone`) + present cast (character slugs); a beat carries its `goal`. The beat document (`intent`/`word_budget`/`nudge_target`) and outline compilation stay deferred to Phase 4 (PH-35). **Resolves the Structure portion of PH-30** — only Saves remains a placeholder.
>
> **Sprint 13** added the per-story **session fork** ([saves.md](./saves.md), E2.1 / S-2.1.1) — the first **save-realm** surface: starting a session atomically forks a play-ready story into one `play_sessions` row at `session_start`, positioned at its first beat, without mutating the authoring template (ADR 0012) and seeding no edges yet (Phase 5). The fresh save lands on a reachable **Play** placeholder. **Resolves PH-30** (Saves was the last `ComingSoon` surface; the shared placeholder is removed). Multi-save management (S-2.1.2), resume (S-2.1.3), and the full Play reader (S-5.4.1) stay deferred (PH-36).
>
> **E3.1 / E4.1 (engine internals) added no HTTP endpoints.** The state-machine spine (S-3.1.1, `SessionStateMachine`) and the **narrator prompt assembly** (S-4.1.1, `NarratorPromptAssembler` — reads the `prompt_blocks` registry to fold the lit narrator blocks into the turn's prompt) are backend services with **no route**: the assembler produces chat messages, the prose call that *sends* them and the advance/pause Play controls arrive with S-4.2.1 + S-5.4.1 (PH-37/PH-36). See [features/narrator/S-4.1.1-narrator-prompt-assembly.md](../features/narrator/S-4.1.1-narrator-prompt-assembly.md).

## Expected first contracts (when O1/O4 begin)

- `session/` — multi-save (load/reset/delete), advance turn, player input + delivery channel (the fork is shipped in [saves.md](./saves.md))
- `review-gate/` — list pending proposals (deltas / nudge-compile / beat-records), accept/edit/reject
- `relationship-viewer/` — read the per-edge audit log (fed by ADR 0003)

Each contract documents request, response, error shape, and the Inertia props it powers (Wayfinder-typed).
