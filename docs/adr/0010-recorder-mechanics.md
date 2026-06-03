# 0010 — Recorder mechanics (beat record + legibility)

- **Status:** Accepted
- **Date:** 2026-06-04

## Context

ADR 0007 (assembler) and ADR 0009 (POV projection) both *consume* a per-beat record but neither
defines who *produces* it. The recorder is the narrator-side step that, after each beat, commits
the structured record every downstream agent reads. It is where two hard problems are actually
solved:

- **Sourcing emotion** — text alone is ambiguous (*"wow that's great!!"* could be delight or
  sarcasm). Emotion must be fixed **at the source**, never decoded downstream from nothing.
- **Legibility** — how much of a character's true state *shows* is not a constant; it depends on
  who they are, what they feel, and *who is watching*.

## Where it sits

The recorder is **not** a separate agent — it is a step *inside the narrator turn*, between a beat
playing out and the NPC turns that react to it:

```
NARRATOR TURN
  beat plays out (prose · player input · NPC actions)
        │
        ▼
  RECORDER (this ADR)   ── after each beat ──►   commits the beat record
        │                                         (witness log + `surface` + private `true_state`)
        ▼
  NPC TURNS via the ASSEMBLER (ADR 0007)
        [SCENE EXCERPT] = recorder `surface`, witness-filtered to that NPC,
                          then POV-projected per ADR 0009, decoded via reads_target (ADR 0006)
```

So the "per-beat witness log" and "scene excerpt" that **ADR 0007** consumes are *produced here*.
ADR 0007 named this the "narrator/recording step"; this ADR **is** that step.

## Decision

### The recorder emits the ADR 0009 two-layer record

```
beat record:
  surface:      observable behavior + dialogue + HEDGED perceived reads ("looks/seems X")
  true_state:   { char: "private feeling/intent" }   per-character, never cross-fed
  witnessed_by: { char: full | overheard | partial } (ADR 0007)
  pov_anchor:   <scene contract anchor>
```

`surface` is the only layer that crosses to other agents (decoded per-observer in ADR 0009).
`true_state` stays private; a character's own copy reaches it via its SELF block (ADR 0007).

### Legibility is a derivation, not a stored trait

The committed `surface` read is computed, not free-written:

```
surface_read(target → observer) =
    base_opacity        CARD disposition: poker-face/trained ↔ expressive   (seeds composure)
  × intensity           AXES: magnitude of the emotion in play (stronger → leaks more)
  × can_conceal         AWARENESS + MASK: is she even aware of it? deliberately hiding it?
  × composure + tells   resolved REGISTER on the target→observer edge (ADR 0006)  ← the RELATIONSHIP term
```

Consequences that fall out for free:

- A **poker-face / professional** target has high `base_opacity` → `surface` dampens to
  *"composed / hard to read"* even when `true_state` is turmoil. (This is the ADR 0009 opacity
  exception — no new subsystem, just card × register.)
- Because `composure`/`tells` are **per-edge**, the same true_state surfaces *differently to
  different watchers* — Luna `fragile` toward Vixia leaks; `unbreakable` toward a stranger seals.
- A **fragile** register + an authored `tell` firing → `surface` carries the leak
  (*"her ears go pink"*), which an observer may or may not decode.

### base_opacity is a card disposition that seeds composure

Like axis disposition priors, a card declares a baseline expressiveness (poker-face ↔ open). It
**seeds** the per-edge register `composure`; relationships then move it (someone's guard drops with
a specific person). Opacity therefore = `card × axes/awareness/mask × resolved register` — exactly
the combination of character + relationship + state.

### Sourcing the player's delivery (hybrid)

The human can't be emotion-decoded either, so delivery is **sourced**, three ways, in order of
preference:

1. **Prose** — the human writes the delivery into their input (*"'Fine,' I say, forcing a smile"*);
   the recorder commits `surface`/`true_state` from it.
2. **Optional tone tag** — a lightweight delivery hint when the human wants precision without prose.
3. **Narrator infer + ask-when-ambiguous** — if input is bare dialogue with a genuinely ambiguous
   read, the narrator proposes a surface and only interrupts to confirm when it can't resolve it.

NPC delivery needs no decode: the NPC agent generated the line *with intent*, so it reports its own
delivery, and the recorder captures the resulting `surface`.

### Validation: structural rule + review gate (does NOT trust the model)

1. **Hedged-attribution rule.** In `surface`, mental-state attributions are allowed **only** in
   perceptual form (`looks`/`seems`/`appears`). An unhedged assertion (`is sad`, `is lying`) is
   rejected before commit. Hidden facts are blocked by `knowledge_boundary`. Cheap, model-independent.
2. **Review gate.** The recorder *proposes* the record; the human can accept/edit/reject before
   commit — the same `propose → review → commit` gate as deltas (ADR 0003), sharing its UI. The
   human is the fidelity floor.

### Model tiering

The recorder runs on the **strong** narrator model (it does the hard generative work: legibility +
hedged surface). Minor NPCs (Haiku) never record — they only react to a pre-framed `surface`.

## Worked example

Input (player): *"'I'm fine,' I say"* — `true_state` (from prose/tag): "not fine, hiding it".
Target watcher: Luna.

- player `base_opacity`: middling; `intensity`: high; `can_conceal`: trying; register player→Luna
  `composure`: shaky.
- → committed `surface = "'I'm fine,' he says — it doesn't quite reach his eyes; he looks like he's
  holding something back."` (hedged, observable).
- Validation: passes (no unhedged mental-state claim; no hidden fact).
- Luna's decode (`reads_target: accurate`): reacts to a man visibly not-fine. If Luna's read
  **crashed**, she'd get a blurred version. The *reason* he's not fine never appears.

## Alternatives considered

- **Free-write the surface read.** Rejected: a weak recorder leaks unhedged truth; the
  hedged-attribution check must be structural.
- **Decode player tone automatically from text.** Rejected: impossible from text alone; delivery is
  sourced (prose/tag/confirm) instead.
- **Store legibility/opacity as one flat trait.** Rejected: it must combine card + axes + the
  per-edge register, or it can't vary by watcher or by what's felt.
- **No review gate (trust the recorder).** Rejected: contradicts the model-independent safety goal;
  the human gate is the fidelity floor.

## Consequences

- The narrator pipeline gains an explicit **recorder step** after each beat: derive legibility →
  commit `surface` + `true_state` + witness tags → validate → review → commit.
- Cards gain a **base_opacity disposition**; it seeds per-edge `composure` (ADR 0002/0006).
- Player input needs a **delivery channel** (prose default + optional tone tag + ambiguity prompt) —
  a UI concern for the input surface.
- The **review gate UI** is now shared by three producers: delta proposals (0003), nudge compile
  (0008), and beat records (this ADR).
- Downstream, **ADR 0007 assembler** and **ADR 0009 projection** consume `surface`; **`reads_target`
  (0006)** decodes it.
