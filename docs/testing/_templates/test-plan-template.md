# {CODE} — {Feature} Test Plan

> **Feature:** [../../features/{domain}/{CODE}-{slug}.md](../../features/README.md) · **Last Updated:** {YYYY-MM-DD}

## Scope

What is and isn't covered.

## Preconditions / fixtures

Seed data, session state, character cards/edges needed.

## Cases

| # | Case | Steps | Expected | Type (unit/feature/e2e) | Automated test |
|---|------|-------|----------|-------------------------|----------------|
| 1 | … | … | … | … | `tests/...` |

## Safety assertions (if applicable)

- [ ] No `true_state` or hidden fact crosses an agent boundary (isolation, ADR 0007/0009/0010).
- [ ] Unhedged mental-state assertions in `surface` are rejected (hedged-attribution rule).
- [ ] Every committed axis delta carries a non-null `trigger` (ADR 0003).

## Out of scope

…
