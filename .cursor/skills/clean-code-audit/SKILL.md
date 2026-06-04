---
name: clean-code-audit
description: Audit existing code against clean code standards and produce a prioritized refactoring plan. Use when the user asks to review code quality, audit clean code, plan refactoring, find code smells, improve code readability, or mentions clean code audit, refactor plan, or code review.
---

# Clean Code Audit

Systematically audit code against clean code rules and produce a structured refactoring plan. Designed for planning mode - analyze and propose, don't modify.

## Audit Workflow

### Step 1: Scope the audit

Determine what to audit. Ask if not clear:
- **Single file** → audit that file
- **Directory/feature** → audit all files in scope
- **Full project** → start with controllers, services, then models

### Step 2: Read and analyze

For each file in scope, check against these 7 categories (reference `.cursor/rules/clean-code-*.mdc` for detailed examples):

| # | Category | What to look for |
|---|----------|-----------------|
| 1 | **Naming** | Vague names, magic numbers, type prefixes, no value objects |
| 2 | **Functions** | Long methods, multiple responsibilities, flag args, side effects, >3 params |
| 3 | **Comments** | Redundant comments, commented-out code, missing intent comments |
| 4 | **Structure** | Variables far from usage, no vertical separation, long lines, broken indentation |
| 5 | **Design** | God classes, if/else chains instead of polymorphism, Law of Demeter violations, no DI |
| 6 | **Tests** | Missing tests, multiple asserts testing different concepts, shared state, slow tests |
| 7 | **Principles** | Code smells (rigidity, fragility, opacity), KISS violations, inconsistent patterns |

### Step 3: Classify findings

Rate each finding by severity:

- **Critical** — Actively causes bugs or blocks maintainability. Fix first.
- **Major** — Significant readability/design issue. Fix in next sprint.
- **Minor** — Style or preference improvement. Fix opportunistically (Boy Scout Rule).

### Step 4: Output the refactoring plan

Use this template for the output:

```markdown
# Clean Code Audit: [scope name]

## Summary
- Files audited: X
- Critical: X | Major: X | Minor: X

## Critical Findings

### [C1] [Category]: Brief description
- **File**: `path/to/file.php` (lines X-Y)
- **Problem**: What's wrong and why it matters
- **Proposed fix**: Concrete refactoring steps
- **Effort**: S / M / L

## Major Findings

### [M1] [Category]: Brief description
- **File**: `path/to/file.php` (lines X-Y)
- **Problem**: ...
- **Proposed fix**: ...
- **Effort**: S / M / L

## Minor Findings

### [m1] [Category]: Brief description
- **File**: `path/to/file.php` (lines X-Y)
- **Proposed fix**: One-line suggestion

## Recommended Order of Execution
1. [C1] — reason why first
2. [C2] — reason
3. [M1] — reason
...
```

## Audit Checklist (per file)

Copy and track progress:

```
- [ ] Naming: descriptive, no magic numbers, no prefixes, searchable
- [ ] Functions: small, single responsibility, few args, no flags, no side effects
- [ ] Comments: no redundancy, no commented-out code, intent explained where needed
- [ ] Structure: variables near usage, vertical density, short lines, consistent style
- [ ] Design: SRP, DI used, no Law of Demeter violations, no god classes
- [ ] Tests: exist, readable, fast, independent, repeatable, one concept each
- [ ] Principles: no code smells, KISS applied, patterns consistent
```

## Important Guidelines

- **Read-only**: Do NOT modify files. Only analyze and propose.
- **Be specific**: Always cite file path and line numbers.
- **Be pragmatic**: Reference the `laravel-pragmatic-abstraction` skill — don't propose over-engineered refactors.
- **Prioritize impact**: A rename that clarifies 50 usages > a structural change in a rarely-touched file.
- **Group related fixes**: If one file has 5 naming issues, group them as one finding.
- **Consider test coverage**: If proposing a change to untested code, note "add tests first" in the plan.
