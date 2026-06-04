# Security Logs

**Append-only** OWASP Top 10 audits. Never edit an old entry — new findings are a new file.

## Naming

```
OWASP-audit-YYYY-MM-DD-{code}-{slug}.md   # e.g. OWASP-audit-2026-06-04-o4-session-api.md
```

Date is `YYYY-MM-DD` (Asia/Jakarta). Archive dense periods into a subfolder.

## Engine-specific security surface

Beyond standard OWASP, this engine has a **context-isolation security boundary** (the assembler, ADR 0007) and three **leak guards** (ADR 0007/0008/0009-0010). Treat a leak as a security defect:

- **A0 — Context isolation:** an NPC never receives another character's `true_state`, another's edges, the beat doc, or narrator instructions.
- **A0b — Leak guards:** own capped feelings never stated plainly; authorial omniscience never crosses the nudge compile; others' hidden truth never crosses the recorder `surface` (hedged-attribution enforced structurally).
- Plus injection, authz, secrets (Claude API keys), and prompt-injection from player input.
