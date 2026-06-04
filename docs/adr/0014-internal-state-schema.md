# 0014 — Internal-state schema (the `[SELF]` layer)

- **Status:** Proposed
- **Date:** 2026-06-04

## Context

ADR 0001 names a per-character **internal state** layer — "mutable, per character, transient
(active emotions, motivation, masks); decays on its own clock, separate from relationship edges" —
and several ADRs already *depend* on it:

- ADR 0007 injects it every NPC turn as the `[SELF]` block (mood, active emotions, motivation).
- ADR 0006 step 4 reads "current emotional state" to modulate the register **surface**.
- ADR 0010 reads awareness + mask when deriving how much of `true_state` shows.
- The interaction queue (brief) checks `motivation`.

But **no ADR defines its shape**, and there is a real hole: ADR 0005 appraisal moves **edge axes**,
not internal emotions — so nothing *writes* transient emotion. `DATABASE.md` carries
`internal_states` + `active_emotions` as **skeletal** tables (ADR 0012 deferred their detail to this
work). This ADR finishes the layer that already exists; it does **not** invent a new one.

## Decision

### 1. Three fields on `internal_states`, one child table for emotions

`internal_states` (per session × character): `mood`, `motivation`, `masks`, plus a drift clock
marker. `active_emotions` is its child (one row per live feeling). Per-character and transient —
**distinct from** the per-pair relationship edges (ADR 0002) and their narrative-time clock
(ADR 0004).

### 2. `active_emotion` shape

```
active_emotion:
  emotion:    "<free-text label>"   # guilt, anxious, startled, fond, dread — author/appraisal vocabulary
  intensity:  0..100
  baseline:   0..100                # the RESTING level for this emotion on this character
                                    #   0 for a spontaneous acute feeling; non-zero for a CHRONIC one
                                    #   (Luna's low-grade guilt) — seeded from the card
  source:     appraisal | rupture | authored   # what installed it
  installed_at, last_clocked_at
```

Emotion labels are **free text** (max expressive range), unlike the fixed register dimensions.

### 3. The writer: appraisal also emits emotion proposals

The hole is closed by extending **appraisal (ADR 0005)**: the same per-event pass that proposes
**edge-axis deltas** also proposes **emotion deltas**, through the **same review gate** (ADR 0003).
An event that moves an edge ("he dodged the question") can also install/raise an internal emotion
("anxious"). Both are proposals with a mandatory trigger; the human accepts/edits/rejects.

- A **rupture** may install an acute spike directly (in-scene, like edge ruptures).
- **Authored** emotions seed chronic baselines on the card.

### 4. Emotions do not latch

Scars / latched floors are an **edge** mechanism (ADR 0004). Internal emotions never scar — they
revert toward baseline. A strong or recurring emotion can *feed* an edge delta (that is appraisal's
job), but the emotion itself leaves no permanent floor.

### 5. The own clock: small, bounded, baseline-reverting drift

Emotions move on their **own clock**, separate from the edge clock. The clock is deliberately
**gentle and bounded** — the baseline is always the true attractor:

```
ON-SCREEN  (events known)
  primary mover = appraisal (§3). Between events, each boundary applies a SMALL reversion
  toward baseline (cap ~±3 points, tunable) so an acute feeling eases off when nothing sustains it.

OFF-SCREEN GAP  (timeskip / between sessions — what happened is UNKNOWN)
  same SMALL bounded step, but with a RANDOM up/down component within the cap (we don't know
  what happened off-screen). It is mean-reverting (pulled toward baseline) and CLAMPED — a gap
  can never swing an emotion by more than the cap (default ±3), and the drift caps out rather
  than scaling without limit over long gaps. The baseline stays true; the value cannot run away.

EXPLICIT OVERRIDE
  if the continuation NARRATES the gap ("three weeks, they barely spoke" / "last meeting went
  well"), that explicit signal sets the value deterministically and NO random roll is taken.
```

