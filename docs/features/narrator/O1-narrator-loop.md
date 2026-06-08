# O1 — Narrator loop + turn sequencing

> **Status:** Closed (designed) → **[ADR 0016](../../adr/0016-narrator-agent-and-turn-loop.md)** (`Proposed`) · **Domain:** narrator · depends-on 0003/0004/0005/0007/0008/0010 · **Last Updated:** 2026-06-04

## Summary

The narrator is the spine of the [session state machine](../../architecture/Diagrams/Engine/Session_State_Machine.md) and is still undesigned. This feature defines how a `NARRATOR_TURN` generates prose, decides the handoff, tags witnesses, and sequences the other subsystems within a beat.

## Goal & non-goals

- **Goal:** a runnable narrator → player → narrator loop, into which NPC turns, appraisal, decay, and the recorder slot.
- **Non-goals:** the beat document format (that's [O2](../directing/O2-beat-document.md)); the internal-state shape (that's [O3](../session/O3-internal-state-schema.md)).

## Scope (from GAPS O1)

- **Prose generation** under the chapter **POV contract**.
- **Handoff detection** — how `NARRATOR_TURN` chooses `PLAYER_MOMENT` vs `NPC_MOMENT` vs `BEAT_COMPLETE`.
- **Mesh-awareness rule** — prompt directive keeping the narrator from revealing what a character wouldn't know (a soft rule, **not** a separate leak-guard subsystem).
- **Witness tagging** — per-beat `witnessed_by` + fidelity that the assembler (0007) and recorder (0010) consume.
- **Resume anchor** wiring (the micro-continuity block).
- **In-loop sequencing** — where appraisal (0003/0005), decay (0004), and the interaction queue fire within a beat.

## Agent / isolation impact

Runs as the **Narrator** agent (omniscient truth, bound by mesh-awareness + POV contract). Must not leak hidden facts into prose or into the recorder `surface`.

## Open questions

- Single prose call per turn, or plan-then-write?
- Is handoff detection a structured tool-call from the narrator, or a separate classifier pass?
- Exact ordering: appraisal before or after the recorder commit?

## Implementation status

The loop's deterministic **spine** is built (skeleton subset): `SessionStateMachine` drives `session_start → narrator_turn → { player_moment | beat_complete } → narrator_resumes`, routed by the narrator turn's structured handoff — see [session/S-3.1.1-state-machine-spine.md](../session/S-3.1.1-state-machine-spine.md). The first turn *internal* is also built: **narrator prompt assembly** (S-4.1.1) folds the registry's lit narrator blocks into the turn's prompt — see [S-4.1.1-narrator-prompt-assembly.md](./S-4.1.1-narrator-prompt-assembly.md). The remaining internals this loop sequences (the prose call + structured handoff S-4.2.1, the recorder sub-call, in-loop appraisal/decay, the `npc_moment` branch) plug into that spine in later sprints/phases without rebuilding it.

## Related Documentation

- Feature: [session/S-3.1.1-state-machine-spine.md](../session/S-3.1.1-state-machine-spine.md) (the built spine this loop runs on)
- ADRs: [0007](../../adr/0007-npc-context-assembly.md), [0009](../../adr/0009-pov-projection.md), [0010](../../adr/0010-recorder-mechanics.md), [0008](../../adr/0008-psychological-nudge.md)
- Architecture: [ARCHITECTURE.md §3,§5,§6](../../architecture/ARCHITECTURE.md) · [State machine diagram](../../architecture/Diagrams/Engine/Session_State_Machine.md)
- Open items: [GAPS O1](../../adr/GAPS.md)
