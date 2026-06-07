# O4 — Persistence + tech stack + UI

> **Status:** In progress (tech stack + persistence designed; UI + orchestration open) · **Domain:** session / ui · **Owning ADR(s):** [ADR 0011](../../adr/0011-tech-stack.md) (tech stack), [ADR 0012](../../adr/0012-persistence-schema.md) (persistence) — both `Proposed`; UI ADR + orchestration ADR pending · **Last Updated:** 2026-06-04

## Summary

Everything around the loop that makes it run: how state is saved/loaded, the framework, and the player-facing UI including the shared review-gate surface and the relationship viewer.

## Status by piece

| Piece | State |
|-------|-------|
| **Tech stack** | **Locked** — [ADR 0011](../../adr/0011-tech-stack.md): Laravel 13 / Vue 3 + Inertia v3 / Wayfinder / Tailwind 4 / pnpm / MariaDB 11.7 (MySQL-8-compatible). |
| **Persistence** | **Locked** — [ADR 0012](../../adr/0012-persistence-schema.md): two-realm, multi-save; column detail in [DATABASE.md](../../architecture/DATABASE.md). |
| **Review-gate UI** | Open — one surface for deltas (0003) + nudge-compile (0008) + beat records (0010). |
| **Relationship viewer** | Open — reads the `axis_deltas` append-only audit log (0003). |
| **Player input** | Open — prose + optional tone tag + ambiguity prompt (the sourced delivery channel, ADR 0010). |
| **LLM client / orchestration** | **Locked** — [ADR 0017](../../adr/0017-llm-orchestration-openrouter.md): OpenRouter gateway + thin `LlmClient`, model-role tiering, `model_profiles` / `llm_calls`. |
| **Cost/latency** | Partially addressed — the `llm_calls` log + caching ref land in [ADR 0017](../../adr/0017-llm-orchestration-openrouter.md); call sequencing/batching/queues remain open (future orchestration ADR). A 3-NPC beat is ~10+ LLM calls. |

## Goal & non-goals

- **Goal:** persist and resume a playthrough; render prose + input; review proposals; inspect relationships.
- **Non-goals:** the loop internals ([O1](../narrator/O1-narrator-loop.md)) and beat format ([O2](../directing/O2-beat-document.md)).

## Agent / isolation impact

The persistence layer encodes the isolation boundary (`beat_true_states` split out). The review-gate UI is the **human fidelity floor** for all three leak guards.

## Open questions

- Database driver: pinned in [ADR 0011](../../adr/0011-tech-stack.md) to MariaDB 11.7 (dev) / MySQL-8-compatible schema.
- ~~Is the Laravel AI SDK the orchestration layer for compile→act, or a thin custom client?~~ **Resolved by [ADR 0017](../../adr/0017-llm-orchestration-openrouter.md):** thin provider-agnostic `LlmClient` over the OpenRouter gateway; Prism / AI SDK remain a drop-in behind it.
- ~~Multi-save UX: forking, naming, reset.~~ **Resolved (S-2.1.1/S-2.1.2/S-2.1.3):** the **Saves** tab forks a play-ready story, names/renames/resets/deletes independent saves, and resumes a save at its persisted loop position (`SessionService` + `SessionController` + `Saves.vue`; see [../../api/saves.md](../../api/saves.md)). Loop-state *producers* (state machine, resume anchor, word/nudge clocks) remain later — PH-37.

## Related Documentation

- Architecture: [DATABASE.md](../../architecture/DATABASE.md) · [Persistence ERD](../../architecture/Diagrams/Data/Persistence_Erd.md)
- API skeleton: [../../api/README.md](../../api/README.md) · Open items: [GAPS O4](../../adr/GAPS.md)
