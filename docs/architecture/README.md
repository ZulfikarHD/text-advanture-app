# Architecture

System design, data flow, persistence schema, and diagrams for the Directed Interactive Novel Engine.

> The **ADRs hold the *why*** ([../adr/README.md](../adr/README.md)). The files here are **snapshots / structured views**. When a file here disagrees with an ADR, the ADR wins.

## Contents

| File | Purpose | Status |
|------|---------|--------|
| [ARCHITECTURE.md](./ARCHITECTURE.md) | The system: three agents + isolation, session state machine, turn loop, context-memory layers, the behavior equation | Living snapshot |
| [DATABASE.md](./DATABASE.md) | Two-realm persistence schema (authoring vs save); living detail of [ADR 0012](../adr/0012-persistence-schema.md) | Living — foundation (Sprint 1) + authoring realm (Sprint 3) built; save realm pending |
| [Diagrams/](./Diagrams/README.md) | Mermaid diagrams (agents, engine, data) | Living |

## See also

- [../directed_interactive_novel_engine_v2.html](../directed_interactive_novel_engine_v2.html) — consolidated brief
- [../adr/GAPS.md](../adr/GAPS.md) — what's still open (O1–O4)
