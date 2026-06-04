# Documentation Structure Standard — Directed Interactive Novel Engine

> **Status:** Authoritative · **Author:** Zulfikar Hidayatullah (+62 857-1583-8733) · **Last Updated:** 2026-06-04
>
> This document is the **official standard** for the `docs/` folder of the **Directed Interactive Novel Engine**. Its goal: every contributor knows **where** a document belongs, **how** to name it, and **when** to create it — so the knowledge base stays consistent and navigable.
>
> Adapted from the *Aligator v2 (Labeling App Peruri)* documentation standard, **retargeted** from a role-based production app to an **agent / subsystem-based narrative engine**. Written in English to match this project's existing ADRs and the architecture brief (single-source-of-truth principle).

> **Related documents:**
> - [README.md](./README.md) — docs entry point, overview, tech stack, navigation
> - [adr/README.md](./adr/README.md) — append-only architecture decision log (ADR 0001–0010)
> - [directed_interactive_novel_engine_v2.html](./directed_interactive_novel_engine_v2.html) — the architecture brief (consolidated snapshot)
> - [adr/GAPS.md](./adr/GAPS.md) — open items measured against the runtime flow (O1–O4)

---

## 1. Philosophy

1. **Single source of truth** — every fact has one home. Don't duplicate; cross-reference.
2. **Location = document type** — the folder declares the kind; the filename declares the subject + code/date.
3. **Hierarchy for navigation** — grouped by **agent** (Narrator / NPC / Player / Director) and **subsystem** (npc-behaviour, directing, narrator, session, review-gate, ui).
4. **Append-only for audit & decisions** — ADRs, security logs, and business-logic logs are never rewritten; add a new entry that supersedes the old.
5. **Documentation follows code** — inline docstrings (PHPDoc/JSDoc) are the first layer; `docs/` is for cross-file knowledge. The **ADRs hold the *why***; the brief is the snapshot.

---

## 2. Agents & Subsystems

This engine has **no end-user roles** (no admin/operator). Instead it is organized around **three narrative agents** plus the **Director/Engine** that orchestrates them, and a set of **subsystem domains**. The hard rule of the engine is **context isolation** — each agent acts only within the limits of what it knows (enforced mechanically by the NPC context assembler, ADR 0007).

### 2.1 Agents (the "audiences" of the runtime)

| Agent | Sees | Documentation grouping |
|-------|------|------------------------|
| **Narrator** | Beat doc (full), full relationship mesh, scene history, player inputs, NPC responses. Uses mesh for atmosphere/body-language **only**. | `narrator/` |
| **NPC** | Own card + own-perspective edges + leak-checked nudge + witnessed, POV-projected excerpt. **Not** the beat doc, other cards, or other edges. | `npc-behaviour/` |
| **Player** | Rendered prose only (+ a delivery/tone input channel). | `ui/` |
| **Director / Engine** | Out-of-context orchestration: the session state machine, stall flags, word budgets, the shared review gate. | `session/`, `review-gate/` |

### 2.2 Subsystem domains (the analog of the source standard's "domains")

| Domain | Scope | Owning ADRs / open items |
|--------|-------|--------------------------|
| `npc-behaviour` | Character data, relationship edges, delta engine, scars/decay, trigger taxonomy, register, NPC context assembly | ADR 0001–0007 (**built**) |
| `directing` | Psychological nudge (the only authorial channel) + beat document + `BEAT_DONE` | ADR 0008 (**built**) · **O2 open** |
| `narrator` | Narrator loop, handoff detection, POV projection, recorder | ADR 0009–0010 (**built**) · **O1 open** |
| `session` | Session state machine, persistence, context-memory layers, internal-state schema | **O3, O4 open** |
| `review-gate` | The single review surface shared by deltas (0003), nudge-compile (0008), beat records (0010) | ADR 0003/0008/0010 |
| `ui` | Prose display, player input + delivery channel, the relationship viewer (fed by the audit log) | **O4 open** |

> "Subsystem" folders are the narrative-engine equivalent of the source standard's role folders (`admin/`/`operator/`). Use them anywhere the source standard nests by role.

---

## 3. Structure Map

