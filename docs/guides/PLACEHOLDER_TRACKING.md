# Placeholder Tracking

Open placeholders and design divergences. Each entry: what, why it's a placeholder, where it resolves.

> Update this whenever you leave a "to be decided / temporary" mark in docs or code.

## Active placeholders

| ID | Placeholder | Why | Resolves in |
|----|-------------|-----|-------------|
| PH-6 | Source bibles live at repo root (`luna-archi.md`); the standard home is now `content/bibles/` ([ADR 0018](../adr/0018-character-creation-pipeline.md)) but `luna-archi.md` is not yet moved | Authoring source; home now decided, migration pending | migrate `luna-archi.md` → `content/bibles/` at the build round |
| PH-7 | `api/`, `testing/`, `manual-qa-check/`, `runbooks/`, `reviews/` are empty skeletons | No app built yet (build is a separate session) | when the build starts |
| PH-8 | Severity rubric + drift/elapsed **tunables** have no config home | `universal_priors` exists (DATABASE.md §3.8) and **`model_profiles`** now homes the LLM tier→slug config ([ADR 0017](../adr/0017-llm-orchestration-openrouter.md)); the rubric values + drift/elapsed tunables (0005/0014/0015) still need a home | config ADR / seeders (see [GAPS](../adr/GAPS.md) audit) |
| PH-9 | Manual-mode cards may have no source bible (`bible_path` null) | [ADR 0018](../adr/0018-character-creation-pipeline.md) allows a hand-authored card as its own source — a deliberate divergence from [ADR 0001](../adr/0001-character-data-three-layer-separation.md)'s "bible is the single source of truth" (`knowledge_boundary` still mandatory) | accepted; revisit only if manual cards prove hard to maintain |

## Resolved

| ID | Placeholder | Resolved by |
|----|-------------|-------------|
| PH-1 | `DATABASE.md` was DRAFT, not an ADR | [ADR 0012](../adr/0012-persistence-schema.md) (2026-06-04); DATABASE.md is now its living snapshot |
| PH-2 | Tech stack had no ADR | [ADR 0011](../adr/0011-tech-stack.md) (2026-06-04) |
| PH-3 | DB driver MySQL vs MariaDB unpinned | [ADR 0011](../adr/0011-tech-stack.md): MariaDB 11.7 (dev) / MySQL-8-compatible schema |
| PH-4 | `beats` / `scenes` / `chapters` tables were skeletal | [ADR 0015](../adr/0015-beat-document-and-boundaries.md) + [DATABASE.md](../architecture/DATABASE.md) §3.10–3.12 |
| PH-5 | `internal_states` / `active_emotions` columns were skeletal | [ADR 0014](../adr/0014-internal-state-schema.md) + [DATABASE.md](../architecture/DATABASE.md) §4.5–4.6 |
