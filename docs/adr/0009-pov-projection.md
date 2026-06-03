# 0009 — POV projection (perspective leak guard)

- **Status:** Accepted
- **Date:** 2026-06-04
- **Revision (2026-06-04):** replaced the original "behavior-only / no emotion labels" rule with the
  **perceived-read** model. The first version asked the actor model to decode emotion from a bare
  observable cue — an impossible cold decode (you cannot tell sincerity from sarcasm in
  *"wow that's great!!"*) and it made socially perceptive characters dumber than a real stranger.
  Corrected before any build; recorded here rather than superseded.

## Context

The narrator writes in a declared **POV contract**. Whatever that POV is, the scene summary and
current-event excerpt an NPC agent receives **must not** carry what that NPC can't have: another
character's *true* internal state, hidden facts, concealed thoughts/intentions, or narrator
omniscience. ADR 0007's witness log answers *did she see it?*; this ADR answers *whose head was it
narrated from?* — a separate, orthogonal leak.

Two properties must be kept apart (they are not the same thing):

- **Safety (leak prevention)** — must be **structural and model-independent** (holds even on Haiku).
- **Fidelity (correct emotional read)** — is **best-effort and degrades gracefully**.

A misread surface is a *quality* bug (in-character, recoverable next beat). A leaked secret is a
*safety* bug. The architecture guarantees the second never happens at any model tier; it makes a
best effort at the first.

The leak line sits in a non-obvious place. A human — even toward a stranger — *forms an emotional
read* ("he looks upset"). Forbidding that would cripple a social character like Luna. So a
**perceived surface read is allowed and necessary**; only the **hidden truth behind it** is the leak.

## Decision

### POV contract: declared per scene, default from the chapter outline

- The **scene** is the unit where POV is declared; it **inherits a default** from the chapter
  outline (the backbone the human authors).
- Modes: `2nd-person/player` · `1st-person/<character>` · `3rd-limited/<anchor>` ·
  `3rd-omniscient` (**display-only** — NPC excerpts are *always* projected to a limited POV).

### Two-layer beat record (extends the ADR 0007 witness log)

The recorder (ADR 0010) commits two separated layers:

```
beat record:
  surface:      observable behavior + dialogue + HEDGED perceived reads ("looks/seems X")
                — the legibility-gated public layer (how it commits is ADR 0010)
  true_state:   { char: "private feeling/intent" }   per-character — NEVER cross-fed
  witnessed_by: { char: full | overheard | partial }                 (ADR 0007)
  pov_anchor:   <scene contract anchor>   whose interiority the DISPLAY may use
```

### Excerpts carry perceived reads, never the truth behind them

The excerpt an NPC receives is the **surface layer**, filtered to `witnessed_by ∋ self` at its
fidelity. The leak line:

- ✅ allowed: hedged perceptual reads — *"Vixia **looks** sad"*, *"he **seems** on edge"*. The
  "looks/seems" framing marks it as the observer's read, not fact.
- ❌ forbidden: true internal state behind it (*"sad **because she's hiding the diagnosis**"*),
  hidden facts, and concealed thoughts/intent (*"he **is** lying"*).

A character's *own* feelings come from its SELF / SNAPSHOT blocks (ADR 0007), never the excerpt. No
character ever reads another's `true_state`.

### Two legibility dials, applied at different stages

```
LEGIBILITY (how much of true_state surfaces)   →  baked into `surface` at the RECORDER (ADR 0010)
   = card base-opacity × axis intensity × awareness/mask × resolved register (composure+tells)
     on the target→observer edge

DECODE (how well the observer reads that surface)  →  applied per-observer at PROJECTION (here)
   = observer.reads_target on the observer→target edge
       accurate → faithful, sometimes sharpened by how well they know the target
       crashes  → degraded/distorted read ("you can barely focus on his face, let alone read it")
```

