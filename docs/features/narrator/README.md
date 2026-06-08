# Feature domain — Narrator

The spine of the runtime flow: the narrator generates prose, scans for the handoff signal, and (via the recorder) commits the per-beat record every other agent reads.

## Map

| Concern | ADR / item | Status |
|---------|-----------|--------|
| POV projection (perceived-read leak guard, legibility × decode) | [ADR 0009](../../adr/0009-pov-projection.md) | **Built** |
| Recorder mechanics (two-layer beat record, hedged-attribution) | [ADR 0010](../../adr/0010-recorder-mechanics.md) | **Built** |
| Narrator prompt assembly (registry-driven blocks → messages) | [ADR 0020](../../adr/0020-prompt-block-registry.md) / S-4.1.1 | **Built** → [S-4.1.1-narrator-prompt-assembly.md](./S-4.1.1-narrator-prompt-assembly.md) |
| Narrator prose call (structured `prose · handoff · elapsed`; malformed → retried then surfaced, never trusted) | [ADR 0016](../../adr/0016-narrator-agent-and-turn-loop.md) / S-4.2.1 / S-4.2.2 | **Built** → [S-4.2.2-structured-prose-output.md](./S-4.2.2-structured-prose-output.md) |
| Narrator loop, handoff detection, mesh-awareness rule, resume anchor, in-loop sequencing | **O1** ([GAPS](../../adr/GAPS.md)) | **Open** → [O1-narrator-loop.md](./O1-narrator-loop.md) |

## Note

ADR 0009/0010 are the narrator-side **leak guards** and the recorder step, but **who runs the loop** (handoff to PLAYER_MOMENT / NPC_MOMENT / BEAT_COMPLETE, where appraisal + decay fire) is O1 — the largest open item.
