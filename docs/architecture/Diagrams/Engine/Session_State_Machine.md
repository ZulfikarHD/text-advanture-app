# Session state machine

The runtime spine. Source: brief "session state machine", [../../../adr/GAPS.md](../../../adr/GAPS.md) (the flow). The loop *internals* are open item **O1**.

```mermaid
flowchart TD
  start([SESSION_START]) --> narrate[NARRATOR_TURN: generate prose, scan for handoff]
  narrate --> handoff{Handoff signal?}
  handoff -->|player input needed| playerMoment[PLAYER_MOMENT: render input box, wait]
  handoff -->|character should act| npcMoment[NPC_MOMENT: interaction queue]
  handoff -->|beat goal met / word budget| beatComplete[BEAT_COMPLETE: chapter wrap + summary]

  playerMoment --> resume[NARRATOR_RESUMES via resume anchor]
  npcMoment --> queue[Relevance -> priority -> interrupt check -> resolve turns]
  queue --> recorder[RECORDER: commit beat record]
  recorder --> resume
  resume --> narrate

  beatComplete --> boundary[Scene/chapter boundary: batch drift + apply decay]
  boundary --> narrate
```

## Where the subsystems fire (O1 must pin this down)

- **Appraisal** (ADR 0003/0005) runs after a beat's events → emits delta proposals → review gate.
- **Recorder** (ADR 0010) runs after each beat, before NPC turns that react to it.
- **Decay** (ADR 0004) runs at scene/chapter boundaries, scaled by declared in-world elapsed time.
- **Nudge escalation** (ADR 0008) is clocked by per-beat word budget + goal-not-met.

> The state machine is the conductor; O1 defines the loop internals, not a new orchestrator.
