# Runbooks

Operational and diagnostic playbooks for production / integration incidents.

## Naming

```
runbooks/{topic}-diagnostics.md     # e.g. claude-api-diagnostics.md
```

## Index

| Runbook | Purpose |
|---------|---------|
| [local-setup-diagnostics.md](./local-setup-diagnostics.md) | Clean-clone boot, MariaDB connection triage, lint/build/test gate, production checklist |
| [session-fork-diagnostics.md](./session-fork-diagnostics.md) | Starting a session (fork): play-readiness gating, first-beat positioning, scoped-binding 404s, atomicity (S-2.1.1) |
| [narrator-turn-diagnostics.md](./narrator-turn-diagnostics.md) | The narrator prose call: surfaced failures, reading `Failed` `llm_calls`, retry/backoff config, off-turn rejection, enum hardening (S-4.2.1 / S-4.2.2) |

## Likely first runbooks

- `claude-api-diagnostics.md` — rate limits, timeouts, model-tier fallback (Sonnet ↔ Haiku), retry/backoff for the compile→act calls.
- `review-gate-backlog-diagnostics.md` — what to do when proposals (deltas/nudges/records) pile up unreviewed.
- `cost-latency-diagnostics.md` — a 3-NPC beat is ~10+ LLM calls; caching/batching levers (ADR 0007).