```
docs/
│
├── 📄 README.md                       # Entry point: overview, tech stack, navigation
├── 📄 DOCUMENTATION_STRUCTURE.md      # This standard
├── 📄 directed_interactive_novel_engine_v2.html   # Architecture brief (consolidated snapshot, preserved)
│
├── 📁 architecture/                   # System design, data flow, schema, diagrams
│   ├── README.md
│   ├── ARCHITECTURE.md                # Agents, state machine, memory layers, behavior equation, isolation
│   ├── DATABASE.md                    # Proposed persistence schema (DRAFT → becomes an ADR when O4 lands)
│   └── Diagrams/                      # Mermaid diagrams, grouped by subject
│       ├── README.md
│       ├── Agents/                    # e.g. Context_Isolation.md
│       ├── Engine/                    # e.g. Session_State_Machine.md
│       └── Data/                      # e.g. Persistence_Erd.md
│
├── 📁 adr/                            # Architecture Decision Records (immutable) — EXISTING
│   ├── README.md                      # Index + status
│   ├── GAPS.md                        # Open items vs the flow (O1–O4)
│   └── NNNN-kebab-title.md            # 4-digit, e.g. 0007-npc-context-assembly.md
│
├── 📁 api/                            # Endpoint / Inertia-props contracts (none yet — skeleton)
│   └── README.md
│
├── 📁 features/                       # Lean feature specs (≤300 lines), grouped by subsystem
│   ├── README.md
│   ├── _templates/feature-template.md
│   ├── npc-behaviour/                 # built subsystem index (references ADR 0001–0007)
│   ├── directing/                     # O2-beat-document.md, ...
│   ├── narrator/                      # O1-narrator-loop.md, ...
│   └── session/                       # O3-internal-state-schema.md, O4-persistence-and-ui.md
│
├── 📁 testing/                        # Test plans & QA checklists
│   ├── README.md
│   └── _templates/test-plan-template.md
│
├── 📁 guides/                         # How-to, onboarding, glossary, tracking, diagnostics
│   ├── README.md
│   ├── glossary.md                    # The engine's dense vocabulary (register, scar, legibility, ...)
│   ├── PLACEHOLDER_TRACKING.md        # Placeholder & design-divergence tracking
│   └── documentation-coverage-audit.md # ADR/brief → new-structure coverage audit + gaps
│
├── 📁 manual-qa-check/                # Manual QA evidence per story (skeleton)
│   └── README.md
│
├── 📁 business_logic_logs/            # Business-logic audits (append-only)
│   └── README.md
│
├── 📁 security_logs/                  # OWASP audits (append-only)
│   └── README.md
│
├── 📁 runbooks/                       # Operational / diagnostic playbooks
│   └── README.md
│
└── 📁 reviews/                        # (optional) UX / feature review notes
    └── README.md
```

> **Preserved as-is (no reorg this pass):** `adr/` (0001–0010 + README + GAPS) and the brief HTML stay where they are. The source bibles (`luna-archi.md`, `luna-archi-author-notes.md`) remain at the repo root as **authoring source** (never injected; see ADR 0001) — flagged in the coverage audit for a possible future `content/bibles/` home.

---

## 4. Folder Reference

| Folder | Purpose | Naming convention | When to add a file | Lifecycle |
|--------|---------|-------------------|--------------------|-----------|
| **(docs root)** | Meta-docs: entry point & standard | `UPPER_SNAKE.md` | On setup / standard change | Editable |
| `architecture/` | System design, layers, data flow, schema, diagrams | Core: `ARCHITECTURE.md`, `DATABASE.md`. Diagrams: `Diagrams/{Subject}/{Title_Case}.md` | When a pattern/schema/cross-subsystem flow changes | Editable |
| `adr/` | Architecture decisions + their rationale | `NNNN-kebab-title.md` (**4-digit**) + `README.md` | Every architecture/library decision | **Immutable** (add a superseding ADR) |
| `api/` | Endpoint contracts (request/response/error + Inertia props) | `{domain}/{domain}-{resource}.md` or `{resource}.md` | When an endpoint/resource changes | Editable |
| `features/` | Lean feature specs (≤300 lines) | `{domain}/{CODE}-{slug}.md` | Every new/modified feature | Editable |
| `testing/` | Test plans & QA checklists | `{CODE}-{slug}-test-plan.md` | Alongside each feature | Editable |
| `guides/` | How-to, onboarding, glossary, tracking, diagnostics | `glossary.md`, `{feature}-onboarding.md`, `PLACEHOLDER_TRACKING.md` | When step-by-step guidance is needed | Editable |
| `manual-qa-check/` | Manual QA evidence per story | `{domain}/{CODE}-{slug}.md` | After manual QA of a story | Append (per story) |
| `business_logic_logs/` | Business-logic integrity audits | `BL-audit-YYYY-MM-DD-{code}-{slug}.md` | After a business-logic audit | **Append-only** |
| `security_logs/` | OWASP Top 10 audits | `OWASP-audit-YYYY-MM-DD-{code}-{slug}.md` | After a security audit | **Append-only** |
| `runbooks/` | Ops/diagnostic playbooks | `{topic}-diagnostics.md` | When an incident/integration playbook is needed | Editable |
| `reviews/` | Structured UX/feature review notes | `{area}-ux-review.md` | When a structured review is done | Editable |

---

## 5. Naming Conventions

### 5.1 Codes (decisions, open items, and future scrum work)

This project tracks work through three coexisting code systems. Use the one that fits the document.

| Prefix | Meaning | Scope | Example |
|--------|---------|-------|---------|
| `ADR-NNNN` | A recorded architecture decision (**4-digit**) | A locked subsystem decision | `ADR-0007` (NPC context assembly) |
| `O{n}` | An **open** architecture item from `adr/GAPS.md` | Backlog against the runtime flow | `O1` (narrator loop), `O2` (beat doc) |
| `E{n}.{m}` / `S-{e}.{s}.{n}` | Scrum **Epic / Story** (added when scrum docs are generated) | Forward feature work | `E1.1-narrator-prose-loop.md`, `S-1.1.3` |

