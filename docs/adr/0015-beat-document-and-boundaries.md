# 0015 — Beat document + `BEAT_DONE` + boundary events

- **Status:** Proposed
- **Date:** 2026-06-04

## Context

ADR 0008 makes the **beat document** a hard dependency — the nudge is *derived* from a beat's
authorial intent, and the escalation ladder is *clocked by word budget + goal-not-met*. But no ADR
defines how a beat is authored, how the nudge is derived from it, what fires `BEAT_DONE`, or the
**scene/chapter boundary events** that programmatically trigger batched drift (ADR 0003) and decay
(ADR 0004). `DATABASE.md` carries `chapters / scenes / beats` as **skeletal** (ADR 0012 deferred the
detail to this work, "O2").

The authoring stance is fixed by ADR 0008: the human is a **pantser of *route*, not of structure** —
chapter outline, scenes, lore, and beats exist; drifting from the path is fine as long as the beat's
**goal** is the same; the hard pacing flag is **word count**.

## Decision

### 1. The hierarchy and the beat shape

`story → chapters → scenes → beats`. A **beat** is the smallest authored unit:

```
beat:
  intent:       "<free text, OMNISCIENT, author-side>"   # "Corner him about the fire; she mustn't learn it was arson"
  goal:         "<the anchor / satisfaction signal>"      # "Luna presses him about that night"
  word_budget:  <int>                                     # the pacing clock for this beat
  nudge_target: <character?>                              # optional; who (if anyone) the nudge is framed onto
  # pov / tone are INHERITED from the scene (ADR 0009 POV contract); not re-authored per beat
```

Free-text `intent` keeps it pantser-friendly; structured `goal` + `word_budget` give the engine the
two signals the ADR 0008 ladder needs. A typical beat targets **0–1** characters with a nudge;
everyone else runs pure self-simulation.

### 2. Nudge derivation (the producer ADR 0008 named)

A compile step turns the omniscient beat into a bounded nudge:

```
beat.intent  +  target's knowledge_boundary (ADR 0013/0001)
      │  compile + LEAK-CHECK at the assembler boundary (ADR 0007/0008)
      ▼
nudge { kind, level, text, target, goal, source: derived }
```

