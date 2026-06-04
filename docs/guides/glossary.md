# Glossary

The engine's vocabulary, with the ADR/brief that defines each term. Read this before the ADRs — the terms are dense and load-bearing.

## Agents & isolation

| Term | Meaning | Source |
|------|---------|--------|
| **Narrator** | Agent that writes prose, holds omniscient truth, bound by the mesh-awareness rule + POV contract. | brief, O1 |
| **NPC** | Agent that acts as one character from its own card + own-perspective edges + nudge + witnessed excerpt. | ADR 0007 |
| **Player** | The human; sees rendered prose only, supplies input + delivery. | brief |
| **Director / Engine** | Out-of-context orchestration (state machine, stall flag, word budget, review gate). | ADR 0008 |
| **Context isolation** | An agent acts only within the limits of what it knows; enforced by the assembler. | ADR 0007 |
| **Leak guard** | One of three structural guards against leaking what an agent shouldn't know. | 0007 / 0008 / 0009-0010 |

## Character data (three layers)

| Term | Meaning | Source |
|------|---------|--------|
| **Source bible** | Full authored character doc, all arcs. Human-facing, **never injected**. | ADR 0001 |
| **Character card** | Compiled, spoiler-free, current-state slice of the bible; immutable at runtime. | ADR 0001 |
| **knowledge_boundary** | What a character currently knows / does NOT know (clamped per chapter). | ADR 0001 |
| **Relationship edge** | Directed, owner-perspective record of how `from` sees `to` (`A→B ≠ B→A`). | ADR 0002 |
| **Internal state** | Per-character transient layer: mood, active emotions, motivation, masks. Decays on its own clock. | ADR 0001, O3 |
| **base_opacity** | Character-level disposition (poker-face ↔ expressive) that seeds register composure; stored on `characters` (DATABASE.md §3.2), not per-chapter. | ADR 0010 |

## Axes & awareness

| Term | Meaning | Source |
|------|---------|--------|
| **Axis** | A −100..+100 relationship dimension: affection, trust, fear, respect, romantic, rivalry, debt. | brief |
| **Awareness** | How consciously a character accesses a feeling; computed from value (`auto`) or pinned (`capped`). | ADR 0002 |
| **Awareness tiers** | 0–39 none · 40–59 vague · 60–79 subconscious · 80+ conscious. | brief |
| **capped** | Awareness override modeling a blind spot — feels strongly, can't consciously reach it. | ADR 0002 |
| **Folding** | The compiler merges value × awareness into one phrase so a capped feeling is never stated plainly. | ADR 0007 |

## Bounds, scars & decay

