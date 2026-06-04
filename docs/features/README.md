# Features

Lean feature specs (≤300 lines each), grouped by **subsystem domain** (this engine has no end-user roles — see [../DOCUMENTATION_STRUCTURE.md](../DOCUMENTATION_STRUCTURE.md) §2).

## Domains

| Folder | Scope | Owning ADRs / open items |
|--------|-------|--------------------------|
| [npc-behaviour/](./npc-behaviour/README.md) | Cards, edges, delta engine, scars/decay, triggers, register, assembly | ADR 0001–0007 (**built**) |
| [directing/](./directing/README.md) | Psychological nudge + beat document + `BEAT_DONE` | ADR 0008 · **O2 open** |
| [narrator/](./narrator/README.md) | Narrator loop, handoff, POV projection, recorder | ADR 0009–0010 · **O1 open** |
| [session/](./session/README.md) | State machine, persistence, memory layers, internal state, UI | **O3, O4 open** |

## Coding

- Open items use `O{n}` codes (from [../adr/GAPS.md](../adr/GAPS.md)).
- Decided subsystems **reference** their `ADR-NNNN` rather than restating them.
- When scrum begins, switch to `E{n}.{m}` / `S-{e}.{s}.{n}` (see standard §5.1).

## Authoring a feature doc

Copy [_templates/feature-template.md](./_templates/feature-template.md). Every feature doc MUST end with a `Related Documentation` section (relative links to api/testing/guides/diagrams).