- The raw omniscient `intent` **never crosses** the boundary; only the leak-checked nudge does.
- `level` is **not authored** — it is set at runtime by the word-budget clock (§4).
- Hand-authored nudges (ADR 0008's other mode) bypass derivation but take the **same** leak-check.
- Nudge compile is a producer on the shared **review gate** (ADR 0012 already reserves it).

### 3. `BEAT_DONE` and goal measurement

`BEAT_DONE` fires when **either**:

- the **goal-satisfaction signal trips** — an **LLM judge** evaluates the prose so far against the
  free-text `goal` string and reports met/not-met (pantser-friendly; no rigid assertions). It is
  **human-reviewable** through the shared gate; or
- the **word budget is exhausted** and the ADR 0008 ceiling has been reached (the beat is force-landed).

Satisfying the goal also **dissolves the nudge** (ADR 0008 scope/lifetime).

### 4. The word-budget clock: warning, then hard override

Per-beat `word_budget`, two **tunable** thresholds, both clocking the ADR 0008 ladder:

```
prose accumulates against word_budget:
  at / near budget        → WARNING flag; the nudge ladder is climbing L0 → L3
  > 60% over budget        → HARD RULE: word count > 1.6 × budget with goal STILL unmet fires the
    (i.e. > 1.6 × budget)    ADR 0008 ceiling — ② narrator push → ③ player "Continue / Skip / Direct?"
                             → ④ break-glass directive — to force the beat to land.
```

The **chapter's** overall word cap remains the **outer hard flag** that forces a chapter wrap
(ADR 0008). Both the per-beat thresholds and the chapter cap are config.

### 5. Boundary events (the batched-subsystem triggers)

The chapter → scene → beat hierarchy emits explicit engine events; today only `BEAT_DONE` was named:

```
BEAT_DONE    → recorder commits the beat record (ADR 0010); per-beat appraisal (ADR 0005) emits
               edge-axis + emotion proposals; the nudge dissolves if its goal was met.
SCENE_DONE   → apply BATCHED DRIFT to edges (ADR 0003) + write the scene summary; AND if this scene
               declared a real elapsed gap (days+, §6) apply narrative-time DECAY (ADR 0004) + the
               emotion gap-drift (ADR 0014), scaled by the bucket.
CHAPTER_DONE → write the chapter log; swap to the next-chapter card snapshot (ADR 0013); AND if the
               chapter boundary itself declares a gap, apply DECAY + emotion gap-drift too. This is
               the state machine's BEAT_COMPLETE / chapter wrap.
```

**Time-based DECAY + emotion gap-drift are keyed to the elapsed bucket on whichever boundary carries
it** (a scene that declares "three weeks pass" decays at its own `SCENE_DONE`) — matching ADR 0004's
"scene/chapter boundaries." Ruptures (ADR 0003) still apply **immediately, in-scene**; only ordinary
*drift* is batched to `SCENE_DONE`.

### 6. Elapsed time is a coarse, sourced bucket — not a precise duration

Novels rarely state elapsed time cleanly, and filler chapters pass ~no time. So time is a **coarse
bucket**, never an exact duration:

```
continuous · hours · days · weeks · months · longer
```

**Sourced** three ways, in order (mirroring the player-delivery sourcing of ADR 0010):

1. **Authored** on the scene/chapter boundary when the author knows it.
2. **Narrator-inferred** from the prose ("the next morning", "weeks later"), human-confirmable at the
   boundary through the review gate.
3. **Default `continuous`** when neither — i.e. the scene flows on with no gap.

**Decay (ADR 0004) and the emotion gap-drift (ADR 0014) fire only on a real gap (`days`+).** A
`continuous` / filler scene or chapter changes nothing by time — only events move state. The bucket → decay
magnitude / gap-cap mapping is **shared tunable config** (like the ADR 0005 severity rubric).

## Alternatives considered

- **Fully structured beats.** Rejected: kills the pantser stance ADR 0008 is built around.
- **Author-defined assertions as the sole goal signal.** Rejected as sole mechanism: brittle for
  free-route prose; the LLM judge + review gate is pantser-friendly. (Assertions remain a possible
  future opt-in for precision-critical beats.)
- **Turn-count clock.** Already rejected by ADR 0008 in favour of word budget; unchanged here.
- **Authored precise durations / decay by beat count.** Rejected: novels are vague and ADR 0004
  already keys decay to *declared* time. The coarse sourced bucket fits real prose.
- **Decay on every boundary regardless of declared time.** Rejected: it would erode relationships
  across filler chapters where no time passes — exactly what the bucket prevents.

## Consequences

- `beats` is fleshed (`intent`, `goal`, `word_budget`, `nudge_target`); `scenes` carry the elapsed
  bucket + pov/tone; `chapters` carry the outline + word cap. Column detail lands in the
  `DATABASE.md` follow-on (the O2 deferral in ADR 0012).
- A **nudge-derivation compile step** (beat intent → leak-checked nudge) joins the assembler/review
  pipeline.
- **Boundary events become explicit engine signals** (`BEAT_DONE` / `SCENE_DONE` / `CHAPTER_DONE`),
  wired and sequenced in the narrator loop (ADR 0016).
- The **word-budget clock** (two tunable thresholds) and the **elapsed-time sourcing + review** are
  runtime behaviors the narrator loop (ADR 0016) runs; the **bucket → magnitude** mapping is shared
  config alongside the severity rubric.
- **Unblocks ADR 0016 (narrator loop)** — it consumes beats, runs the clock, fires the boundary
  events, and sequences appraisal / drift / decay around them.