The magnitude cap is **shared, tunable config** (like the ADR 0005 severity rubric). This model is
**scoped to emotions only** — ADR 0004's deterministic edge decay is unchanged.

### 6. `mood` is a derived rollup (+ optional override)

`mood` is **computed from** `active_emotions` (dominant or blended) — one source of truth, always
consistent with the live feelings. An optional **`mood_override`** lets the author pin it when a
scene needs it. `mood` feeds the register **surface** modulation (ADR 0006 step 4) and the `[SELF]`
block; it never changes the register **grammar**.

### 7. `motivation`

A **short structured** field — a current drive / goal (optional `source`), not a planner. Read by
the **interaction queue** (relevance / "motivation strong?") and surfaced in `[SELF]`.

### 8. `masks`

`internal_states.masks` = a list of `{ scope: global | state, condition, effect, source }`:

- **global** — a card-trait mask always in force (e.g. "cannot voice sincere gratitude").
- **state** — driven by an active emotion (guilt / grief / shame), an external obligation
  (sworn to secrecy), or self-deception (a feeling below its awareness threshold).

**Topic-scoped** masks stay on the **edge** as `topic_flags` (ADR 0002); this layer holds the
**global + emotion-driven** masks. Together they are the `[MASKS]` block (ADR 0007) and the
expression gate (ADR 0006 step 5).

### 9. The nudge `mood` is not a stored emotion

A nudge of kind `mood` (ADR 0008) is **beat-scoped authorial bias** — a transient *input* to the
behavior equation, framed as the character's own coloring. It is **not** written into
`active_emotions`. Stored emotions are the character's *simulated* state (written by appraisal);
the nudge is *direction/pressure* for one beat. They resolve together but are stored apart.

## Alternatives considered

- **A separate emotion-appraisal pass.** Rejected: doubles LLM cost and splits one event's reaction
  across two passes; folding emotion proposals into the existing appraisal is cheaper and coherent.
- **Recorder writes emotion from `true_state`.** Rejected: the recorder's job is the *observable*
  surface + private snapshot, not driving the simulation; appraisal is the natural mover.
- **Independent `mood` field.** Rejected: two sources of truth that drift apart; derived + override
  gives control without the desync.
- **Deterministic decay-to-baseline over gaps (the original O3 option).** Rejected per the off-screen
  argument: we don't know what happened off-screen, so a small bounded random wobble around the
  baseline is more honest than a confident fade — while still respecting the baseline.
- **Unbounded / time-scaling gap drift.** Rejected: a long gap could swing an emotion wildly; the
  cap (≈±3) keeps it small and keeps the baseline true.
- **Emotions that latch (emotional scars).** Rejected: scars are an edge concept (ADR 0004);
  internal emotions revert, and durable change is recorded on the edge instead.

## Consequences

- **Appraisal (ADR 0005) gains an emotion-proposal output** alongside axis deltas, sharing the
  review gate and the mandatory-trigger rule.
- `DATABASE.md` `active_emotions` gains **`baseline`** and **drift params** (reversion + random cap),
  replacing the placeholder `decay_rate`; `internal_states` pins `mood` (derived, with nullable
  `mood_override`), structured `motivation`, `masks` JSON, and a `last_clocked_at` marker. These are
  the O3 column details ADR 0012 deferred — they land in the `DATABASE.md` follow-on.
- The own-clock drift needs a **shared tunable cap** in config (the ≈±3 bound) and a roll only on
  off-screen gaps; explicit-narration override is wired in the narrator loop (O1 / ADR 0016).
- The `[SELF]` block (ADR 0007) now has a fully-specified source: mood (derived) + active emotions
  + motivation + masks.
- **Unblocks** ADR 0015 (beat document — its nudge `mood` kind now has a clear boundary) and
  ADR 0016 (narrator loop — where the gap roll, override, and clock fire).
