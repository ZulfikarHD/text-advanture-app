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

## Likely first runbooks

- `claude-api-diagnostics.md` — rate limits, timeouts, model-tier fallback (Sonnet ↔ Haiku), retry/backoff for the compile→act calls.
- `review-gate-backlog-diagnostics.md` — what to do when proposals (deltas/nudges/records) pile up unreviewed.
- `cost-latency-diagnostics.md` — a 3-NPC beat is ~10+ LLM calls; caching/batching levers (ADR 0007).
