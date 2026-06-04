# 0012 — Persistence schema (two-realm, multi-save)

- **Status:** Proposed
- **Date:** 2026-06-04
- **Scope note:** This ADR locks the persistence **strategy** and the schema for the **settled** subsystem (ADR 0001–0010) plus skeletal structural tables. Column-level detail for the still-open O1/O2/O3 work is **deferred** (see below). The living, column-level schema lives in [`../architecture/DATABASE.md`](../architecture/DATABASE.md); this ADR is the decision of record.

## Context

ADR 0001 separates authored identity (immutable at runtime) from evolving relationship/internal state, and ADR 0002 says edges are "written at scene/session boundaries." The brief lists save/load and a DB choice as open (O4). We need a relational realization on MySQL/MariaDB (ADR 0011) that:

- Lets a playthrough **evolve and be saved/reset** without clobbering authored data.
- Keeps the **delta engine** (0003) and **decay** (0004) cheap to compute and **explainable** (0003's mandatory `trigger` + audit log).
- Enforces the **context-isolation boundary** (0007/0009/0010) at the data layer, not only in code.
- Answers the core MySQL question: **normalize vs JSON**.

## Decision

### 1. Two realms

- **Authoring realm** — `stories`, `characters`, `character_cards`, `register_archetypes`, `registers`, `sensitivities`, `universal_priors`, `lorebook_entries`, `chapters`, `scenes`, `beats`. **Immutable at runtime.**
- **Save realm** — everything mutable, **scoped to a `session` (a save)**: `relationship_edges`, `edge_axes`, `axis_deltas`, `internal_states`, `active_emotions`, `acquired_sensitivities`, `beat_records`, `beat_true_states`, `beat_witnesses`, `nudges`, `review_items`, `scene_summaries`, `chapter_logs`, `events`.

A `session` is a **fork** of the authored template into evolving state → **multi-save and reset for free**. Source bibles stay as repo markdown referenced by slug (never injected, ADR 0001).

### 2. Normalize vs JSON

Normalize what the engine reads/writes **per-axis** or **queries on**; use **JSON** for nested authorial config read as a whole.

- **Rows:** `edge_axes` (one row per live axis — delta engine + decay are per-axis), `axis_deltas` (append-only log keyed by edge+axis), `beat_true_states` / `beat_witnesses` (isolation), `active_emotions` (per-emotion decay).
- **JSON:** `knowledge_boundary`, `disposition_priors`, register dimension profiles + `overrides`, `topic_flags`, sensitivity `detect`/`axes`, nudge `kind`, the `scar` sub-object.

### 3. Materialized state + append-only log (not event-sourcing)

Current values live in `edge_axes`/`internal_states` for fast reads; `axis_deltas` (and the beat-record/nudge tables) are **append-only history**. We do **not** event-source the edges — replaying clamp + latch + decay on every read is needless complexity. The log is the source of truth for the relationship viewer and for debugging a character that "feels off."

### 4. Isolation made structural

`beat_true_states` is a **separate child table** of `beat_records`, never a column — so the assembler's "read `surface` only" query *physically cannot* pull another character's private `true_state`. Append-only tables carry only `created_at`. Typed via PHP/DB enums (`axis`, `awareness_mode`, `delta_channel`, `delta_source`, `fidelity`, `nudge_level`, `target`, `state_node`, `review_status`, `producer_type`).

### 5. Shared review gate

A single `review_items` queue (`producer_type` + `producer_id` + `payload` + `status`) backs the one review surface shared by deltas (0003), nudge-compile (0008), and beat records (0010).

## Alternatives considered

- **Single canonical mutable world (no per-save fork).** Rejected: no multi-save/reset, and it re-merges authoring with runtime — the exact thing ADR 0001 warns against.
- **Edge stored as one JSON document.** Rejected: the delta engine and decay operate per-axis and the relationship viewer needs per-axis history; JSON would force read-modify-write of the whole blob and lose queryability.
- **Full event-sourcing of axis values.** Rejected: clamp/latch/decay replay is complex; the materialized-state + append-only-log middle ground is simpler and equally auditable.
- **`true_state` as a column on `beat_records`.** Rejected: a careless `SELECT *` could cross the isolation boundary; splitting it out makes leaks structurally hard.

## Consequences

- Migrations split into an **authoring** set and a **save** set; the save set is FK-scoped to `sessions`.
- Append-only tables are **never `UPDATE`/`DELETE`d**; corrections are new rows through the review gate.
- **Deferred** (lands with its ADR): richer `sessions` loop state, `scene_summaries`/`chapter_logs`/`events` shapes (**O1**); `beats.BEAT_DONE` + boundary-event tables (**O2**); the precise `internal_states`/`active_emotions` shape (**O3**).
- Resolves [PLACEHOLDER_TRACKING](../guides/PLACEHOLDER_TRACKING.md) PH-1; the skeletal-table placeholders (PH-4/PH-5) remain until O1–O3.
