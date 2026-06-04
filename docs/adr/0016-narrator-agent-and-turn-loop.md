# 0016 — Narrator agent + turn loop

- **Status:** Proposed
- **Date:** 2026-06-04

## Context

The narrator is the **spine** of the session state machine, but its internals were never designed
(GAPS "O1"). The brief and ARCHITECTURE.md describe the *what* (NARRATOR_TURN → prose → handoff →
PLAYER / NPC / BEAT_COMPLETE), and ADR 0007 specified the **NPC** prompt — but the **narrator's own
prompt**, the **handoff mechanism**, the **mesh-awareness rule**, **witness tagging**, the **resume
anchor**, and the **in-loop sequencing** (where recorder, appraisal, drift, decay, and the
interaction queue fire within a beat) were all open. This ADR closes them.

It is **define-the-loop, not a new orchestrator** — the session state machine *is* the conductor
(GAPS "removed from the earlier audit"). With ADR 0013 (cards/lorebook), 0014 (internal state), and
0015 (beats/boundaries) settled, the loop now has everything it consumes.

## Decision

### 1. The narrator turn is two calls: prose, then recorder

```
NARRATOR TURN
  ① PROSE CALL (strong model)      single call; structured output:
        prose · handoff {PLAYER_MOMENT | NPC_MOMENT | BEAT_COMPLETE} · inferred elapsed bucket (0015 §6)
  ② RECORDER SUB-CALL (0010)       separate call on the narrator model; produces the two-layer record:
        { surface, true_state{char}, witnessed_by{char: full|overheard|partial}, pov_anchor }
        → hedged-attribution validation + review gate (does NOT trust the model)
```

Handoff detection is the **structured output of the prose call** — not a separate classifier pass.
The recorder is a **separate sub-call** (the ADR 0010 "step inside the narrator turn"), keeping the
hedged-attribution validation a clean target and the witness/`true_state` derivation isolated from
prose generation.

### 2. Narrator prompt assembly (the piece ADR 0007 never specified)

The narrator sees the **beat doc (full)** and the **full relationship mesh** — the NPC never does.

```
NARRATOR_CALL
 system:
   [POV CONTRACT]    scene pov_mode + pov_anchor + tone (ADR 0009)
   [MESH-AWARENESS]  full mesh → atmosphere / body-language / room-dynamics ONLY; never reveal what a
                     present character would not know; perceived reads MUST be hedged (§3)
   [BEAT]            current beat intent / goal / word_budget (ADR 0015)
   [DIRECTOR STATE]  word-budget warnings + ceiling pushes (engine → narrator, ADR 0008 ②)
   [LOREBOOK]        keyword-matched world facts (ADR 0013)
   [SCENE STATE]     present characters · immediate context (~2000 tok) · scene summary
 user:
   [RESUME ANCHOR]   scene type · last line · POV · tone  (when resuming, §5)
   "Continue narrating."
```

### 3. The mesh-awareness rule (a prompt directive, not a subsystem)

The narrator may use the mesh **only** for atmosphere, body-language, and room dynamics; it must
**never state a fact a present character would not know**, and every perceived read must be
**hedged** ("looks / seems"). This is the soft narrator-side anti-leak the brief already names; it
feeds straight into the recorder's structural **hedged-attribution rule** (ADR 0009/0010). It is a
directive, **not** a fourth leak guard.

### 4. In-loop sequencing (recorder first, then appraisal)

```
beat plays out (prose · player input · NPC actions)
   ↓
RECORDER commits surface + true_state + witnesses (ADR 0010)
   ↓
per PRESENT character: APPRAISAL reads ITS OWN projected `surface`
   (witness-filtered + POV-projected per ADR 0009, decoded via reads_target ADR 0006)
   → emits edge-axis + emotion proposals (ADR 0005 / 0014) → REVIEW GATE
   ↓
RUPTURES apply immediately in-scene; DRIFT is batched to SCENE_DONE (ADR 0003)
```

Recorder-first means appraisal always reasons over **witnessed evidence** (the projected `surface`),
never omniscient truth — the isolation constraint GAPS flagged for appraisal, made concrete.

### 5. Interaction queue, boundaries, resume

- **Interaction queue** (brief) runs at `NPC_MOMENT` and after each speech act: per present character
  relevance → priority (`RESPOND_NOW / WAIT / SILENT / INTERRUPT`) → interrupt check. If no one
  responds, the **player-inaction timer** escalates (short = others fill · medium = atmosphere beat ·
  long = "Continue / Skip / Direct?"). Each NPC turn is the ADR 0007 two-stage compile→act.
