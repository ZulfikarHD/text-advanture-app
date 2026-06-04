# 0013 — Authoring & compile pipeline (bible -> card / registers / sensitivities / lorebook)

- **Status:** Proposed
- **Date:** 2026-06-04

## Context

ADR 0001 split the **source bible** (full authored doc, all arcs, never injected) from the
runtime **character card** (a compiled, spoiler-free, current-state slice). Its Consequences
named two things no later ADR ever specified:

- *"Need a **compile step** that derives the runtime card from the source bible."*
- *"`knowledge_boundary` becomes a first-class, must-maintain field (epistemic state per chapter)."*

Every downstream subsystem **consumes** the products of that missing step:

- ADR 0007 assembles the **card** (+ `knowledge_boundary`), **registers**, and **sensitivities**.
- ADR 0006 reads **registers** + the shared **archetype** library.
- ADR 0005 reads **sensitivities** + the universal-priors library.
- ADR 0002 seeds new edges from the card's **disposition priors**.
- The brief names a **lorebook** ("world facts, injected on keyword match") but no ADR designs
  how it is authored or injected.

ADR 0012 / `DATABASE.md` define the **output shapes** (the authoring-realm tables) but say nothing
about the **producer**, its **lifecycle**, or how **spoilers are excluded per chapter**. The source
material is the proof of the problem: `luna-archi.md` + `luna-archi-author-notes.md` are
spoiler-laden and saga-tagged (Saga 1-4, "Ch 9"), with load-bearing reveals — the diagnosis secret,
the guilt, the Saga-4 cure — that an early-chapter card must never contain.

This ADR specifies that compile step. It is **not new scope**: it is the producer ADR 0001 already
named, plus the lorebook the brief already assumes.

## Decision

### 1. An authoring-time compile pipeline, gated by review

Compilation happens **offline at authoring time**, never inside the runtime loop:

```
SOURCE (omniscient, never injected)              COMPILE (LLM-assisted)        REVIEW          COMMIT
luna-archi.md + author notes  ──────────────────► draft artifacts  ──────────► review gate ──► authoring rows
  + chapter outline / reveal ledger                (card, registers,            accept/edit/    (immutable
                                                    sensitivities, lorebook)    reject          at runtime)
```

- The LLM **proposes**; the human **accepts / edits / rejects** through the **same shared review
  gate** as deltas (ADR 0003), nudge-compile (ADR 0008), and beat records (ADR 0010). A new
  `producer_type` — `card_compile` — is added to `review_items`. The human is the fidelity floor.
- Committed artifacts are **immutable at runtime** (ADR 0001). Re-authoring means a new compile +
  review, not an in-place runtime edit.

### 2. What the pipeline produces

The compile targets are exactly the **authoring-realm tables** (ADR 0012 / `DATABASE.md` §3):

```
characters         slug, name, bible_path, base_opacity, live_axes, model_tier, is_player
character_cards    folded_identity, knowledge_boundary, disposition_priors, voice, tells
                     — one row PER (character, chapter)
registers          dimension profile (ADR 0006 fixed set) + speech_ref + tells; archetype-bound or bespoke
register_archetypes promoted when a grammar is reused across characters
sensitivities      { detect, targets, axes, weight, channel }  (ADR 0005)
lorebook_entries   { keywords, content }  story-scoped world facts
```

Extraction is **LLM-driven then reviewed**:

- The bible's **behavioral contrast** (Luna's "Koakuma vs. With-Vixia" table) compiles into **two
  registers** over the ADR 0006 fixed dimension set (`koakuma_default`, `transparent_mess`); reused
  grammars are promoted to the shared **archetype** library, bespoke ones are allowed.
- The bible's **wounds and triggers** (threat-to-Vixia, fear of abandonment, pitied-as-fragile)
  compile into ADR 0005 **sensitivities**.
- The bible's **disposition** (how she meets new people by trait) compiles into `disposition_priors`.

### 3. Spoiler-safety: saga/chapter tags + a reveal ledger -> `knowledge_boundary`

The compiler must know each fact's **reveal point** to clamp the card to the current chapter. Two
inputs, both authored and reviewable:

- **Section tags** — the bible is annotated by saga/chapter at the section level (it already carries
  `Saga 1-4` / `Ch 9` markers). Coarse, cheap, covers the bulk of the doc.
- **Reveal ledger** — a small per-character list for the **load-bearing secrets** that must never
  leak early: `{ fact, reveal_chapter, who_knows }` (e.g. *diagnosis -> late Saga 1*, *cure -> Saga
  4*, *parents-died-searching -> Ch 9*). Explicit, so spoiler-safety never rests on inference.

Clamp rule at compile time for a card at chapter **N**:

```
include a fact  iff  reveal_point(fact) <= N
otherwise        ->  it becomes an explicit "does NOT know" entry in knowledge_boundary (or is omitted)
```

