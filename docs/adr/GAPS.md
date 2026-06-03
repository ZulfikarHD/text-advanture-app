# Open items — measured against the flow

> **Reset note (2026-06-04).** An earlier version of this file was a 16-item "architecture audit"
> that treated the engine like a generic backend and inflated the list with subsystems the design
> **already has** (it called the session state machine a missing "conductor," and the context-memory
> layers a missing "memory subsystem"), plus a Luna-specific author-note ("the Pile") promoted to an
> engine gap. That drifted from what this project actually is. This file is now scoped to what is
> **genuinely open against the brief's flow** — the same shortlist the brief's "What is not yet
> decided" already names. The removed items, and *why* they weren't gaps, are recorded at the bottom
> so the correction is traceable.

The flow (the spine everything hangs off):

```
[SESSION_START] → [NARRATOR_TURN] prose → scan for handoff
   ├ [PLAYER_MOMENT]  input
   ├ [NPC_MOMENT]     interaction queue → NPC turn(s)
   └ [BEAT_COMPLETE]  chapter wrap
→ [NARRATOR_RESUMES]
```

The **NPC behaviour subsystem (ADR 0001–0010) is built**. What's open is everything *around* it that
makes the loop run.

---

## Open

### O1 — Narrator internals
The narrator is the spine of the flow and is still undesigned. Needs:
- **Prose generation** under the chapter **POV contract**.
- **Handoff detection** — how `NARRATOR_TURN` decides `PLAYER_MOMENT` vs `NPC_MOMENT` vs `BEAT_COMPLETE`.
- **Mesh-awareness rule** — the prompt rule that keeps the narrator from revealing what a character
  wouldn't know (this is the brief's existing soft rule; it lives here as a prompt directive, **not**
  a separate "leak-guard subsystem").
- **Witness tagging** — the per-beat `witnessed_by` + fidelity the assembler (0007) and recorder
  (0010) already consume.
- **Resume anchor** wiring (the brief's micro-continuity block).
- **In-loop sequencing** — where appraisal (0003/0005), decay (0004), and the interaction queue fire
  within a beat. (This is "define the loop," not a new orchestrator — the state machine already is it.)
- Touches: brief (session state machine, three-agents), 0003, 0004, 0005, 0007, 0008, 0010.
- Home: a new ADR — **Narrator agent + turn loop**.

### O2 — Beat document + `BEAT_DONE` + boundary events
- Authoring format for a beat: **intent / goal / word-budget** (the word-budget + goal-satisfaction +
  stall signals that the nudge ladder, ADR 0008, already depends on).
- How the **psychological nudge is derived** from a beat.
- What fires **`BEAT_DONE`**.
- The **scene/chapter boundary events** that programmatically trigger drift-batching (0003) and decay
  (0004) — today only `BEAT_DONE` is named; the chapter→scene→beat hierarchy needs explicit events.
- Touches: brief, 0003, 0004, 0008.
- Home: a new ADR — **Beat document + boundaries**.

### O3 — Internal-state schema
The one place the "complete" NPC subsystem is actually incomplete: ADR 0001 *names* a per-character
internal-state layer and the assembler injects it as `[SELF]` every NPC turn (0007), 0006 step 4 reads
"current emotional state," and 0010 reads awareness+mask — but **no ADR defines its shape**: an active
emotion, `motivation` (referenced by the interaction queue and `[SELF]`), the mask data, what *writes*
transient emotions (0005 moves edge axes, not internal emotions), and the "own clock" decay. Finish the
layer that already exists; don't invent a new one.
- Touches: 0001, 0004, 0005, 0006, 0007, 0010.
- Home: amend 0001 (or a small new ADR) — **Internal-state schema**.

### O4 — Session persistence + tech stack + UI
Brief-named and still open:
- **Persistence** — what's saved/loaded; DB choice.
- **Tech stack** — framework, hosting, and the **compile→act** orchestration from 0007.
- **UI** — prose display, player input, the relationship viewer (fed by the 0003 audit log), and the
  **single review-gate surface** shared by deltas (0003), nudge compile (0008), and beat records (0010).
- **Cost/latency budget** — a 3-NPC beat is ~10+ LLM calls; a holistic caching/batching plan beyond
  0007's per-block caching note. (A planning concern, not a blocker.)
- Home: deferred until the loop is real (tech-stack ADR + UI ADR).

---

## Removed from the earlier audit — *not* architecture gaps

| Was | Why it's not a gap |
|-----|--------------------|
| "No conductor / orchestration subsystem" | The **session state machine is the conductor.** The only real residue (in-loop sequencing) folds into **O1**. |
| "Narrator→player leak-guard subsystem" | The narrator's anti-leak is the **mesh-awareness prompt rule** (already in the brief), folded into **O1** — not a structural guard. |
| "Mutable epistemic state + memory-retrieval subsystem" | The brief's **context-memory layers** (immediate / scene-summary / chapter-log / lorebook) already are the memory model. Mid-session learning is a small detail to settle when we wire **O1**, not a new subsystem. |
| "Appraisal isolation (behavior-level leak)" | Appraisal already consumes the **witnessed** scene; a one-line constraint when designing the loop (**O1**), not a standalone gap. |
| Cross-ADR items (player-as-target legibility, register threshold-selector field, `base_opacity` smear, consolidated schema) | Mostly artifacts of the **0009/0010 over-reach** (and Luna-canon specifics like the L5 trust ladder). Revisit only if/when we re-scope the narrator-prose framing — deliberately deferred for now. |
| "The Pile" (suppressed observations) | An **author-note detail for one example character (Luna)**, not an engine requirement. If it ever generalizes, it rides on O3. |
| "Physical / health / world state model" | **Out of scope for v1** unless you say otherwise (canon-specific: Crystal Hollow condition, the gloves). |