- **Boundary events** (ADR 0015) fire in-loop: `BEAT_DONE` (recorder + appraisal + nudge dissolve),
  `SCENE_DONE` (batched drift + scene summary; **plus** decay + emotion gap-drift **if that scene
  declared a real elapsed gap**), `CHAPTER_DONE` (chapter log + next-chapter card swap ADR 0013; plus
  decay + gap-drift on any chapter-level gap). Decay/gap are keyed to the elapsed bucket on whichever
  boundary carries it (ADR 0004 / 0015 §6). The state machine *is* the conductor — no separate module.
- **Word-budget clock + nudge level:** the loop runs the ADR 0015 clock, sets the ADR 0008 nudge
  `level`, raises the warning, and fires the ceiling (narrator push → player prompt → break-glass).
- **Resume anchor:** built from `sessions.resume_anchor` (ADR 0012) and injected on `NARRATOR_RESUMES`
  after any pause (player moment, save/load).

### 6. Final context inventory (every prompt slot → its producer)

The consolidated answer to "all the data that reaches the final context." **Narrator prompt:**

| Slot | Produced by |
|------|-------------|
| POV contract | scene (ADR 0009 / 0015) |
| Mesh-awareness directive + full mesh | this ADR (rule) + edges (ADR 0002) |
| Beat (intent / goal / budget) | beat doc (ADR 0015) |
| Director state (budget warnings / ceiling) | engine clock (ADR 0008 / 0015) |
| Lorebook (world facts) | keyword match (ADR 0013) |
| Scene state (present chars · immediate ctx · summary) | context-memory (ADR 0012 / 0015) |
| Resume anchor | `sessions.resume_anchor` (ADR 0012) |

**NPC prompt** (consolidating ADR 0007 with the now-settled producers):

| Slot | Produced by |
|------|-------------|
| `[IDENTITY]` card + knowledge_boundary | per-chapter card snapshot (ADR 0013) |
| `[SELF]` mood · emotions · motivation | internal state (ADR 0014) |
| `[SNAPSHOT]` edges, value×awareness folded | edges (ADR 0002) + fold (ADR 0007) |
| `[MASKS]` topic_flags + global/state masks | edge `topic_flags` (ADR 0002) + masks (ADR 0014) |
| `[DIRECTIVES]` resolved register | registers (ADR 0006, compiled ADR 0013) |
| `[NUDGE]` directed-pressure, leak-checked | nudge derivation (ADR 0015 / 0008) |
| `[SCENE RULES]` POV / tone | scene (ADR 0009) |
| `[LOREBOOK]` knowledge-bounded world facts | keyword match clamped by knowledge_boundary (ADR 0013) |
| user `[SCENE EXCERPT]` | recorder `surface` (ADR 0010), witness-filtered + POV-projected (ADR 0009), decoded via reads_target (ADR 0006) |

This table is mirrored into `ARCHITECTURE.md` as a living reference (a follow-on).

## Alternatives considered

- **Separate classifier pass for handoff.** Rejected: an extra call per turn; the prose model already
  knows whether it handed off to the player, an NPC, or wrapped the beat — emit it as structured output.
- **Recorder folded into the prose call (one call).** Rejected here in favour of a separate sub-call:
  cleaner separation for the hedged-attribution validation and `true_state` derivation, at the cost of
  one extra call. (Folding remains a later cost optimization.)
- **Appraisal before the recorder.** Rejected: appraisal must reason over the **committed, projected
  surface** (witnessed evidence), which only exists after the recorder commits — otherwise appraisal
  could peek at omniscient truth and break isolation.
- **A standalone orchestrator/"conductor" module.** Rejected (GAPS): the session state machine is the
  conductor; this ADR defines its internals, not a new component.
- **Plan-then-write as the default.** Rejected as default (cost); available as an opt-in for complex
  beats.

## Consequences

- The **narrator prompt** is now specified (it was the symmetric gap to ADR 0007's NPC prompt), and
  the **two-call narrator turn** (prose + recorder) is the unit the cost/latency budget (O4) plans
  around — on top of the ADR 0007 two calls per NPC turn.
- **In-loop sequencing is fixed** (recorder → appraisal → drift/rupture; boundaries fire the batched
  subsystems), giving the engine a deterministic order to implement.
- `sessions` gains the **loop state** the brief implies (`state_node`, current beat/scene/chapter,
  word counters, `resume_anchor`, narrative clock) — the O1 detail ADR 0012 deferred; it lands in the
  `DATABASE.md` follow-on along with `scene_summaries` / `chapter_logs` / `events` shapes.
- The **final context inventory** is captured here and mirrored into `ARCHITECTURE.md`.
- **All four open items (O1–O4 design portions) are now closed at ADR level**: O1 (this ADR), O2
  (ADR 0015), O3 (ADR 0014), and the authoring/compile gap (ADR 0013). Remaining O4 work (UI,
  cost/latency, the `DATABASE.md` column follow-on) is implementation, not open architecture.
