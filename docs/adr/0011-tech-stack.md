# 0011 — Tech stack

- **Status:** Accepted
- **Date:** 2026-06-04

## Context

The brief left **tech stack** explicitly undecided (one of the O4 open items in [GAPS.md](GAPS.md)). The engine needs a stack that can:

- Render authored prose and a stateful, server-driven UI (prose display, player input + delivery channel, the shared review-gate surface, the relationship viewer).
- Orchestrate the **compile → act** LLM calls (ADR 0007) — a 3-NPC beat is ~10+ Claude calls, so background work and caching matter.
- Stay productive for a solo developer using an established, opinionated framework.

The developer's standing rules also constrain the choice: **pnpm**, **Wayfinder instead of Ziggy** for Vue/Inertia projects, a **pragmatic Laravel Service pattern**, timezone **Asia/Jakarta**, currency **Rupiah**. A local **MariaDB 11.7** instance (Docker, port 3306) is the available database.

## Decision

Build a **single Laravel + Inertia application** (no separate SPA/API split):

| Layer | Choice |
|-------|--------|
| Backend | **Laravel 13.x** (released 2026-03-17), **PHP 8.3+**, pragmatic Service pattern |
| Frontend | **Vue 3** (Composition API, TypeScript) + **Inertia.js v3.x** |
| Routing types | **Wayfinder** (typed routes/controllers) — **not Ziggy** |
| Styling / UI | **Tailwind CSS 4** + **shadcn-vue** |
| Build | **Vite 7**, package manager **pnpm** |
| Auth | Laravel **Fortify** via the official Vue starter kit (passkeys available) |
| Database | **MariaDB 11.7** in development (what's available), **MySQL 8-compatible** schema; Laravel `mariadb` connection driver |
| LLM | **Claude API**; the **Laravel AI SDK** (stable in L13) is the candidate orchestration layer for compile→act |
| Conventions | Timezone Asia/Jakarta (WIB), currency Rupiah (Rp) |

The official **Laravel Vue starter kit** is the scaffold (it ships Inertia v3, Wayfinder, Tailwind 4, shadcn-vue, Fortify auth out of the box).

## Alternatives considered

- **SillyTavern / a config on an existing tool.** Rejected by the brief itself — this is a *purpose-built* app.
- **Separate JSON API + standalone SPA.** Rejected: Inertia removes the API-contract overhead for a single-author app; Wayfinder gives end-to-end types without a separate client.
- **Ziggy for routes.** Rejected per the developer rule; Wayfinder is typed and is the starter-kit default since Laravel 12.
- **React / Svelte starter kit.** Rejected: Vue is the standing preference.
- **PostgreSQL / SQLite.** Rejected: MySQL/MariaDB is the available, preferred engine; JSON columns and generated columns are supported on MariaDB 11.7.
- **Laravel 12.** Rejected in favour of 13 (zero breaking changes from 12, AI SDK stable, PHP 8.3 baseline) for a greenfield project.

## Consequences

- The starter kit provides auth scaffolding; **Wayfinder generates types at build time**, so any disabled feature's route references must be removed from the frontend or the build breaks.
- **Orchestration is call-heavy** (compile→act per NPC) — needs queues/caching; this feeds the cost/latency planning in O4 and a future runbook.
- **MariaDB vs MySQL** is pinned to *MariaDB 11.7 in dev, MySQL-8-compatible schema*; revisit only if a MySQL-only feature is needed (resolves [PLACEHOLDER_TRACKING](../guides/PLACEHOLDER_TRACKING.md) PH-3).
- Unblocks **ADR 0012** (persistence schema) and the future UI ADR.
- The locked stack is mirrored (snapshot) in [`../README.md`](../README.md); this ADR is the decision of record.