> When formal scrum begins (via the `scrum-document-generator` skill), the Phase → Epic → Story codes mirror the source standard. Until then, `features/` is keyed by `O{n}` (open items) and references the relevant `ADR-NNNN`.

### 5.2 Audit files (dated, append-only)

```
{TYPE}-audit-{YYYY-MM-DD}-{code}-{slug}.md
```

| Type | Folder | Example |
|------|--------|---------|
| `BL` | `business_logic_logs/` | `BL-audit-2026-06-04-o3-internal-state.md` |
| `OWASP` | `security_logs/` | `OWASP-audit-2026-06-04-o4-session-api.md` |

- **Date = `YYYY-MM-DD`** (Asia/Jakarta) for lexicographic ordering.
- **Never edit an old file** — new findings = new file. Archive old periods into a subfolder (e.g. `2026-q2/`).

### 5.3 ADR

- Format `NNNN-kebab-title.md`, **4-digit sequential** (`0001`…). This is **this project's established convention** (ADR 0001–0010 already exist) — it intentionally differs from the source standard's 3-digit form. **Keep 4 digits.**
- **Immutable**: when a decision changes, write a new ADR that states *supersedes #NNNN* and mark the old one `Superseded by NNNN`.
- Register every ADR in [`adr/README.md`](./adr/README.md).

### 5.4 General rules

- Markdown `.md`; content filenames in `kebab-case`; docs-root meta-docs in `UPPER_SNAKE`.
- Subsystem folders are always lowercase: `npc-behaviour/`, `directing/`, `narrator/`, `session/`, `review-gate/`, `ui/`.
- Template folders are underscore-prefixed: `_templates/`.
- `Diagrams/{Subject}/` uses PascalCase subjects + `Title_Case` filenames (e.g. `Engine/Session_State_Machine.md`).
- Every top-level folder has a `README.md` index.
- **Case-sensitive** — respect existing capitalization (developer rule: "always watch for the case sensitivity").

---

## 6. "One Feature = Many Documents"

When building one feature, the documents are **spread by type** (don't create one fat file):

```
features/{domain}/{CODE}-{slug}.md            → lean spec (≤300 lines) + Related Documentation
api/{resource}.md (or {domain}/...)           → endpoint contract + Inertia props
testing/{CODE}-{slug}-test-plan.md            → QA checklist (maps to automated tests)
guides/{feature}-onboarding.md                → developer walkthrough
manual-qa-check/{domain}/{CODE}-{slug}.md     → manual QA evidence (UI navigation, no direct-URL)
business_logic_logs/BL-audit-*.md             → business-logic audit
security_logs/OWASP-audit-*.md                → security audit
architecture/Diagrams/{Subject}/{Title}.md    → ERD + sequence/flow (Mermaid)
runbooks/{topic}-diagnostics.md               → ops/incident playbook (if needed)
```

> A feature doc **MUST** include a `Related Documentation` section that links the documents above (relative paths).
>
> Because the **NPC behaviour subsystem is already decided** (ADR 0001–0010), its `features/npc-behaviour/` docs primarily **reference** the ADRs rather than restate them. New forward work (O1–O4) gets full feature docs.

---

## 7. Quick Decision Tree

| I just… | Put it in |
|---------|-----------|
| Changed tech stack / setup | `README.md` |
| Made an architecture/library decision | `adr/NNNN-*.md` (new, 4-digit) |
| Changed a pattern/layer/data flow | `architecture/*` |
| Changed the database schema | `architecture/DATABASE.md` + `architecture/Diagrams/Data/*` |
| Created/changed an endpoint or Inertia props | `api/{resource}.md` |
| Specced/finished a feature (open item or epic) | `features/{domain}/{CODE}-*.md` (+ derived docs, §6) |
| Wrote a test plan | `testing/{CODE}-{slug}-test-plan.md` |
| Needed a term defined | `guides/glossary.md` |
| Left a placeholder / design divergence | `guides/PLACEHOLDER_TRACKING.md` |
| Finished manual QA of a story | `manual-qa-check/{domain}/...` |
| Finished a business-logic audit | `business_logic_logs/BL-audit-*.md` |
| Finished a security audit | `security_logs/OWASP-audit-*.md` |
| Wrote an incident/ops procedure | `runbooks/{topic}-diagnostics.md` |
| Did a UX review | `reviews/{area}-ux-review.md` |

---

## 8. Maintenance & Consistency Notes

- Update the docs `README.md` and the relevant folder `README.md` whenever you add a document.
- Keep cross-references valid (use relative paths).
- Archive audit logs per period once a folder gets dense.
- Inline docstrings remain mandatory at the code level; `docs/` complements, not replaces, them.
- The **ADRs are the source of truth for decisions**; `architecture/ARCHITECTURE.md` and the brief are *snapshots* — when they disagree with an ADR, the ADR wins.

---

**Author: Zulfikar Hidayatullah** · This standard governs all contributions to `docs/` in the Directed Interactive Novel Engine.
