# Directed Interactive Novel Engine — Documentation

> **Entry point** for the `docs/` knowledge base. · **Last Updated:** 2026-06-04
>
> Read the structure standard first: [DOCUMENTATION_STRUCTURE.md](./DOCUMENTATION_STRUCTURE.md).

---

## What this project is

A **purpose-built web app** (not a SillyTavern config) — a *Directed Interactive Novel Engine* on the Claude API. The player reads authored-quality prose chapter-by-chapter, with player-input moments and autonomous NPC agent turns. A hidden **beat document** steers everything toward chapter goals **without** issuing explicit plot instructions to the characters.

The whole design rests on **context isolation** between three agents, plus three orthogonal **leak guards** and one shared **review gate**. The full *why* lives in the [ADRs](./adr/README.md); the consolidated snapshot is the [architecture brief](./directed_interactive_novel_engine_v2.html).

## Status at a glance

| Area | State |
|------|-------|
| NPC behaviour subsystem (ADR 0001–0010) | **Designed & accepted** |
| Narrator loop + turn sequencing (**O1**) | Open — see [adr/GAPS.md](./adr/GAPS.md) |
| Beat document + `BEAT_DONE` (**O2**) | Open |
| Internal-state schema (**O3**) | Open |
| Persistence + tech stack + UI (**O4**) | Tech stack ([ADR 0011](./adr/0011-tech-stack.md)) + persistence ([ADR 0012](./adr/0012-persistence-schema.md)) locked; schema in [architecture/DATABASE.md](./architecture/DATABASE.md); UI open; app not built yet |

## Tech stack (locked — [ADR 0011](./adr/0011-tech-stack.md))

- **Backend:** Laravel 13.x (PHP 8.3+), pragmatic Service pattern
- **Frontend:** Vue 3 + Inertia.js v3.x, Wayfinder (typed routes, **not** Ziggy), Tailwind 4, shadcn-vue
- **Tooling:** pnpm, Vite 7
- **Database:** MySQL 8 / MariaDB 11.7 (wire-compatible; Laravel `mariadb` driver)
- **LLM orchestration:** Claude API (Laravel AI SDK is a candidate for the compile→act calls)
- **Standards:** Timezone Asia/Jakarta (WIB), currency Rupiah (Rp)

## Navigation

| You want… | Go to |
|-----------|-------|
| The folder/naming standard | [DOCUMENTATION_STRUCTURE.md](./DOCUMENTATION_STRUCTURE.md) |
| The consolidated architecture snapshot | [directed_interactive_novel_engine_v2.html](./directed_interactive_novel_engine_v2.html) |
| Why each decision was made | [adr/README.md](./adr/README.md) (ADR 0001–0010) |
| What's still open | [adr/GAPS.md](./adr/GAPS.md) (O1–O4) |
| System design in structured form | [architecture/ARCHITECTURE.md](./architecture/ARCHITECTURE.md) |
| The proposed database schema | [architecture/DATABASE.md](./architecture/DATABASE.md) |
| Diagrams (Mermaid) | [architecture/Diagrams/README.md](./architecture/Diagrams/README.md) |
| Feature specs / backlog | [features/README.md](./features/README.md) |
| Term definitions | [guides/glossary.md](./guides/glossary.md) |
| What docs exist vs. what's missing | [guides/documentation-coverage-audit.md](./guides/documentation-coverage-audit.md) |

## Conventions reminder

- ADRs are **4-digit** and **immutable** (supersede, don't edit).
- Audit logs (`business_logic_logs/`, `security_logs/`) are **append-only**, dated `YYYY-MM-DD`.
- Filenames `kebab-case`; meta-docs `UPPER_SNAKE`; subsystem folders lowercase. **Case-sensitive.**
