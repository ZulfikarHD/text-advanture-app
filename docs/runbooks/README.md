# Runbooks

Operational and diagnostic playbooks for production / integration incidents.

> **Status: empty skeleton.** Populated when there's something to operate.

## Naming

```
runbooks/{topic}-diagnostics.md     # e.g. claude-api-diagnostics.md
```

## Likely first runbooks

- `claude-api-diagnostics.md` — rate limits, timeouts, model-tier fallback (Sonnet ↔ Haiku), retry/backoff for the compile→act calls.
- `review-gate-backlog-diagnostics.md` — what to do when proposals (deltas/nudges/records) pile up unreviewed.
- `cost-latency-diagnostics.md` — a 3-NPC beat is ~10+ LLM calls; caching/batching levers (ADR 0007).
