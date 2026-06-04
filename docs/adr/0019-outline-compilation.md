# 0019 — Outline compilation (free outline → chapters / scenes / beats)

- **Status:** Proposed
- **Date:** 2026-06-04

## Context

[ADR 0015](0015-beat-document-and-boundaries.md) fixed the **shape** the narrator loop consumes — `story → chapters → scenes →
beats`, where a beat is `{ intent, goal, word_budget, nudge_target }` and a scene carries
`pov_mode / pov_anchor / tone / elapsed_bucket`. It also fixed the **authoring stance**: the human
is a **pantser of route, not structure** ([ADR 0008](0008-psychological-nudge.md)). But it never said how an author *gets* from
the way they actually write — a **free-text outline** ("Ch 1: Luna and the player meet in the
workshop; she deflects the gloves question; Vixia watches") — to the structured rows the engine
needs. Today that structured beat document would have to be hand-built row by row.

This mirrors exactly the [ADR 0013](0013-authoring-and-compile-pipeline.md) / [ADR 0018](0018-character-creation-pipeline.md) problem on the **directing** side: a compile step
from a human-friendly source into reviewed, committed authoring rows. The developer wants both: *"my
written chapter outline converted to the app format … and I also can do it manually fully."*

## Decision

### 1. Outline compilation = the ADR 0013 pattern applied to beats

A new authoring-time compile step turns a **free outline** into the [ADR 0015](0015-beat-document-and-boundaries.md) hierarchy, through
the **same shared review gate**:

```
SOURCE (human-friendly)            COMPILE (LLM-assisted)              REVIEW            COMMIT
chapter_outlines.raw_text  ──────► draft chapters / scenes / beats ──► review gate ────► authoring rows
  (free prose outline)              { number, title, pov_default,      accept/edit/      chapters / scenes
                                      word_cap, scenes[ pov_mode,      reject            / beats (ADR 0015)
                                      pov_anchor, tone, elapsed,
                                      beats[ intent, goal,
                                      word_budget, nudge_target ]]}
```

A new `outline_compile` `producer_type` joins `delta` / `nudge_compile` / `beat_record` /
`card_compile` / `bible_generate` on `review_items`. The compile runs on the [ADR 0017](0017-llm-orchestration-openrouter.md) `compiler`
role. The LLM **proposes** the structured tree; the human **accepts / edits / rejects** before any
row is committed — the same `propose → review → commit` everywhere else uses.

### 2. The raw outline is stored: `chapter_outlines`

The author's free text is first-class and kept, so a recompile (the outline changed, or the author
wants a different breakdown) re-runs from source — exactly like [ADR 0013](0013-authoring-and-compile-pipeline.md)'s full recompile:

```
chapter_outlines:
  story_id      FK
  chapter_id    FK NULL    # set once a chapter is compiled out of this outline (an outline may span chapters)
  raw_text      LONGTEXT   # the author's free outline, verbatim
  status        draft | compiled | manual
  review_item_id FK NULL   # the outline_compile review record
```

`status: manual` records an outline that was authored straight into structured beats with no compile
(see §3). An outline is **not** injected anywhere at runtime — it is an authoring artifact, like a
bible; only its **compiled** beats reach the loop.

### 3. The full manual path is first-class, not a fallback

The author may **skip the compile entirely** and write `chapters` / `scenes` / `beats` rows
directly through the same forms the review gate edits — populating `intent` / `goal` /
`word_budget` / `pov` / `tone` / `elapsed_bucket` by hand. Manual and compiled beats are
**indistinguishable** downstream; the narrator loop ([ADR 0016](0016-narrator-agent-and-turn-loop.md)) cannot tell how a beat was
authored. This honors [ADR 0015](0015-beat-document-and-boundaries.md)'s rejection of *forced* full structure while still allowing
*chosen* full structure.

### 4. What the compiler infers vs. what stays authored

- **Inferred + reviewed:** scene breakdown, per-beat `goal` strings, suggested `word_budget`
  (from the chapter `word_cap` and beat count), `elapsed_bucket` (= `narrator_inferred` source per
  [ADR 0015](0015-beat-document-and-boundaries.md) §6, human-confirmable), and which beat (if any) carries a `nudge_target`.
- **Stays the author's:** the omniscient `intent` free-text per beat ([ADR 0015](0015-beat-document-and-boundaries.md) keeps it
  pantser-friendly), the `pov_default` per chapter, and the chapter `word_cap`. The compiler may
  *draft* these but they are the load-bearing authorial signals, always surfaced for edit.

The omniscient `intent` is **authoring-side only** and never injected raw — it becomes a
leak-checked nudge at runtime ([ADR 0015](0015-beat-document-and-boundaries.md) §2 / [ADR 0008](0008-psychological-nudge.md)). Outline compilation therefore touches
**no leak guard**; it produces author-side rows whose runtime exposure is already gated elsewhere.

### 5. Dependency order

A beat's `nudge_target` references a **character**, so a story's characters ([ADR 0018](0018-character-creation-pipeline.md)) should
exist before its outline is compiled (or the compiler leaves `nudge_target` null for the human to
fill once characters exist). `pov_anchor` likewise names a present character. No hard FK ordering is
forced — `nudge_target` / `pov_anchor` are nullable until resolved at review.

## Alternatives considered

- **Author beats directly only (no compile).** Rejected as the *only* mode: it forces the
  structure [ADR 0008](0008-psychological-nudge.md)/[0015](0015-beat-document-and-boundaries.md) deliberately avoid; kept as the first-class **manual** path.
- **Fully structured outline input (author writes JSON).** Rejected: same pantser violation; the
  free-text outline is the human-friendly source.
- **Discard the raw outline after compile.** Rejected: recompile needs the source; `chapter_outlines`
  keeps it, matching [ADR 0013](0013-authoring-and-compile-pipeline.md)'s "full recompile from source" stance.
- **A separate review surface for outlines.** Rejected: one shared review gate ([ADR 0003](0003-delta-engine-two-channels-and-appraisal-review.md)/[0012](0012-persistence-schema.md))
  serves every producer; outline compile is just another `producer_type`.
- **Compile straight to runtime without review.** Rejected: contradicts the model-independent,
  human-is-the-floor posture used by every other compile.

## Consequences

- A new **`chapter_outlines`** table + an **`outline_compile`** `producer_type` land in
  [DATABASE.md](../architecture/DATABASE.md); `chapters` / `scenes` / `beats` are unchanged (already fleshed by [ADR 0015](0015-beat-document-and-boundaries.md)).
- Outline compilation reuses the [ADR 0017](0017-llm-orchestration-openrouter.md) `compiler` role + the shared review gate; the manual
  path needs no LLM.
- The narrator loop ([ADR 0016](0016-narrator-agent-and-turn-loop.md)) is unaffected — it consumes committed beats regardless of how
  they were authored.
- Feature spec [O6](../features/directing/O6-outline-compiler.md) carries the authoring-UI detail (the outline editor, the review tree, the
  manual beat forms).
