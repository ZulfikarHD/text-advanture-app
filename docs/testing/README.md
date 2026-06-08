# Testing

Test plans & QA checklists, one per feature, that map to automated tests (PHPUnit + Vitest).

> Test plans are added alongside each feature as it ships (e.g. `S-3.1.1`, `S-4.1.1`, `S-4.2.2`, `S-5.1.1`). Backend tests are PHPUnit under `tests/Feature` / `tests/Unit`.

## Naming

```
testing/{CODE}-{slug}-test-plan.md     # e.g. O1-narrator-loop-test-plan.md
```

## Authoring

Copy [_templates/test-plan-template.md](./_templates/test-plan-template.md). Each plan links back to its `features/{domain}/{CODE}-*.md` spec.

## Engine-specific testing notes

- **Leak guards are safety-critical** — every test plan touching the assembler (0007), POV projection (0009), or recorder (0010) MUST assert no `true_state` / hidden fact crosses an agent boundary, and that the hedged-attribution rule rejects unhedged "is sad / is lying".
- **Determinism** — LLM calls are non-deterministic; assert *structural* invariants (isolation, hedging, schema), not exact prose.
