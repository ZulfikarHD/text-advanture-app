# 0005 — Appraisal trigger taxonomy

- **Status:** Accepted
- **Date:** 2026-06-04

## Context

The appraisal step (ADR 0003) must decide, after an event, **which axes on which edges move,
in what direction, and whether it is drift or rupture** — and do it *in character*. It also
doubles as the **salience filter**: deciding what even registers. Authoring a full reaction
table per character would be thousands of rules; a pure-LLM free-for-all loses control,
consistency, and in-character salience.

## Decision

### Two layers: humanity + personality

- **Universal appraisal priors** (shared library): weak baseline human reactions — insult ->
  affection down, kindness -> affection up, threat -> fear up, broken promise -> trust down.
- **Character sensitivities** (on the card): this character's amplifiers, dampeners, and
  special cases. The card layer overrides/amplifies the universal one.

### Sensitivity structure

```yaml
sensitivity:
  id: threat_to_vixia
  detect: "anyone harms, threatens, demeans, or endangers Vixia"   # natural language; LLM matches
  targets: actor                  # actor | beneficiary | witnessed_third_party
  axes: { affection: down, trust: down }
  weight: high                    # salience multiplier
  channel: scales_with_severity   # drift_only | rupture_only | scales_with_severity
```

### Drift vs. rupture

`magnitude ~= weight x LLM-judged severity`, against a **shared severity rubric**:

| Severity | Magnitude | Channel |
|---|---|---|
| negligible | 0–1 | drift |
| minor | 1–3 | drift |
| notable | 3–8 | drift |
| major | 8–20 | rupture |
| defining | 20–50 | rupture (may latch a scar / break bounds) |

Some sensitivities are categorically `rupture_only` regardless of degree — **betrayal,
confession, abandonment** — because those are a *kind* of event, not a magnitude.

### Three behaviors

1. **Match-only salience.** No matched sensitivity (universal or card) -> no delta. Characters
   are numb to what they don't care about. (Cheaper, and in-character.)
2. **Multiple sensitivities may fire -> multiple proposals.** The same praise can hit
   `genuine_acknowledgment` (respect up) *and* `pitied_as_fragile` (affection down). Do not
   resolve — emit both. This is how the engine manufactures meaningful contradictions.
3. **Appraisal moves the number; register/mask handles expression.** Internal change and
   expressed behavior stay on separate tracks (e.g. affection rises but gratitude stays
   un-sayable behind the mask).

### Targeting (vicarious shifts supported)

`targets` may be the **actor**, the **beneficiary**, or a **witnessed third party** — so
watching A protect B can raise the observer's respect for A even though A never acted on the
observer. Enables NPC-to-NPC mesh dynamics.

### Runtime-installed sensitivities

Sensitivities have **two homes**:

- **Authored** — on the card, immutable.
- **Acquired** — installed at runtime by ruptures, stored in mutable state (e.g. the scar's
  `triggers:` from ADR 0004). A fear scar installs its own re-spike triggers.

So appraisal both **reads** sensitivities and, on ruptures, **writes** them. Effective
sensitivity set = card sensitivities + runtime-installed.

### Loop closure

The matched sensitivity **names itself as the `trigger` string** in the ADR 0003 delta
proposal. The Q4 "audit question" (*did this move my axes, and why?*) is exactly: scan event
against sensitivities -> matched one names the reason -> review gate -> audit log.

## Alternatives considered

- **Full per-character reaction tables.** Rejected: unmaintainable.
- **Pure-LLM, no authored sensitivities.** Rejected: loses control, consistency, salience.
- **Always-on universal appraisal pass.** Rejected for now: cost/noise; chose match-only.
- **Authored-only sensitivities.** Rejected: scars/trauma need to install new triggers.

## Consequences

- Cards carry a `sensitivities` list; a shared universal-priors library is needed.
- The severity rubric is shared, tunable config.
- Vicarious targeting requires feeding third-party events into the appraisal context.
- The effective sensitivity set is partly **mutable state**, not just authored card data.