Both dials are **per-edge** and **directional**: a target's legibility lives on its edge *toward*
the observer; the observer's decode lives on its edge *toward* the target. This is why Luna leaks
to Vixia (her composure is `fragile` there) *and* misreads him (`reads_target: crashes` there),
yet is sharp and sealed with a stranger.

### Robustness at any model tier (the part that does NOT trust the model)

1. **Hedged-attribution rule — validated, not trusted.** Mental states may appear in the surface
   layer **only** in perceptual form (`looks` / `seems` / `appears`). An *unhedged* assertion
   (`is sad`, `is lying`) is rejected before commit. Hidden facts are blocked by
   `knowledge_boundary` regardless. This enforces the perception framing *and* blocks true-state
   leaks with a check that doesn't need a smart model.
2. **Human review gate** — reuses the ADR 0003 `propose → review → commit` pattern. The human is
   the fidelity floor; this matters *more* for weaker models.
3. **Model tiering by task difficulty.** The hard generative step (commit the surface read) runs on
   the strong narrator/recorder model; minor NPCs (Haiku) only *react to* an already-framed read.
4. **Graceful degradation.** A misread is in-character and recoverable, so the system needs bounded
   non-leaking error, **not** semantic determinism — which LLMs cannot provide anyway.

### Render pipeline

```
DISPLAY (the human reads)  → render `surface` in the scene's POV contract; pov_anchor interiority allowed.

NPC X excerpt (X's agent)  → surface ∩ witnessed_by[X] at its fidelity,
                             addressed to X, decoded through X.reads_target(→target),
                             true_state of others absent, validated vs X.knowledge_boundary.
                             X's OWN feelings come from its SELF/SNAPSHOT blocks, not here.
```

## Worked example

- **Scene POV:** 2nd-person, player anchor. Beat: the player is sad but hiding it while answering Luna.
- **Record:** `true_state = {player: "sad, hiding it"}`; legibility for player→Luna is moderate (he
  hides, but not perfectly) → committed `surface = "he goes quiet; he looks like something's
  weighing on him"`; `witnessed_by = {Luna: full}`.
- **Display (human reads):** *"You go quiet, hoping she doesn't see it land."*
- **Luna's excerpt + decode:** `reads_target: accurate` → *"He looks like something's weighing on
  him."* Luna responds to that read. If her reading **crashes** (the Vixia case) → the read is
  degraded (*"he's hard to read right now, and so are you"*). Either way the **diagnosis-grade
  truth never leaks** — only the *quality* of the read varies.

## Alternatives considered

- **Behavior-only / no emotion labels (the original v1 of this ADR).** Rejected: asks for a cold
  emotional decode with no signal, and makes a perceptive character read worse than a stranger.
- **True emotion stated as fact, flagged "not directly knowable".** Rejected: leakiest option;
  relies on the model honoring a flag.
- **Forbidden-emotion-word list.** Rejected: now wrong, since `looks sad` must be *allowed*;
  replaced by the hedged-attribution rule.
- **Prose re-projection as the source of truth.** Rejected: lossy/leak-prone; used only as the
  *rendering* step on top of the committed surface layer.
- **Recorder commits a separate read per observer.** Rejected as default: heavier and more
  omniscient at record time; the legibility×decode split achieves per-observer variance more cheaply.

## Consequences

- The **recorder (ADR 0010)** must commit the two-layer record and compute the legibility-gated
  surface read (incl. hedged perceived reads).
- The **hedged-attribution validator** + a **review surface** are required (share the ADR 0003 gate).
- **Model tiering** is explicit: recorder = strong model; minor-NPC readers = Haiku reacting to a
  pre-framed read.
- **`reads_target` (ADR 0006) is load-bearing** — the per-edge decode dial, including in-character
  misreads and crashes.
- The **assembler (ADR 0007) scene-excerpt step** is now: witness-filter → fidelity-degrade →
  POV-project (surface only) → decode via `reads_target` → `knowledge_boundary` validate.
- Safety is guaranteed at any model tier; fidelity is best-effort and human-backstopped.
