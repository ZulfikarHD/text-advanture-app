# O5 — Character creation (AI / manual / hybrid) + archetype library

> **Status:** Proposed · **Domain:** npc-behaviour · **Owning ADR(s):** [ADR 0018](../../adr/0018-character-creation-pipeline.md) (creation + `character_archetypes`), depends-on [ADR 0013](../../adr/0013-authoring-and-compile-pipeline.md) (compile), [ADR 0017](../../adr/0017-llm-orchestration-openrouter.md) (LLM) · **Last Updated:** 2026-06-04

## Summary

A front door for bringing a new character into a story. The author either describes the character in
a prompt and lets the engine draft it (**ai**), fills the card fields by hand (**manual**), or has
AI draft and then edits (**hybrid**). Either way the result is **committed authoring rows** —
`characters` + chapter-1 `character_cards` + `registers` + `sensitivities` — "ready to use." A new
global **`character_archetypes`** library (e.g. *koakuma*) seeds creation so common shapes do not
start blank.

## Goal & non-goals

- **Goal:** create a usable, spoiler-bounded character through one of three modes, ending in
  committed rows via the shared review gate; let an archetype pre-fill the starting point.
- **Non-goals:** the [ADR 0013](../../adr/0013-authoring-and-compile-pipeline.md) compile mechanics (reused as-is); edge seeding (happens at session
  fork, [ADR 0002](../../adr/0002-relationship-edge-schema.md)/[0012](../../adr/0012-persistence-schema.md)); per-chapter recompile beyond chapter 1 ([ADR 0013](../../adr/0013-authoring-and-compile-pipeline.md) §4).

## Behavior

Authoring-time only (never inside the runtime loop). Three modes converge on the same compile +
review + commit:

- **ai** — seed `{name, role, traits, archetype?}` → `compiler` role drafts a **bible** to
  `content/bibles/<slug>.md` (`bible_generate` review) → [ADR 0013](../../adr/0013-authoring-and-compile-pipeline.md) compile of each artifact →
  review → commit.
- **manual** — forms fill the card fields directly (`folded_identity`, `knowledge_boundary`,
  `disposition_priors`, `voice`, `tells`, `live_axes`, `base_opacity`, `model_tier`) + registers +
  sensitivities; bible optional (`bible_path` may be null). No LLM — works with no API key.
- **hybrid** — AI pre-fills, human edits every artifact at the review gate.

`knowledge_boundary` is mandatory in **all** modes. The archetype picker pre-fills priors /
registers / sensitivities / voice / `base_opacity`; every field stays editable.

## Data touched

New: `character_archetypes` (global), `review_items.producer_type = bible_generate`, `creation_mode`
process metadata on the review payload. Writes (on commit): `characters`, `character_cards`,
`registers` (+ promoted `register_archetypes`), `sensitivities`. See
[../../architecture/DATABASE.md](../../architecture/DATABASE.md) §3.2–3.7, §3.13. AI bibles live at `content/bibles/<slug>.md`
(resolves [PH-6](../../guides/PLACEHOLDER_TRACKING.md)).

## Agent / isolation impact

Runs as the **authoring-time compiler** (omniscient over the source), gated by the [ADR 0013](../../adr/0013-authoring-and-compile-pipeline.md) §3
`knowledge_boundary` clamp + the review gate. Adds **no** new leak guard. Manual bible-less cards are
an accepted divergence from [ADR 0001](../../adr/0001-character-data-three-layer-separation.md) (logged in [PLACEHOLDER_TRACKING](../../guides/PLACEHOLDER_TRACKING.md)) — the card is already the
spoiler-bounded slice.

## Acceptance criteria

- [ ] A character can be created in each of the three modes and ends with committed `characters` +
  chapter-1 `character_cards` rows.
- [ ] An archetype pre-fills the seed/forms and every field remains editable before commit.
- [ ] Manual mode works with no LLM key configured.
- [ ] AI/hybrid bibles are written under `content/bibles/` and referenced by `bible_path`.
- [ ] `knowledge_boundary` is required and clamped per the reveal ledger before any card is committed.

## Open questions

- Does the archetype library ship seeded with more than *koakuma* at v1, or grow per story?
- Migrate `luna-archi.md` into `content/bibles/` now, or leave at root until the build round?

## Related Documentation

- ADR: [0018](../../adr/0018-character-creation-pipeline.md) · [0013](../../adr/0013-authoring-and-compile-pipeline.md) · [0017](../../adr/0017-llm-orchestration-openrouter.md) · [0006](../../adr/0006-register-relational-mode-system.md) · [0005](../../adr/0005-appraisal-trigger-taxonomy.md)
- Architecture: [DATABASE.md](../../architecture/DATABASE.md) (§3.13 `character_archetypes`)
- Open items: [GAPS O5](../../adr/GAPS.md)
