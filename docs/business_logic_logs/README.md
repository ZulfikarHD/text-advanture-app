# Business Logic Logs

**Append-only** audits of business-logic integrity. Never edit an old entry — new findings are a new file.

## Naming

```
BL-audit-YYYY-MM-DD-{code}-{slug}.md     # e.g. BL-audit-2026-06-04-o3-internal-state.md
```

Date is `YYYY-MM-DD` (Asia/Jakarta) for lexicographic ordering. Archive dense periods into a subfolder (e.g. `2026-q2/`).

## Engine focus areas

- **Delta engine correctness** — drift clamps to soft bounds, rupture reaches hard bounds, latch sets a permanent floor, decay stops at the scar floor (ADR 0003/0004).
- **Appraisal salience** — match-only; multiple sensitivities emit multiple proposals; every proposal names a `trigger` (ADR 0005).
- **Register resolution** — pinned base bypasses the threshold selector; emotional modulation changes surface not grammar (ADR 0006).
- **Review gate** — nothing commits without passing the shared propose→review→commit gate.
