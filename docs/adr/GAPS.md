# Open items — measured against the flow

> Each item below has a lean feature spec under [`../features/`](../features/README.md):
> O1 → `features/narrator/O1-narrator-loop.md`, O2 → `features/directing/O2-beat-document.md`,
> O3 → `features/session/O3-internal-state-schema.md`, O4 → `features/session/O4-persistence-and-ui.md`.
>
> **Update (2026-06-04): O1–O3 are now closed** by ADR 0016 / 0015 / 0014 (and the card/compile gap by
> ADR 0013). What remains is O4 + the items surfaced by the coherence audit.

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

The **NPC behaviour subsystem (ADR 0001–0010)** and the **narrator / authoring side (ADR 0013–0016)**
are now **designed** (all `Proposed`). What remains is implementation + the UI/orchestration items below.

---

## Closed at the design level (now ADRs · all `Proposed`)

| Item | Closed by |
|------|-----------|
| **O1** — Narrator internals (prose under POV contract, handoff detection, mesh-awareness rule, witness tagging, resume anchor, in-loop sequencing) | [ADR 0016](0016-narrator-agent-and-turn-loop.md) |
| **O2** — Beat document + `BEAT_DONE` + scene/chapter boundary events + nudge derivation | [ADR 0015](0015-beat-document-and-boundaries.md) |
| **O3** — Internal-state schema (active emotions + baseline drift, mood, motivation, masks; appraisal writes emotions) | [ADR 0014](0014-internal-state-schema.md) |
| **Authoring & compile pipeline** (bible → card / registers / sensitivities / lorebook + reveal ledger) — an unfiled gap surfaced mid-design | [ADR 0013](0013-authoring-and-compile-pipeline.md) |
| **LLM client / orchestration** — OpenRouter gateway, thin `LlmClient`, model-role tiering, `model_profiles` / `llm_calls` (resolves the ADR 0011 "AI SDK candidate" note; partially closes O4 cost/latency) | [ADR 0017](0017-llm-orchestration-openrouter.md) |
| **O5** — Character creation (AI / manual / hybrid) + the `character_archetypes` library | [ADR 0018](0018-character-creation-pipeline.md) |
| **O6** — Outline compilation (free outline → chapters / scenes / beats; manual path) | [ADR 0019](0019-outline-compilation.md) |
| **O7** — Prompt block registry (machine-readable block specs driving assembly + docs) | [ADR 0020](0020-prompt-block-registry.md) |

The column-level schema for all of these is in [`../architecture/DATABASE.md`](../architecture/DATABASE.md).

---

## Open

### O4 — UI + orchestration + cost/latency
- **UI** — prose display, player input (incl. a **delivery/tone** channel), the relationship viewer
  (fed by the 0003 audit log), and the **single review-gate surface** shared by deltas (0003),
  emotion proposals (0014), nudge compile (0008), beat records (0010), and **card compiles** (0013).
- **Compile→act orchestration** — sequencing the many LLM calls per beat (narrator ×2 + per-NPC ×2),
  with caching/batching beyond 0007's per-block note (cost / latency). The **client** is now settled
  ([ADR 0017](0017-llm-orchestration-openrouter.md): OpenRouter + `LlmClient`, model-role tiering, `llm_calls` log); only the
  **sequencing/queueing/batching** remains for the orchestration ADR.
- Home: a new **UI ADR** + an **orchestration ADR**. (Persistence + tech stack + LLM client are settled — 0011/0012/0017.)

### Surfaced by the 2026-06-04 coherence audit
- **Interaction-queue mechanics** — relevance → priority (`RESPOND_NOW / WAIT / SILENT / INTERRUPT`)
  + the player-inaction timer. Referenced by ADR 0016 §5 but never got its own ADR. Home: fold into
  the orchestration ADR, or a small **Interaction-queue ADR**.
- **Shared tunable config** — a home + format for the severity rubric (0005), elapsed-time buckets
  (0015), and emotion drift caps (0014). Home: a config ADR / seeders (tracked as PH-8).
- **Offline compile tooling** — the bible→card command ADR 0013 assumes. The authoring *surfaces*
  are now designed (character creation [O5](0018-character-creation-pipeline.md), outline compilation [O6](0019-outline-compilation.md)); the CLI/command + UI are implementation.
- **Authoring content** — only Luna's bible exists; the engine needs ≥2 characters (Vixia is
  referenced throughout). Content, not architecture.
- **Auth / multi-user scope** — single-author vs multi-user is undecided (the starter kit ships
  Fortify). Settle when the UI ADR lands.

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