| Term | Meaning | Source |
|------|---------|--------|
| **Soft / hard bounds** | Two-tier per-axis floor/cap; drift clamps to soft, rupture reaches hard. | ADR 0002/0003 |
| **rates** | Asymmetric per-axis gain/loss multipliers. | ADR 0002 |
| **peak** | High-water mark per direction; the latch source. | ADR 0004 |
| **Latch / scar** | When a peak crosses `latch_threshold`, set a permanent floor (a scar). | ADR 0004 |
| **Commitment** | A positive latch (high affection → won't abandon over slights). | ADR 0004 |
| **Trauma** | A high-magnitude latch (fear peaks → permanent flaw). | ADR 0004 |
| **Decay** | Pulls value toward baseline on **narrative time**; stops at the latched floor. | ADR 0004 |
| **Effective floor** | `max(static soft_floor, scar.floor)`. | ADR 0004 |

## Delta engine

| Term | Meaning | Source |
|------|---------|--------|
| **Drift** | Ordinary, tiny, rate-scaled change; soft-clamped; batched at scene boundary. | ADR 0003 |
| **Rupture** | High-impact change; reaches hard bounds; may rewrite bounds / flip register; immediate. | ADR 0003 |
| **Proposal** | An appraisal-emitted delta `{ edge, axis, direction, magnitude, channel, trigger, confidence }`. | ADR 0003 |
| **Review gate** | The `propose → review → commit` surface; human accept/edit/reject. Shared by deltas, nudge-compile, records. | ADR 0003/0008/0010 |
| **trigger** | Mandatory human-readable reason on every committed delta. | ADR 0003 |
| **Sensitivity** | A card's amplifier/special-case `{ id, detect, targets, axes, weight, channel }`. | ADR 0005 |
| **Universal prior** | Shared baseline human reaction (insult → affection down, etc.). | ADR 0005 |
| **Salience (match-only)** | No matched sensitivity → no delta (characters are numb to what they don't care about). | ADR 0005 |
| **Target** | actor \| beneficiary \| witnessed_third_party (vicarious shifts). | ADR 0005 |

## Register & expression

| Term | Meaning | Source |
|------|---------|--------|
| **Register** | Profile across a fixed canonical dimension set — the *grammar* of how a character expresses. | ADR 0006 |
| **Dimensions** | disclosure, proximity, flow, deflection, sincerity, composure, reads_target, tells, speech. | ADR 0006 |
| **Archetype** | Reusable register skeleton (one_way_mirror, romantic_deflection, …). | ADR 0006 |
| **Threshold selector** | Axis value → register variant (e.g. trust gradient). | ADR 0006 |
| **Emotional modulation** | Current emotional state shifts the **surface**, not the grammar. | ADR 0006 |
| **Expression mask** | Separates what is felt from what is expressed; topic-scoped masks = `topic_flags`. | brief, ADR 0002 |
| **Assembler** | Compiler + isolation boundary; two-stage (compile → act) NPC turn. | ADR 0007 |

## Directing (nudge & beat)

| Term | Meaning | Source |
|------|---------|--------|
| **Nudge** | The only authorial channel into an NPC; a register-gated bias term framed as the character's own impulse. | ADR 0008 |
| **Escalation ladder** | L0 ambient → L1 preoccupation → L2 active intent → L3 urgent drive; clocked by word budget. | ADR 0008 |
| **Stall flag** | Out-of-context orchestration signal read only by the engine. | ADR 0008 |
| **Break-glass / hard directive** | Last-resort override of an NPC; player-invoked, logged. | ADR 0008 |
| **Beat** | The smallest authored unit (intent + goal + word budget). | O2 |
| **BEAT_DONE** | The signal that a beat's goal is met / budget exhausted. | O2 |

## Narrator-side (POV & recorder)

| Term | Meaning | Source |
|------|---------|--------|
| **POV contract** | The narration POV declared per scene (default from the chapter outline). | ADR 0009 |
| **POV projection** | Re-projecting the excerpt to an NPC's limited POV (third leak guard). | ADR 0009 |
| **surface** | Observable behavior + dialogue + **hedged** perceived reads; the only layer that crosses agents. | ADR 0009/0010 |
| **true_state** | Per-character private feeling/intent; **never cross-fed**. | ADR 0009/0010 |
| **witnessed_by / fidelity** | Who perceived a beat and how well (full \| overheard \| partial). | ADR 0007 |
| **Perceived read** | A hedged observer read ("looks/seems X") — allowed; the truth behind it is not. | ADR 0009 |
| **Hedged-attribution rule** | Structural validator: unhedged "is sad / is lying" rejected before commit. | ADR 0009/0010 |
| **Recorder** | Narrator-side step that commits the two-layer beat record after each beat. | ADR 0010 |
| **Legibility** | How much of true_state shows; baked into `surface` at the recorder. | ADR 0009/0010 |
| **Decode (reads_target)** | How well an observer reads a surface; applied per-observer at projection. | ADR 0009 |

## Flow & memory

| Term | Meaning | Source |
|------|---------|--------|
| **Session state machine** | The runtime spine: NARRATOR_TURN → PLAYER/NPC/BEAT_COMPLETE. | brief |
| **Interaction queue** | Per-character relevance → priority (RESPOND_NOW/WAIT/SILENT/INTERRUPT) → interrupt check. | brief |
| **Context-memory layers** | immediate (~2000 tok) · scene summary · chapter log · lorebook. | brief |
| **Resume anchor** | Micro-continuity block the narrator uses to resume after a pause. | brief |
| **Model tiering** | Major NPC = full card (Sonnet); minor NPC = compressed (Haiku). | ADR 0007 |

## Authoring & LLM (ADR 0017–0020)

| Term | Meaning | Source |
|------|---------|--------|
| **OpenRouter** | The provider gateway (OpenAI-compatible) all LLM calls route through. | ADR 0017 |
| **`LlmClient`** | Provider-agnostic interface over the gateway; OpenRouter is the first impl (Prism / AI SDK swap behind it). | ADR 0017 |
| **Model role** | The per-call role (`narrator_prose`, `recorder`, `npc_major`, `compiler`, …) that resolves to a model slug + params. | ADR 0017 |
| **`model_profiles`** | The role → model-slug + params config; global defaults + per-story override. | ADR 0017 |
| **`llm_calls`** | Append-only per-call log (tokens, cost, latency, status); save-realm-sensitive, never agent-readable. | ADR 0017 |
| **Creation mode** | How a character is authored: `ai` / `manual` / `hybrid`. | ADR 0018 |
| **Character archetype** | A seedable whole-character template (priors + registers + sensitivities + voice + opacity); distinct from a register archetype. | ADR 0018 |
| **Outline compile** | Turning a free-text outline into reviewed `chapters` / `scenes` / `beats`. | ADR 0019 |
| **`chapter_outlines`** | The author's raw free outline, stored verbatim; never injected at runtime. | ADR 0019 |
| **Prompt block registry** | The `prompt_blocks` table defining every prompt block (purpose + source + compile instruction + leak rules); drives assembly and renders the block reference. | ADR 0020 |
| **`leak_rules`** | The per-block list naming which existing leak guard applies (awareness_fold / knowledge_boundary / hedged_attribution / own_perspective_only / omniscient_authoring). | ADR 0020 |
