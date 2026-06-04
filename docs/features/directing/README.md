# Feature domain — Directing

The **only** channel from authorial / story intent into the narrative: the psychological nudge, produced by the beat document.

## Map

| Concern | ADR / item | Status |
|---------|-----------|--------|
| Psychological nudge (directed-pressure, escalation ladder, leak-checked) | [ADR 0008](../../adr/0008-psychological-nudge.md) | **Built** |
| Beat document authoring format + `BEAT_DONE` + boundary events | **O2** ([GAPS](../../adr/GAPS.md)) | **Open** → [O2-beat-document.md](./O2-beat-document.md) |

## Note

ADR 0008 declares the beat/nudge producer as a **named dependency**: each beat must emit a *beat intent + goal + word budget*. O2 designs that producer. Until O2 lands, the nudge has no upstream.
