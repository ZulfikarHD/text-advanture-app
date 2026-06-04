# O6 — Outline compiler (free outline → chapters / scenes / beats)

> **Status:** Proposed · **Domain:** directing · **Owning ADR(s):** [ADR 0019](../../adr/0019-outline-compilation.md) (outline compilation), depends-on [ADR 0015](../../adr/0015-beat-document-and-boundaries.md) (beat shape), [ADR 0017](../../adr/0017-llm-orchestration-openrouter.md) (LLM) · **Last Updated:** 2026-06-04

## Summary

Turns the way an author actually writes — a **free-text outline** ("Ch 1: Luna and the player meet
in the workshop; she deflects the gloves question; Vixia watches") — into the structured
[ADR 0015](../../adr/0015-beat-document-and-boundaries.md) hierarchy (`chapters` / `scenes` / `beats`) the narrator loop consumes, through the
shared review gate. A full **manual** path lets the author write structured beats directly instead.

## Goal & non-goals

- **Goal:** compile a free outline into reviewed, committed `chapters`/`scenes`/`beats`; keep the
  raw outline for recompile; support a fully manual authoring path.
- **Non-goals:** the beat *shape* and boundary events ([ADR 0015](../../adr/0015-beat-document-and-boundaries.md)); the runtime nudge derivation
  ([ADR 0008](../../adr/0008-psychological-nudge.md)/[0015](../../adr/0015-beat-document-and-boundaries.md) §2); the narrator loop ([ADR 0016](../../adr/0016-narrator-agent-and-turn-loop.md)).

## Behavior

Authoring-time only. `chapter_outlines.raw_text` → `compiler` role drafts the tree (`chapters`
`{number,title,pov_default,word_cap}` → `scenes` `{pov_mode,pov_anchor,tone,elapsed_bucket}` →
`beats` `{intent,goal,word_budget,nudge_target}`) → `outline_compile` review → commit. The author
accepts/edits/rejects per node. **Manual path:** skip the compile, author beats directly through the
same forms (`status: manual`). Manual and compiled beats are indistinguishable downstream.

- **Inferred + reviewed:** scene breakdown, per-beat `goal`, suggested `word_budget`, `elapsed_bucket`
  (`narrator_inferred` source, [ADR 0015](../../adr/0015-beat-document-and-boundaries.md) §6), candidate `nudge_target`.
- **Stays the author's:** omniscient `intent` free-text, `pov_default`, chapter `word_cap`.

## Data touched

New: `chapter_outlines`, `review_items.producer_type = outline_compile`, `outline_status` enum.
Writes (on commit): `chapters`, `scenes`, `beats` (unchanged shapes, [ADR 0015](../../adr/0015-beat-document-and-boundaries.md)). See
[../../architecture/DATABASE.md](../../architecture/DATABASE.md) §3.10–3.12, §3.15.

## Agent / isolation impact

Runs as the **authoring-time compiler**. Produces author-side rows only; the omniscient `intent` is
never injected raw (it becomes a leak-checked nudge at runtime, [ADR 0015](../../adr/0015-beat-document-and-boundaries.md) §2). Touches **no**
leak guard. The raw outline, like a bible, is never injected at runtime.

## Acceptance criteria

- [ ] A free outline compiles into a reviewable `chapters`/`scenes`/`beats` tree; nothing commits
  until accepted.
- [ ] The raw outline is stored and a recompile re-runs from it.
- [ ] The manual path commits structured beats with no LLM call (`status: manual`).
- [ ] `nudge_target` / `pov_anchor` may stay null until characters exist, then resolve at review.

## Open questions

- Should one outline span multiple chapters, or one outline per chapter?
- Default `word_budget` heuristic — split `word_cap` evenly across beats, or LLM-estimated per beat?

## Related Documentation

- ADR: [0019](../../adr/0019-outline-compilation.md) · [0015](../../adr/0015-beat-document-and-boundaries.md) · [0008](../../adr/0008-psychological-nudge.md) · [0017](../../adr/0017-llm-orchestration-openrouter.md)
- Architecture: [DATABASE.md](../../architecture/DATABASE.md) (§3.15 `chapter_outlines`)
- Open items: [GAPS O6](../../adr/GAPS.md)