`knowledge_boundary` therefore records **both** what the character knows and what it does *not* know
(ADR 0001/0007), so the assembler and recorder can structurally block hidden facts.

### 4. Card lifecycle: full recompile per (character, chapter)

- A card is keyed **per (character, chapter)** and is a **full, deterministic recompile** from the
  bible at that chapter's reveal state — not a forward-diff of the previous card.
- **Recompile triggers:** the bible changes, the chapter outline / reveal ledger changes, or a new
  chapter is added. Each recompile re-runs through the review gate.
- At runtime the session reads the card for `session.current_chapter` (ADR 0012) and treats it as
  immutable. Advancing chapters swaps in the next card snapshot; relationship/internal state carries
  across via the save realm.

### 5. Lorebook: authoring + knowledge-bounded injection

- **Authoring.** `lorebook_entries` = `{ keywords, content }`, story-scoped, **world facts only**
  (Crystal Hollow, the suppressor gloves, Link Resonance, Aether) — never a character's interiority.
  Extracted from the bible's world sections, reviewed like any other artifact.
- **Injection.** Keyword-match against the active scene/excerpt injects matching entries into:
  - the **Narrator** context, and
  - an **NPC** world-knowledge block **clamped by `knowledge_boundary`** — an NPC only receives a
    world fact it would plausibly know at the current chapter.

  This is a **soft keyword mechanism** (the brief's context-memory lorebook layer), not a new
  subsystem. World facts are not character-private, so lorebook injection does not breach isolation;
  the per-character `knowledge_boundary` clamp is the only gate it needs.

### 6. Edge-seeding is produced here, applied elsewhere

The pipeline **produces** `disposition_priors`; it does **not** seed edges. New relationship edges
are seeded at **session-fork** time from those priors, keyed by target traits — that remains ADR
0002 / 0012. This ADR only guarantees the priors exist on the card.

### 7. The player card

The player gets an **appearance-only** card plus a `base_opacity` (so other characters can read the
player's delivery, ADR 0010) and **no outgoing edges** (ADR 0001). Same pipeline, minimal output.

### 8. Isolation framing

The compiler is **omniscient at authoring time** (it reads the entire bible), but its *output* — the
card — is the spoiler-bounded slice. Safety here is the **`knowledge_boundary` clamp + the review
gate**, enforced before anything reaches the runtime. This is **not a fourth leak guard**; it is the
**authoring-time enforcement that the three runtime guards depend on** (a card that leaks a future
arc would defeat ADR 0007's awareness-fold no matter how good the runtime guards are).

## Alternatives considered

- **Hand-authored cards (no compile).** Rejected: duplicates the bible, drifts from it, and loses
  the single source of truth ADR 0001 established.
- **Unreviewed LLM compile.** Rejected: spoiler-leak risk; contradicts the model-independent safety
  posture (the human review gate is the fidelity floor everywhere else).
- **One card per character (no per-chapter snapshot).** Rejected: `knowledge_boundary` is *defined*
  to advance per chapter; a single card cannot represent epistemic progression.
- **Forward-diff recompile (copy previous card + apply new reveals).** Rejected for now: full
  recompile is deterministic and simpler to audit; diffing can be a later optimization.
- **LLM-infer spoilers from the outline as the sole mechanism.** Rejected: unreliable for
  load-bearing secrets; the explicit reveal ledger backstops the coarse section tags.
- **Inline reveal tags on every spoiler fact.** Rejected: heavier authoring burden than section
  tags + a ledger for the few critical facts.
- **Lorebook as its own subsystem / ADR.** Rejected: it is a keyword-match injection over authored
  world facts, folded in here rather than promoted to a subsystem.

## Consequences

- The shared review gate gains a `card_compile` **producer_type** (alongside delta / nudge / record).
- The bible needs a light **annotation convention** (saga/chapter section tags) plus a small
  **reveal ledger** per character — a modest authoring burden that *is* the spoiler-safety source.
- `character_cards` are **recompiled per chapter**, requiring an **offline compile/recompile
  command** (authoring tooling). This is authoring-time work, **not** part of the runtime loop or its
  cost/latency budget.
- `DATABASE.md` is mostly already aligned; a **reveal-ledger** structure (small table or JSON on the
  card) is the one addition — its column-level detail folds into the DATABASE.md follow-on once
  O1-O3 land, consistent with ADR 0012's deferral pattern.
- **Context assembly** gains a keyword-match **lorebook injection** step for the Narrator and (knowledge-
  bounded) for the NPC, to be wired in the narrator-loop ADR (O1).
- **Unblocks ADR 0014 (internal-state), 0015 (beat document), and 0016 (narrator loop)** — all of
  which assume committed, spoiler-bounded cards exist.
