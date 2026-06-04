# Documentation Coverage Audit

> **Date:** 2026-06-04 · One-time audit performed while scaffolding the `docs/` standard. Maps existing source docs (the brief + ADR 0001–0010 + GAPS + bibles) onto the new structure, and lists what's **missing**.

---

## A. Source inventory (what existed before this scaffold)

| Source | Type | Disposition |
|--------|------|-------------|
| `docs/directed_interactive_novel_engine_v2.html` | Architecture brief (snapshot) | **Preserved** in place; distilled into `architecture/ARCHITECTURE.md` |
| `docs/adr/0001–0010` | Decision records | **Preserved**; referenced from `features/` + `architecture/` |
| `docs/adr/README.md` | ADR index | **Preserved** |
| `docs/adr/GAPS.md` | Open-items audit (O1–O4) | **Preserved**; surfaced as `features/` open-item specs |
| `luna-archi.md`, `luna-archi-author-notes.md` | Source bibles (one example character) | **Left at repo root** (authoring source, never injected) — see GAP-7 |

---

## B. Migration map (brief section → new home)

| Brief / ADR content | New structured home |
|---------------------|---------------------|
| Project summary | `README.md`, `architecture/ARCHITECTURE.md` §1 |
| Three agents — isolation | `ARCHITECTURE.md` §2 + `Diagrams/Agents/Context_Isolation.md` |
| Session state machine | `ARCHITECTURE.md` §3 + `Diagrams/Engine/Session_State_Machine.md` |
| Data model — three layers (0001) | `ARCHITECTURE.md`, `DATABASE.md` §3 |
| Relationship edge schema (0002) | `DATABASE.md` §4 (`edge_axes`), `glossary.md` |
| Emotion axes / awareness tiers | `glossary.md`, `ARCHITECTURE.md` |
| Caps / floors / scars (0004) | `glossary.md`, `DATABASE.md`, `Diagrams/Data` |
| Delta engine + triggers (0003/0005) | `ARCHITECTURE.md` §8, `glossary.md` |
| Expression mask + register (0006) | `ARCHITECTURE.md` §4, `glossary.md` |
| NPC context assembly (0007) | `ARCHITECTURE.md` §5 |
| Nudge (0008) | `ARCHITECTURE.md` §4/§7, `features/directing/` |
| POV projection + recorder (0009/0010) | `ARCHITECTURE.md` §6/§7 |
| Context-memory layers / resume anchor | `ARCHITECTURE.md` §9/§3 |
| "What is not yet decided" / next steps | `adr/GAPS.md` (kept) + `features/` O1–O4 + `README.md` status |
| Persistence (was undecided) | [ADR 0012](../adr/0012-persistence-schema.md) + `architecture/DATABASE.md` (living detail) + `Diagrams/Data/Persistence_Erd.md` |
| Tech stack (was undecided) | [ADR 0011](../adr/0011-tech-stack.md) + `README.md` (snapshot) |

> **Net new content created** (not just relocated): the persistence schema (`DATABASE.md`), all Mermaid diagrams, the glossary, the open-item feature specs, and the documentation standard itself.

---

## C. Gaps — documentation that is still missing

Ordered roughly by priority. "Home" = where it should live once written.

| ID | Missing doc | Why it matters | Home | Priority |
|----|-------------|----------------|------|----------|
| GAP-3 | **Interaction-queue ADR** | The brief marks it `core` (relevance/priority/interrupt + inaction timer) but **no ADR defines it** | new ADR (npc-behaviour) | Medium |
| GAP-4 | **Bible → card compile step** | ADR 0001 says "need a compile step" to derive the runtime card from the bible; nowhere specified | new ADR or `features/npc-behaviour/` | Medium |
| GAP-5 | **Disposition-priors spec** | ADR 0002 references prior functions that seed new edges from target traits; shape undefined | `features/npc-behaviour/` | Medium |
| GAP-6 | **Shared config homes** | Severity rubric (0005) + universal-priors library + register-archetype library need a storage/authoring home | `DATABASE.md` / seeders (O4) | Medium |
| GAP-7 | **Source-bible location standard** | Bibles sit at repo root; not standardized (a future `content/bibles/<slug>.md`?) | standard + `features/npc-behaviour/` | Low |
| GAP-8 | **Player card spec** | Player is appearance-only with no outgoing edges; the card shape isn't documented | `features/npc-behaviour/` | Low |
| GAP-9 | **Root project `README.md`** | Repo root has no README (only `docs/README.md` exists) | repo root | Low |
| GAP-10 | **Lorebook system** | Brief calls it an "existing system"; for this app it's unspecified | `features/session/` | Low |
| GAP-11 | **Cost/latency budget** | ~10+ LLM calls per 3-NPC beat; caching/batching plan beyond 0007 | `runbooks/cost-latency-diagnostics.md` | Low |
| GAP-12 | **Code-documentation standard** | The source standard references a `code-documentation` skill for inline docstrings; this project has none yet | `adr/` or `guides/` | Low |

### Closed

| ID | Was | Closed by |
|----|-----|-----------|
| GAP-1 | Tech-stack ADR | [ADR 0011](../adr/0011-tech-stack.md) (2026-06-04) |
| GAP-2 | Persistence ADR | [ADR 0012](../adr/0012-persistence-schema.md) (2026-06-04) |

> Tracked placeholders for items already stubbed are in [PLACEHOLDER_TRACKING.md](./PLACEHOLDER_TRACKING.md). Close a gap by writing the doc in its "Home" and striking the row.
