# Placeholder Tracking

Open placeholders and design divergences. Each entry: what, why it's a placeholder, where it resolves.

> Update this whenever you leave a "to be decided / temporary" mark in docs or code.

## Active placeholders

| ID | Placeholder | Why | Resolves in |
|----|-------------|-----|-------------|
| PH-4 | `beats` / `scenes` / `chapters` tables are **skeletal** | O1/O2 not designed | O1, O2 ADRs |
| PH-5 | `internal_states` / `active_emotions` columns are skeletal | O3 not designed | O3 ADR / 0001 amendment |
| PH-6 | Source bibles live at repo root (`luna-archi.md`) | Authoring source; location not standardized | optional `content/bibles/` move (audit GAP-7) |
| PH-7 | `api/`, `testing/`, `manual-qa-check/`, `runbooks/`, `reviews/` are empty skeletons | No app built yet (build is a separate session) | when the build starts |
| PH-8 | Severity rubric + universal-priors library not yet stored | ADR 0005 references shared config; home undecided | O4 / seeders |

## Resolved

| ID | Placeholder | Resolved by |
|----|-------------|-------------|
| PH-1 | `DATABASE.md` was DRAFT, not an ADR | [ADR 0012](../adr/0012-persistence-schema.md) (2026-06-04); DATABASE.md is now its living snapshot |
| PH-2 | Tech stack had no ADR | [ADR 0011](../adr/0011-tech-stack.md) (2026-06-04) |
| PH-3 | DB driver MySQL vs MariaDB unpinned | [ADR 0011](../adr/0011-tech-stack.md): MariaDB 11.7 (dev) / MySQL-8-compatible schema |
