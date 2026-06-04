# 0008 — Psychological nudge (directed-pressure model)

- **Status:** Proposed
- **Date:** 2026-06-04

## Context

Per the brief's isolation rule and ADR 0007, the **nudge is the only channel** through which
authorial / story intent reaches an NPC. Everything else a character does is self-simulation
from its card + edges + register. The nudge is what makes the engine *directed* — but it has to
thread three constraints at once:

- **Steer** the scene toward where the story needs to go (or it's a sandbox, not "directed").
- **Not leak** knowledge the character doesn't have (isolation).
- **Not puppet** the character (or the whole axis / register / mask stack is dead weight).

Authoring context: the human is a **pantser of *route*, not of structure**. A chapter outline,
its scenes, world lore, and the chapter's beats already exist. Drifting from the *path* is fine
**as long as the beat's goal is the same**. The hard pacing flag is **word count**. So "directed"
means *catchable*, not *scripted* — the nudge is a safety net that gently lands a beat when prose
is wandering, and the human can always veto it.

## Decision

### A nudge is a bias term, gated by the register — it never bypasses the stack

It feeds *into* the ADR 0006 behavior equation and is still gated at the end:

```
nudge ──┐
        ├─► resolve(register pipeline)        nudge can JUSTIFY a situational override (step 3)
        ├─► modulated by emotional state      mood-type nudge shifts the SURFACE (step 4)
        └─► expressing axis-values-as-language
            └─► gated by mask + awareness      nudge is STILL gated here — it can be suppressed
```

"Open up to him" nudged onto Luna-with-a-classmate hits her `sealed` disclosure register and is
defeated — **and that struggle is the scene.** The nudge sets *direction + pressure*; the register
decides *how it surfaces, or whether it surfaces at all*.

### Internal-impulse framing

Every nudge reads as the character's **own** urge / mood / preoccupation / goal
("you find yourself wanting to…"), never as a stage direction. This is the autonomy guarantee at
the prompt layer and the lowest-leak framing. It is the transient sibling of the capped-awareness
trick: where a capped axis renders "feels it, can't name it", a nudge renders
"feels a pull tonight, can't say why".

### Stall-driven escalation ladder (beat-scoped)

Pressure is **not static** — it ratchets the longer a beat goes unresolved:

```
NUDGE (one beat, one character)
  L0 ambient        internal coloring ........ "something about tonight sits wrong"
  L1 preoccupation  recurring pull, directs attention
  L2 active intent  a felt goal she wants this scene
  L3 urgent drive   in-character compulsion to force the beat's question open
  ───────── ceiling: do not puppet from inside the character ─────────
```

Each rung is still run through the register / mask gate, so escalation changes *intensity*, not
*autonomy*.

### The clock is word budget + goal-not-met (+ manual bump)

- The **beat's goal is the anchor**; the route may drift freely toward it.
- A **per-beat word budget** drives rung climbs: as the budget depletes without the goal being
  satisfied, the ladder climbs. If prose *is* progressing toward the goal, the satisfaction signal
  moves and the ladder does **not** ratchet (pantser-safe).
- The **chapter's overall word count** is the outer hard flag that forces a wrap.
- The human can **manually bump** (or hold) the level at any time.

### Ceiling: act *around* the character first, override only as break-glass

NPC and narrator are **separate agents**, so a stalled beat is resolved by acting around the NPC,
not by reaching inside it. Beyond L3, in order:

```
① STALL FLAG          out-of-context orchestration signal — injected into NO narrative agent.
                      Zero leak. The director/engine reads it.
② NARRATOR PUSH       narrator agent forces the topic via event / atmosphere. The narrator is the
                      engine's instrument (not an autonomous character), so directing it hard is fine.
③ PLAYER PROMPT       surface "Continue / Skip / Direct a character?" — hand control to the human.
④ HARD DIRECTIVE      break-glass last resort: MAY override the NPC agent to guarantee the beat lands.
                      Preferentially PLAYER-INVOKED (the "Direct a character" path = the human issuing it).
                      Always logged to the audit trail. Deliberately breaks the autonomy guarantee.
```

Steps ①–③ keep autonomy intact. Step ④ is the only path that overrides an NPC, it is rare, and it
is recorded like a delta.

### Authoring: both modes, one leak guard

```
AUTHOR SIDE (omniscient, never crosses the boundary)
  beat intent: free text. "Player burned her hometown. Corner him about that night.
                           She must NOT learn it was arson."          ← pantser-friendly

        │  compile + leak-check  (told her knowledge_boundary; may emit ONLY internal
        ▼                         signals consistent with what she knows)

NPC SIDE (the only thing that reaches Luna)
  nudge: { kind:[goal,attention], level:L1,
           text:"His dodging about that night itches at you; you keep circling back to it." }
```

Two supported modes, **both** validated against `knowledge_boundary` at the ADR 0007 boundary:

- **Derived (default):** author-side beat intent → compiled, bounded nudge. Raw omniscient intent
  never crosses.
- **Hand-authored:** the human writes the bounded internal nudge directly; it still passes the
  leak-check before injection.

### Shape

```yaml
nudge:
  kind:    [goal | attention | mood | relational-impulse | suppression]   # 1+; drives framing
  level:   L0 | L1 | L2 | L3            # current rung (set by the clock or a manual bump)
  text:    "<internal-framed prose>"    # compiled or hand-authored, leak-checked
  target:  <character|topic>            # optional focus of the impulse
  goal:    "<beat satisfaction signal>" # the anchor; when met → nudge dissolves, feeds BEAT_DONE
  source:  derived | authored
```

### Scope / lifetime

- **Beat-scoped.** A nudge expires when its beat advances; satisfying its `goal` dissolves it.
- **Optional and per-character.** A typical beat nudges 0–1 characters; everyone else runs pure
  self-simulation.
- A character may hold a small stack (e.g. a `goal` + a `mood`); they are inputs to behavior,
  resolved alongside the register, never bypassing the mask/awareness gate.

### Third leak guard

Nudge-compile guards **authorial / plot omniscience**. It is one of three orthogonal guards:

| Guard | Stops leaking | Where |
|---|---|---|
| Awareness-fold | the character's *own* capped feelings | ADR 0007 |
| **Nudge compile** | **authorial / plot omniscience** | **this ADR** |
| POV projection | other characters' interiority + narrator omniscience in the excerpt | ADR 0009 (next) |

## Worked example (Luna)

Beat intent (author, omniscient): *"Corner him about the night of the fire before the scene ends;
she mustn't know it was arson."*

| Rung | Compiled nudge to Luna (classmate, `koakuma_default`) | Surface, after register gate |
|------|------|------|
| L1 | "His dodging about that night itches at you." | watches him, light probing, still sealed |
| L2 | "You want a straight answer about that night." | steers conversation, teasing-interrogation |
| L3 | "It's eating you — drop the act, make him say it." | pushes hard, but *her* way (composed) |
| ceiling | — | ② narrator: a burnt smell drifts in / someone names the fire → forces the topic |

The **same** nudge onto Vixia (`transparent_mess`, fragile) leaks at L1 — pink ears, a stumbling
half-question. Same pressure, opposite surface. The nudge never puppets.

## Alternatives considered

- **Director-note framing.** Rejected: reads external, invites mechanical instruction-following
  that breaks character.
- **Hard nudges that bypass the register (normal escalation).** Rejected: kills the behaviour
  stack; the resisted nudge *is* the drama. Override survives only as the logged break-glass (④).
- **Hand-authored bounded nudge as the *only* mode.** Rejected as sole mode — a pantser writing
  free text will eventually leak; kept as an optional mode behind the leak-check.
- **Turn-count clock.** Rejected in favour of **word budget**, which fits a prose/writing tool and
  matches the human's "word count is the hard flag".
- **Auto-overriding the NPC as a normal rung.** Rejected: override is break-glass, preferentially
  player-invoked, and audited.

## Consequences

- The **beat / nudge producer is now a named dependency** (the beat doc, designed next): each beat
  must emit a **beat intent + goal + word budget**.
- **Stall detection needs a word counter** per beat and per chapter, plus a goal-satisfaction
  signal — new orchestration state.
- The **assembler (ADR 0007) gains the nudge leak-check** (compile beat intent → bounded nudge,
  validated against `knowledge_boundary`) as part of compilation.
- The **hard-directive break-glass (④) must be logged** to the audit trail and surfaced in the
  review / relationship UI, alongside delta proposals.
- **POV projection (ADR 0009)** is the next required Narrator-side leak guard.
