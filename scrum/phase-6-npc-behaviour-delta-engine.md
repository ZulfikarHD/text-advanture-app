# Phase 6: Runtime — NPC Behaviour & Delta Engine — Directed Interactive Novel Engine
## Directed Interactive Novel Engine (DINE)

**Timeline:** ~2.75 Months (11 Sprints)
**Sprint Duration:** 1 Week
**Team Size Recommendation:** 1 Full-stack Dev (+ QA for isolation/leak negative tests)
**Depends on:** Phase 5 (the narrator loop + recorder `surface` to appraise, POV projection, session/state machine)
**Governing ADRs:** 0007 (NPC assembly), 0002 (edge schema), 0003 (delta engine), 0004 (decay + scars), 0005 (trigger taxonomy), 0006 (register system), 0008 (psychological nudge), 0014 (internal state) + orchestration (GAPS O4)

> Goal: stand up the full NPC psychology + relationship simulation at runtime. This phase builds the **NPC context assembler** (the compile→act isolation boundary), **edges/axes/awareness** at read time, the **delta engine** (drift vs rupture + appraisal proposals + review→commit + append-only audit), **decay & latched scars**, the **internal state `[SELF]`** (emotions, mood, motivation, masks), **register resolution & expression masks**, the **runtime nudge** (bias term, escalation ladder, ceiling), the **interaction queue**, and the **compile→act orchestration** that sequences a ~10+-call beat without freezing the player. After this phase a beat runs end-to-end: NPCs act in character, relationships move for explainable reasons, and every movement is auditable.

---

## Product Backlog Overview

### Epic Summary

| Epic ID | Epic Name | Priority | Story Points | Sprints |
|---------|-----------|----------|--------------|---------|
| E1 | NPC Context Assembly — compile→act | Critical | 28 | 35–36 |
| E2 | Edges, Axes & Awareness (runtime) | Critical | 5 | 37 |
| E3 | Delta Engine — Drift vs Rupture | Critical | 29 | 37–39 |
| E4 | Decay & Latched Scars | High | 15 | 40–41 |
| E5 | Internal State `[SELF]` | High | 15 | 41–42 |
| E6 | Register Resolution & Expression Masks | High | 13 | 43 |
| E7 | Psychological Nudge — runtime | High | 15 | 44 |
| E8 | Interaction Queue | Medium | 11 | 45 |
| E9 | Compile→Act Orchestration | High | 13 | 45 |

**Total Estimated:** ~144 Story Points

---

## EPIC E1: NPC Context Assembly — compile→act

### E1.1 — Assemble & Act

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.1.1 | As a **system**, I want to assemble the NPC prompt from registry-driven blocks (IDENTITY, SELF, SNAPSHOT, MASKS, DIRECTIVES, NUDGE, SCENE_RULES + user SCENE_EXCERPT) reading only this NPC's own data + its witnessed projected surface so that isolation is enforced by construction | 8 | Critical | 35 |
| S-1.1.2 | As a **system**, I want awareness-fold applied (merge value×awareness so a capped feeling is never stated plainly) and own-perspective-only enforced so that a blind-spot feeling cannot become self-aware and no other edge is exposed | 5 | Critical | 35 |
| S-1.1.3 | As a **system**, I want the two-stage turn (compile → act) with stable blocks cached within a scene and the volatile snapshot recompiled after deltas, plus model tiering (major=strong, minor=cheap), so that cost/latency are controlled | 5 | High | 36 |
| S-1.1.4 | As a **system**, I want the act call to produce the in-character response so that the NPC acts within the limits of what it knows | 5 | Critical | 36 |

**Acceptance Criteria - S-1.1.1:**
```gherkin
Feature: NPC Prompt Assembly from Registry Blocks

Scenario: Assemble an NPC system prompt from this NPC's own data only
  Given a beat where Luna and a classmate are present
  And it is Luna's turn to act
  When the assembler compiles Luna's prompt
  Then the system prompt contains the blocks in their registry-declared order:
    IDENTITY, SELF, SNAPSHOT, MASKS, DIRECTIVES, NUDGE, SCENE_RULES
  And the user prompt contains the SCENE_EXCERPT plus the acting question "How does Luna respond?"
  And every block is sourced only from Luna's own card, her own edges, her own internal state,
    the leak-checked nudge addressed to her, and the witnessed scene surface

Scenario: Block selection and order are driven by the prompt-block registry
  Given the prompt-block registry declares each block's agent, section, label, and order
  When the assembler builds an NPC prompt
  Then only blocks whose agent is "npc" (or "both") are included
  And each block renders under its declared label (e.g. [SNAPSHOT], [MASKS]) in its declared section and order

Scenario: A block with no data is omitted, not invented
  Given Luna has no active nudge for this beat
  When her prompt is assembled
  Then the NUDGE block is absent
  And no placeholder pressure or filler is injected to occupy the slot
```

**Acceptance Criteria - S-1.1.2:**
```gherkin
Feature: Awareness-Fold and Own-Perspective Rendering

Scenario: A capped-high feeling renders as felt-but-unnameable
  Given Luna's edge toward Vixia has a romantic axis at value 84 with awareness mode "capped"
  When the SNAPSHOT block is compiled
  Then the feeling is rendered as something she feels but cannot name (e.g. "a pull she can't account for")
  And the plain feeling ("she is in love with Vixia") never appears in the prompt
  And the raw value and the awareness mode are never shown as separate, plain fields

Scenario: The value-derived awareness tier colours the phrasing
  Given an affection axis at value 50 with awareness mode "auto" (vague tier)
  When the SNAPSHOT is compiled
  Then the feeling is phrased as faint / half-noticed rather than clearly known

Scenario: Only the acting character's own edges are exposed
  Given it is Luna's turn
  When the SNAPSHOT is compiled
  Then it contains only edges Luna owns (Luna → others)
  And no edge owned by another character (e.g. classmate → Luna) appears
  And how anyone else feels about Luna is never stated
```

**Acceptance Criteria - S-1.1.3:**
```gherkin
Feature: Two-Stage Turn with Caching and Model Tiering

Scenario: Stable blocks are cached within a scene
  Given Luna has already acted once in the current scene
  And her IDENTITY and resolved-register DIRECTIVES have not changed
  When she acts again later in the same scene
  Then the cached stable blocks are reused rather than recompiled

Scenario: The volatile snapshot recompiles after a committed delta
  Given a trust delta on Luna's edge toward the classmate was committed since her last turn
  When her next turn is assembled
  Then the SNAPSHOT block is recompiled from the updated axis values
  And the stable blocks remain served from cache

Scenario: Each NPC turn is exactly two model calls
  Given any NPC takes a turn
  Then the turn comprises a compile call (structured state → folded prose blocks)
    and an act call (in-character response)
  And both are recorded to the call log with role, tokens, cost, and latency

Scenario: Model tier follows the character
  Given Luna is a major NPC and a background classmate is a minor NPC
  When each one's turn is assembled
  Then the major NPC resolves to the stronger model role and a full card
  And the minor NPC resolves to the cheaper model role and a compressed card
```

**Acceptance Criteria - S-1.1.4:**
```gherkin
Feature: In-Character Act Call

Scenario: The NPC acts within the limits of what it knows
  Given Luna's compiled prompt embeds only her witnessed surface and her own state
  And a secret she did not witness (the diagnosis) was discussed out of her perception
  When the act call runs
  Then her response is generated in character
  And it never references the unwitnessed secret

Scenario: The response respects the resolved register surface
  Given Luna toward the classmate resolves to koakuma_default (sealed, clean-exit)
  When the act call runs
  Then her reply stays sealed and exits cleanly rather than becoming transparent

Scenario: Act output is the NPC's contribution, not a state write
  Given the act call completes
  Then its output is the character's observable speech/action for this moment
  And it is handed back to the loop to be recorded (witness-tagged)
  And the act call itself commits no axis change
```

> **Technical Notes E1.1:**
> - **Business Logic:**
>   - The assembler is both a **compiler** (structured state → folded prose blocks) and the **isolation boundary** — the one place that guarantees an NPC sees only its own data plus what it witnessed.
>   - Inputs are **this NPC only**: its card (+ `knowledge_boundary`), internal state (`[SELF]`), its own edges to the present characters (folded `value×awareness`), `topic_flags` + masks, the resolved register → directives, the leak-checked beat nudge, scene config, and the recorder `surface`.
>   - **Numbers → language:** each live axis renders via a `(value × awareness)` translation; **own-perspective only** — never expose how others feel or any edge this NPC does not own.
>   - The **SCENE_EXCERPT** is the recorder's `surface` layer, **witness-filtered** (`witnessed_by` contains this NPC), then **POV-projected**, decoded through the register's `reads_target` (accurate vs crashes), and **`knowledge_boundary`-validated**.
>   - Block selection / order / label / fold instruction / leak-rules are driven by the **prompt-block registry** (data-driven assembly).
>   - **Two LLM calls per NPC turn** (compile + act); stable blocks (identity, register) cached within a scene, the volatile snapshot recompiled after deltas land. Model tiering: major = full card / strong model, minor = compressed / cheap.
> - **Reference:** ADR 0007 / 0009 / 0020.

---

### E1.2 — Isolation Tests

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.2.1 | As a **system**, I want negative tests proving the beat doc, other cards/edges, other characters' true_state, and narrator instructions never reach an NPC prompt so that the isolation boundary is verified, not assumed | 5 | Critical | 36 |

**Acceptance Criteria - S-1.2.1:**
```gherkin
Feature: Isolation Boundary Negative Tests

Scenario: The beat document never reaches an NPC prompt
  Given a beat whose omniscient intent reads "corner him; she must NOT learn it was arson"
  When Luna's prompt is assembled
  Then the raw beat intent appears nowhere in her prompt
  And only the compiled, leak-checked nudge (if any) is present

Scenario: Another character's card or edges never reach an NPC prompt
  Given the classmate's card and the classmate → Luna edge both exist
  When Luna's prompt is assembled
  Then neither the other character's card nor any edge Luna does not own appears

Scenario: Another character's private true_state never reaches an NPC prompt
  Given a beat record with a public surface and per-character private true_state
  When Luna's prompt is assembled
  Then only the surface (witness-filtered, POV-projected) is read
  And a query that reads the surface cannot return any other character's true_state

Scenario: Narrator instructions never reach an NPC prompt
  Given narrator-side directives and omniscient scene notes exist for the beat
  When any NPC prompt is assembled
  Then no narrator instruction is included

Scenario: Isolation holds at the cheapest model tier
  Given a minor NPC assembled on the cheap model role
  When its prompt is built
  Then the same forbidden inputs are absent — safety does not depend on the model tier
```

> **Technical Notes E1.2:**
> - **Business Logic:**
>   - Isolation is enforced **by construction** and must be **verified, not assumed** — every assembly story carries explicit **negative tests** that assert forbidden data never reaches a prompt.
>   - Forbidden inputs (must never appear in an NPC prompt): the **beat doc / raw beat intent**, any **other character's card**, any **edge this NPC does not own**, any **other character's `beat_true_state`**, and **narrator instructions / omniscient scene notes**.
>   - The "read `surface` only" path **physically cannot** pull another character's private `true_state` (it lives in a separate child table by design).
>   - Safety must hold at **any model tier** — a cheap minor-NPC model is as isolated as a strong major-NPC model.
> - **Reference:** ADR 0007 / 0009.

---

## EPIC E2: Edges, Axes & Awareness (runtime)

### E2.1 — Read & Translate

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.1.1 | As a **system**, I want to compute the awareness tier from value (auto: 0–39 none / 40–59 vague / 60–79 subconscious / 80+ conscious) or honor capped, and translate (value×awareness) to language, with effective floor = max(soft_floor, scar.floor), so that the snapshot reflects self-perceived feeling correctly | 5 | Critical | 37 |

**Acceptance Criteria - S-2.1.1:**
```gherkin
Feature: Awareness Tier and Numbers-to-Language Translation

Scenario: Awareness tier is computed from value at read time (auto)
  Given an affection axis with awareness mode "auto"
  When the snapshot is read at the following values:
    | value | tier         |
    | 30    | none         |
    | 50    | vague        |
    | 70    | subconscious |
    | 90    | conscious    |
  Then the rendered self-perception matches the tier
    (none = not noticed, vague = faint, subconscious = half-known, conscious = clearly known)
  And no awareness tier is stored — it is derived on each read

Scenario: A capped axis ignores the value-derived tier
  Given a romantic axis at value 84 with awareness mode "capped"
  When the snapshot is read
  Then it is rendered as felt-but-unnameable regardless of how high the value is
  And it is treated as a blind spot, never surfaced as conscious

Scenario: Effective floor honours a latched scar
  Given an axis with soft_floor = 40 and a latched scar floor = 49
  Then the effective floor used for translation and clamping is max(40, 49) = 49

Scenario: Bounds are invisible to the character
  Given an axis with soft_cap = 100, hard_floor = 40, and asymmetric gain/loss rates
  When the snapshot is rendered
  Then no cap, floor, rate, peak, or baseline appears in the prompt
  And the character is rendered as simply living inside its bounds
```

> **Technical Notes E2.1:**
> - **Business Logic:**
>   - Edges are **directed, owner-perspective** (`from`'s self-perceived view of `to`; can be self-deceived); only the declared **live axes** are instantiated.
>   - **Awareness is computed at read time** from `|value|`: `0–39 none · 40–59 vague · 60–79 subconscious · 80+ conscious`. No stored tier. `mode: capped` overrides the auto tier → blind spot ("feels it, can't name it").
>   - `(value × awareness)` is the translation that turns the number into language for the SNAPSHOT.
>   - **Bounds** (soft/hard floors and caps), **rates**, **peaks**, and **baseline** are authorial and **invisible to the character**.
>   - **Effective floor = `max(soft_floor, scar.floor)`** — a latch raises the floor a read/clamp respects.
> - **Reference:** ADR 0002 / 0007.

---

## EPIC E3: Delta Engine — Drift vs Rupture

### E3.1 — Appraisal Proposals

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.1.1 | As a **system**, I want appraisal (per present character, reading ITS OWN projected surface) to match sensitivities (universal + card + acquired) with match-only salience and emit a proposal per match (multiple matches → multiple proposals) so that meaningful contradictions are manufactured, not resolved | 8 | Critical | 37 |
| S-3.1.2 | As a **system**, I want magnitude ≈ weight × LLM-judged severity (shared rubric) with betrayal/confession/abandonment categorically rupture, and appraisal also emitting emotion proposals, so that both edge and internal-emotion changes come from one pass | 5 | Critical | 38 |
| S-3.1.3 | As a **system**, I want vicarious targeting (actor/beneficiary/witnessed_third_party) so that watching A protect B can shift the observer's regard for A | 3 | High | 38 |

**Acceptance Criteria - S-3.1.1:**
```gherkin
Feature: Appraisal Proposals from Matched Sensitivities

Scenario: Each present character appraises only its own witnessed surface
  Given Luna and Henrik both witnessed a beat
  When appraisal runs
  Then Luna's appraisal reads only Luna's projected surface and Luna's sensitivity set
  And Henrik's appraisal reads only his own
  And no character appraises another character's interiority

Scenario: Match-only salience emits nothing when no sensitivity matches
  Given an event matching none of Luna's sensitivities (universal, card, or acquired)
  When appraisal runs
  Then no proposal is emitted for Luna
  And she remains numb to what she does not care about

Scenario: Multiple matches emit multiple proposals, left unresolved
  Given a single line of praise that matches both "genuine_acknowledgment" (respect up)
    and "pitied_as_fragile" (affection down)
  When appraisal runs
  Then two separate proposals are emitted — respect up and affection down
  And the contradiction is not reconciled; both are carried forward

Scenario: The effective sensitivity set includes acquired triggers
  Given Luna carries an acquired sensitivity installed by an earlier rupture (a scar trigger)
  When an event matches that acquired trigger
  Then a proposal is emitted exactly as it would for an authored sensitivity
```

**Acceptance Criteria - S-3.1.2:**
```gherkin
Feature: Magnitude, Categorical Ruptures, and Emotion Proposals

Scenario: Magnitude scales weight by judged severity
  Given a matched sensitivity with weight "high"
  And the event severity is judged "notable" on the shared rubric
  Then the proposed magnitude ≈ weight × severity, landing in the notable band as drift

Scenario: Severity maps to magnitude and channel via the shared rubric
  Given the shared severity rubric
  Then the bands map as:
    | severity   | magnitude | channel |
    | negligible | 0–1       | drift   |
    | minor      | 1–3       | drift   |
    | notable    | 3–8       | drift   |
    | major      | 8–20      | rupture |
    | defining   | 20–50     | rupture |

Scenario: Betrayal, confession, and abandonment are categorically rupture
  Given an event recognised as a betrayal regardless of judged degree
  When appraisal proposes
  Then the proposal channel is rupture, not drift

Scenario: One pass emits both edge and emotion proposals
  Given the event "he dodged the question" matches a trust sensitivity
  When appraisal runs
  Then it may emit a trust edge-delta proposal (down)
  And an emotion proposal that installs/raises "anxious"
  And both carry a mandatory trigger and go to the same review gate
```

**Acceptance Criteria - S-3.1.3:**
```gherkin
Feature: Vicarious Targeting

Scenario: Watching A protect B shifts the observer's regard for A
  Given Luna witnesses Henrik shielding Vixia from harm
  And Luna carries a sensitivity targeting a witnessed_third_party who protects Vixia
  When appraisal runs for Luna
  Then a proposal raises Luna's respect/affection toward Henrik
  And Henrik never acted on Luna directly

Scenario: Targeting distinguishes actor, beneficiary, and third party
  Given a sensitivity whose target is "actor"
  When an event has a distinct actor and beneficiary
  Then the sensitivity matches on the actor, not the beneficiary
```

> **Technical Notes E3.1:**
> - **Business Logic:**
>   - Appraisal runs **per present character, reading ITS OWN projected surface only** — never another character's `true_state`.
>   - **Effective sensitivity set = card sensitivities + universal priors + acquired (runtime-installed)**. Universal priors are baseline humanity; the card layer amplifies/dampens/special-cases; acquired are scar triggers installed by ruptures.
>   - **Match-only salience:** no matched sensitivity → no delta. **Multiple matches → multiple proposals**; contradictions are **manufactured, not resolved**.
>   - `magnitude ≈ weight × LLM-judged severity` against the **shared severity rubric** (tunable config). **betrayal / confession / abandonment** are categorically `rupture_only` — a *kind* of event, not a magnitude.
>   - The same pass emits **emotion proposals** (ADR 0014) alongside edge deltas. **Appraisal proposes, never writes.**
>   - `target ∈ {actor, beneficiary, witnessed_third_party}` enables **vicarious** shifts and NPC-to-NPC mesh dynamics.
> - **Reference:** ADR 0003 / 0005 / 0014.

---

### E3.2 — Review → Commit + Audit

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.2.1 | As a **system**, I want every proposal to carry a mandatory trigger, pass the review gate (accept/edit/reject), and commit to an append-only delta log so that every axis movement is explainable and auditable | 5 | Critical | 38 |
| S-3.2.2 | As a **system**, I want drift batched at SCENE_DONE clamped to soft bounds, and ruptures applied immediately reaching hard bounds (and able to rewrite bounds / flip register / install acquired sensitivities) so that ordinary events nudge slowly while high-impact events change character | 8 | Critical | 39 |

**Acceptance Criteria - S-3.2.1:**
```gherkin
Feature: Mandatory Trigger, Review Gate, and Append-Only Audit

Scenario: A proposal without a trigger cannot commit
  Given a delta proposal with an empty trigger
  When commit is attempted
  Then it is rejected as invalid
  And the producer must supply the matched sensitivity's self-naming reason

Scenario: Accept commits the proposal as an append-only delta
  Given a pending trust-down proposal of magnitude 18 with a trigger
  When it is accepted
  Then a delta row is appended recording axis, direction, magnitude, channel, trigger,
    value_before, value_after, and source
  And no prior delta row is modified or deleted

Scenario: Edit commits the adjusted magnitude
  Given a pending proposal of magnitude 18
  When the magnitude is edited to 10 and committed
  Then the committed delta records magnitude 10 with source "review_edit"

Scenario: Reject commits nothing but remains auditable
  Given a pending proposal
  When it is rejected
  Then no axis moves
  And the item is recorded as rejected with who/when

Scenario: Every committed movement is explainable
  Given any committed axis movement
  Then it carries a human-readable trigger and links to its review item
  And there are no silent deltas
```

**Acceptance Criteria - S-3.2.2:**
```gherkin
Feature: Drift Batching vs Immediate Ruptures

Scenario: Drift is batched at the scene boundary and clamped to soft bounds
  Given several accepted drift proposals accrued during a scene (small affection gains)
  When SCENE_DONE fires
  Then the accepted drift is applied as a batch
  And the resulting value is clamped to the soft bounds (drift can never cross a soft cap/floor)
  And the scene summary marks drift as applied

Scenario: A rupture applies immediately and may reach the hard band
  Given a betrayal rupture of magnitude 30 is accepted mid-scene
  When it commits
  Then the affected axis moves immediately, in-scene
  And it may pass the soft floor and reach toward the hard floor

Scenario: A rupture may rewrite bounds, flip the register, and install acquired sensitivities
  Given a defining rupture commits
  Then it may lower the hard_floor or rewrite bounds (character development)
  And it may flip the edge's base register
  And it may install an acquired sensitivity (a scar trigger) for future appraisal

Scenario: Ordinary events nudge slowly; high-impact events change character
  Given a hundred small kindnesses processed as drift
  Then trust climbs slowly and cannot break its soft cap
  Given one betrayal processed as a rupture
  Then trust can blow through the soft floor and permanently lower the hard floor
```

> **Technical Notes E3.2:**
> - **Business Logic:**
>   - **Appraisal proposes; the review gate commits** — the appraisal/rupture path never writes the edge directly.
>   - `trigger` is **mandatory** on every proposal = the matched sensitivity **naming itself**. Committed deltas form an **append-only** per-edge audit log; corrections are **new rows** (never UPDATE/DELETE).
>   - **DRIFT:** batched at `SCENE_DONE`, clamped to **soft** bounds. **RUPTURE:** applied **immediately** in-scene, may reach the **soft↔hard** band, **rewrite bounds**, **flip the register**, and **install acquired sensitivities** (scar triggers).
>   - **Emotion proposals** share the same gate and the same mandatory-trigger rule.
> - **Reference:** ADR 0003 / 0005 / 0014.

---

## EPIC E4: Decay & Latched Scars

### E4.1 — Decay

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.1.1 | As a **system**, I want narrative-time decay at boundaries (scaled by the elapsed bucket; pulls value toward baseline; stops at the latched floor; committed/latched edges exempt) so that relationships cool from neglect without erasing development | 5 | High | 40 |

**Acceptance Criteria - S-4.1.1:**
```gherkin
Feature: Narrative-Time Decay

Scenario: Decay scales by the declared elapsed bucket, not by beat count
  Given a scene boundary declaring an elapsed bucket of "weeks"
  When decay runs
  Then each non-exempt axis is pulled toward its baseline scaled by the "weeks" bucket
  And a "continuous" boundary produces ~no decay regardless of how many beats passed

Scenario: Decay stops at the latched floor
  Given an axis at value 70 with baseline 0 and a latched scar floor 49
  When decay runs over a long gap
  Then the value cools toward baseline but never crosses 49

Scenario: Committed and latched edges are decay-exempt
  Given an axis whose effective floor comes from a latch
  When decay runs
  Then the latched development is never erased
  And only the acute value above the effective floor cools

Scenario: Decay is on narrative time only
  Given two boundaries, one "continuous" and one "months"
  When decay runs
  Then only the "months" boundary (real gap of days+) produces meaningful decay
  And this is independent of how finely the story was sliced
```

> **Technical Notes E4.1:**
> - **Business Logic:**
>   - Decay runs at **scene / chapter boundaries** (piggybacks the compression step), scaled by the **declared in-world elapsed bucket** (`continuous · hours · days · weeks · months · longer`) — **never** by beat/chapter count.
>   - It **pulls the value toward `baseline`**, **stopping at the effective (latched) floor**. **Committed/latched edges are decay-exempt** by construction.
>   - Decay is on **narrative time only** (a real gap of days+); same-scene continuation produces ~no decay.
> - **Reference:** ADR 0004 / 0015.

---

### E4.2 — Scars

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.2.1 | As a **system**, I want a latch (when a peak crosses latch_threshold, set a permanent floor: commitment = positive latch, trauma = high-magnitude latch) so that high feelings leave permanent marks | 5 | High | 40 |
| S-4.2.2 | As a **system**, I want scar triggers to rupture-spike the value back toward peak, and only a deliberate growth-arc rupture to clear a latched floor, so that "something reminds her" works and scars don't fade on their own | 5 | High | 41 |

**Acceptance Criteria - S-4.2.1:**
```gherkin
Feature: Latched Scars (Commitment and Trauma)

Scenario: A peak crossing the latch threshold sets a permanent floor (commitment)
  Given an affection axis with latch_threshold 80 and latch_retain 0.6
  When its upward peak reaches 82
  Then a scar latches with floor = round(0.6 × 82) = 49
  And this positive latch models commitment (won't abandon over small things)

Scenario: A high-magnitude fear latch models trauma
  Given a fear axis that peaks at 85 over latch_threshold 80
  When the latch fires
  Then a permanent floor is set (a lingering flaw / trauma)
  And the scar records its source (e.g. "kidnapping, Saga 2")

Scenario: The effective floor reflects the new latch
  Given soft_floor 40 and a newly latched scar floor 49
  Then the effective floor becomes max(40, 49) = 49
```

**Acceptance Criteria - S-4.2.2:**
```gherkin
Feature: Scar Triggers and Clearing a Latch

Scenario: A scar trigger rupture-spikes the value back toward peak
  Given a latched fear scar with triggers ["confinement", "Solenne's scream"] and an upward peak of 85
  When an event matches "confinement"
  Then a rupture spikes the fear value back up toward its peak
  And it reads as "something reminds her"

Scenario: Time and ordinary drift cannot clear a latch
  Given a latched floor of 49
  When long gaps and ordinary drift accrue
  Then the floor remains — scars do not fade on their own

Scenario: Only a deliberate growth-arc rupture clears the floor
  Given a latched floor
  When a growth-arc rupture explicitly chooses to overcome it
  Then the latched floor is lowered or cleared and "overcome_by" is recorded
  And nothing else (time, drift, decay) can clear it
```

> **Technical Notes E4.2:**
> - **Business Logic:**
>   - A **latch** fires when a per-direction **peak** crosses `latch_threshold`: `scar.floor = latch_retain × peak`. **Commitment = positive latch; trauma = high-magnitude latch** (one unified mechanism).
>   - A scar stores **triggers** that fire **ruptures** (ADR 0003), spiking the value back toward `peak` ("something reminds her").
>   - **Effective floor = `max(soft_floor, scar.floor)`.** Time and ordinary drift **cannot** clear a latch — **only a deliberate growth-arc rupture** sets `overcome_by` and clears it.
> - **Reference:** ADR 0004 / 0015.

---

## EPIC E5: Internal State `[SELF]`

### E5.1 — Emotions

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.1.1 | As a **system**, I want active_emotions (free-text label, intensity, baseline, source) installed/raised by appraisal, with emotions that never latch, so that transient feeling is modeled separately from edges | 5 | High | 41 |
| S-5.1.2 | As a **system**, I want the own-clock drift (small bounded reversion to baseline on-screen; bounded random within ±cap off-screen; explicit-narration override sets the value deterministically) so that emotions ease off honestly across gaps without running away | 5 | High | 42 |

**Acceptance Criteria - S-5.1.1:**
```gherkin
Feature: Active Emotions

Scenario: Appraisal installs or raises an active emotion through the gate
  Given an emotion proposal "anxious" at intensity 40 is accepted
  When it commits
  Then an active emotion (label, intensity, baseline, source) is installed or raised on the character's [SELF]

Scenario: Emotions carry a baseline distinguishing acute from chronic
  Given a spontaneous "startled" emotion
  Then its baseline is 0 (acute)
  Given Luna's chronic low-grade guilt seeded from the card
  Then its baseline is non-zero (a chronic resting level)

Scenario: Emotions never latch
  Given any active emotion, however intense
  When boundaries pass
  Then it never sets a permanent floor
  And durable change is recorded on the edge, not on the emotion
```

**Acceptance Criteria - S-5.1.2:**
```gherkin
Feature: Emotion Own-Clock Drift

Scenario: On-screen reversion eases an acute emotion toward baseline
  Given an "anxious" emotion at intensity 40 with baseline 0
  And no event sustains it
  When a boundary applies the on-screen reversion
  Then intensity steps toward baseline by a small bounded amount (cap ≈ ±3)

Scenario: An off-screen gap applies a bounded random wobble
  Given a timeskip where what happened off-screen is unknown
  When the off-screen drift rolls
  Then the emotion moves by at most the cap (±3), mean-reverting toward baseline
  And a long gap can never swing it beyond the cap (the drift caps out, it does not scale with gap length)

Scenario: Explicit narration overrides the roll deterministically
  Given the continuation narrates the gap ("three weeks, they barely spoke")
  When drift is applied
  Then the explicit signal sets the value deterministically
  And no random roll is taken
```

> **Technical Notes E5.1:**
> - **Business Logic:**
>   - `active_emotions`: **free-text label**, `intensity` 0–100, `baseline` (0 = acute, non-zero = chronic, seeded from the card), `source` (appraisal/rupture/authored), own-clock markers. Installed/raised by appraisal's **emotion proposals** through the same review gate.
>   - **Emotions never latch** — durable change is recorded on the **edge**; an emotion may *feed* an edge delta (appraisal's job) but leaves no permanent floor.
>   - **Own clock:** on-screen = a **small bounded reversion** toward baseline (cap ≈ **±3**, shared config); off-screen gap = a **bounded random** step within ±cap, mean-reverting and **clamped** (never scales with gap length); **explicit narration** sets the value deterministically (no roll).
> - **Reference:** ADR 0014.

---

### E5.2 — Mood / Motivation / Masks

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.2.1 | As a **system**, I want mood as a derived rollup (+ optional override), structured motivation (drive/goal/source), and masks (global + state) so that the SELF block and the interaction queue have a fully-specified source | 5 | High | 42 |

**Acceptance Criteria - S-5.2.1:**
```gherkin
Feature: Mood, Motivation, and Masks

Scenario: Mood is a derived rollup of active emotions
  Given active emotions guilt 30 and anxious 50
  When mood is computed
  Then it is the dominant/blended feeling derived from the active emotions (one source of truth)

Scenario: An author override pins the mood when a scene needs it
  Given an author has set a mood override
  When the [SELF] block is built
  Then it uses the override while the live emotions still drift underneath

Scenario: Motivation is a short structured field
  Given a character with motivation { drive, goal, source }
  When the [SELF] block and the interaction queue read it
  Then the structured drive/goal/source is available (it is not a planner)

Scenario: Masks combine global and state here, topic flags stay on the edge
  Given a global mask "cannot voice sincere gratitude" and a guilt-driven state mask
  When the [MASKS] block is built
  Then both global and state masks come from the internal state
  And topic-scoped masks remain on the edge as topic_flags
```

> **Technical Notes E5.2:**
> - **Business Logic:**
>   - `mood` is a **derived dominant/blended rollup** of `active_emotions` (cached, one source of truth), with an optional **`mood_override`**; it feeds register **surface** modulation only — never the **grammar** — and the `[SELF]` block.
>   - `motivation` is a **short structured** `{ drive, goal, source }` (not a planner); read by the **interaction queue** ("motivation strong?") and surfaced in `[SELF]`.
>   - `masks`: **global** (card-trait, always in force) + **state** (emotion-driven / obligation / self-deception) live **here**; **topic-scoped masks stay on the edge** (`topic_flags`). Together they form the `[MASKS]` block and the expression gate.
> - **Reference:** ADR 0014.

---

## EPIC E6: Register Resolution & Expression Masks

### E6.1 — Resolution & Directives

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-6.1.1 | As a **system**, I want the per-turn resolution pipeline (base/pin → threshold selector by axis value → situational override → emotional modulation of the surface only → mask + awareness gate) so that numbers become behavior without the grammar changing under emotion | 8 | High | 43 |
| S-6.1.2 | As a **system**, I want the resolved register compiled into concrete behavioral directives in the prompt so that the actor model receives an instruction set, not raw numbers | 5 | High | 43 |

**Acceptance Criteria - S-6.1.1:**
```gherkin
Feature: Per-Turn Register Resolution Pipeline

Scenario: The pipeline resolves in the fixed order
  Given a non-pinned edge
  When the register resolves for a turn
  Then it applies in order:
    base/pin → threshold selector (by axis value) → situational override
    → emotional modulation (surface only) → mask + awareness gate

Scenario: The threshold selector opens the register as an axis climbs
  Given a trust gradient with variants L1..L4
  When trust = 30 then later trust = 75
  Then the selector picks the guarded variant at 30 and a more open variant at 75
  And the change is by axis value, not by emotion

Scenario: A pinned base bypasses the threshold selector
  Given Luna → Vixia is pinned to transparent_mess
  When the register resolves at any trust level
  Then it stays transparent_mess (her reads crash with him no matter what)

Scenario: Emotional modulation shifts the surface, not the grammar
  Given Luna in koakuma_default with mood "scared"
  When the register resolves
  Then surface intensity shifts (her performance collapses toward single words)
  And the underlying grammar (sealed, clean-exit) is unchanged

Scenario: A situational override swaps the register on condition
  Given an override { when: target_shows_romantic_interest, use: boundary_protection }
  And the target shows romantic interest
  Then the resolved register becomes boundary_protection (distancing)
```

**Acceptance Criteria - S-6.1.2:**
```gherkin
Feature: Behavioral Directives from the Resolved Register

Scenario: Register dimensions compile to concrete instructions
  Given a resolved register with flow = extends-every-moment and sincerity = rerouted-through-teasing
  When the DIRECTIVES block compiles
  Then it states concrete rules such as "never close without a hook"
    and "sincere words come out as teasing"
  And the actor model receives an instruction set, not raw axis numbers

Scenario: A rupture may flip the base register
  Given a defining rupture flips Luna's base from koakuma_default to a more guarded base
  When her next turn resolves
  Then the new base drives the compiled directives
```

> **Technical Notes E6.1:**
> - **Business Logic:**
>   - **Per-turn resolution pipeline:** `base/pin → threshold selector (axis value → variant) → situational override → emotional modulation (surface only) → mask + awareness gate`.
>   - A **pinned base bypasses** the threshold selector; **emotional modulation shifts the SURFACE not the grammar**; **ruptures may flip the base**.
>   - The resolved register compiles to concrete **behavioral directives** (the `[DIRECTIVES]` block) — the actor receives instructions, never raw numbers.
> - **Reference:** ADR 0006.

---

## EPIC E7: Psychological Nudge — runtime

### E7.1 — Bias Term

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-7.1.1 | As a **system**, I want the nudge to feed the behavior equation as a register-gated bias term with internal-impulse framing, still gated by mask + awareness, so that it sets direction/pressure but never puppets the character | 5 | High | 44 |

**Acceptance Criteria - S-7.1.1:**
```gherkin
Feature: Nudge as a Register-Gated Bias Term

Scenario: The nudge enters the behavior equation as direction and pressure
  Given a beat nudge addressed to Luna
  When her behavior resolves
  Then the nudge enters as a bias term framed as her own internal impulse ("you find yourself wanting to…")
  And it is still gated by mask + awareness at the end of the pipeline

Scenario: A nudge defeated by the register is the scene
  Given "open up to him" nudged onto Luna whose disclosure register is sealed
  When she acts
  Then the sealed register suppresses the nudge — she does not open up
  And the struggle surfaces in character, not as a stage direction

Scenario: The nudge never puppets the character
  Given any nudge at any framing
  When behavior resolves
  Then it sets direction/pressure only
  And it cannot bypass the register / mask / awareness stack
```

> **Technical Notes E7.1:**
> - **Business Logic:**
>   - The nudge is the **only authorial channel** to an NPC; it feeds the ADR 0006 behavior equation as a **register-gated bias term** with **internal-impulse framing**, still **gated by mask + awareness**.
>   - It sets **direction + pressure**; the register decides **how, or whether, it surfaces** — a **resisted nudge is the drama**; it **never puppets**.
> - **Reference:** ADR 0008.

---

### E7.2 — Escalation & Ceiling

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-7.2.1 | As a **system**, I want the escalation ladder (L0 ambient → L1 preoccupation → L2 active intent → L3 urgent drive) clocked by word budget + goal-not-met, with satisfying the goal dissolving the nudge, so that pressure ratchets only while a beat stalls | 5 | High | 44 |
| S-7.2.2 | As a **system**, I want the ceiling (stall flag read by the engine only → narrator environmental push → player "Continue/Skip/Direct?" → break-glass hard directive that is player-invoked and logged) so that a stalled beat is resolved by acting around the NPC before ever overriding it | 5 | High | 44 |

**Acceptance Criteria - S-7.2.1:**
```gherkin
Feature: Stall-Driven Escalation Ladder

Scenario: The ladder climbs as the word budget depletes without the goal met
  Given a beat nudge at L0 with a per-beat word budget
  When the budget depletes and the goal is not satisfied
  Then the level climbs L0 ambient → L1 preoccupation → L2 active intent → L3 urgent drive

Scenario: Progress toward the goal does not ratchet the ladder
  Given prose is progressing toward the beat goal
  When the budget advances
  Then the satisfaction signal moves and the ladder does not climb (pantser-safe)

Scenario: Satisfying the goal dissolves the nudge
  Given an active nudge whose goal becomes satisfied
  When the goal is met
  Then the nudge dissolves and feeds the beat-done signal

Scenario: Escalation changes intensity, not autonomy
  Given the nudge at L3
  When behavior resolves
  Then each rung still passes the register/mask gate
  And only intensity rises — autonomy is not broken
```

**Acceptance Criteria - S-7.2.2:**
```gherkin
Feature: Nudge Ceiling and Break-Glass

Scenario: The stall flag is read by the engine only
  Given the ladder is exhausted past L3
  When a stall is detected
  Then a stall flag is raised that is injected into NO narrative agent (zero leak)
  And only the director/engine reads it

Scenario: The narrator environmental push acts around the NPC
  Given a stall flag is set
  When the ceiling escalates
  Then the narrator forces the topic via event/atmosphere (e.g. a burnt smell drifts in)
  And the NPC's autonomy is untouched

Scenario: The player is offered Continue/Skip/Direct
  Given the narrator push does not resolve the stall
  Then the system offers the player to continue, skip, or direct a character

Scenario: Break-glass is player-invoked and logged
  Given the player chooses to direct the character (break-glass)
  When the hard directive is issued
  Then it may override the NPC to guarantee the beat lands
  And it is recorded to the audit trail like a delta
  And it is the only path that overrides an NPC
```

> **Technical Notes E7.2:**
> - **Business Logic:**
>   - **Escalation ladder:** `L0 ambient → L1 preoccupation → L2 active intent → L3 urgent drive`, clocked by **word budget + goal-not-met** (+ manual bump). **Satisfying the goal dissolves the nudge.** Escalation changes **intensity, not autonomy** (every rung still gated).
>   - **Ceiling, in order:** ① **stall flag** (engine-only, **injected into NO narrative agent**) → ② **narrator environmental push** (act *around* the NPC) → ③ **player "Continue/Skip/Direct?"** → ④ **break-glass hard directive** (preferentially **player-invoked**, **logged**, deliberately **breaks autonomy**, audited like a delta).
>   - Steps ①–③ keep autonomy intact; ④ is the only path that overrides an NPC, is rare, and is recorded like a delta.
> - **Reference:** ADR 0008.

---

## EPIC E8: Interaction Queue

### E8.1 — Queue Resolution & Inaction Timer

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-8.1.1 | As a **system**, I want, after each speech act, per present character: relevance check → priority (RESPOND_NOW/WAIT/SILENT/INTERRUPT) → interrupt validity (axis threshold + card supports it + motivation strong) → resolve order, so that who acts at an NPC moment is decided in-character | 8 | Medium | 45 |
| S-8.1.2 | As a **system**, I want a player-inaction timer (short = others fill the silence; medium = narrator atmosphere beat; long = system prompt Continue/Skip/Direct) so that silence is handled gracefully | 3 | Medium | 45 |

**Acceptance Criteria - S-8.1.1:**
```gherkin
Feature: Interaction Queue Resolution

Scenario: Each present character is evaluated after a speech act
  Given Luna, Henrik, and Vixia are present
  When a speech act completes
  Then each present character runs a relevance check
  And is assigned a priority: RESPOND_NOW, WAIT, SILENT, or INTERRUPT

Scenario: An interrupt is honoured only when valid
  Given a character is flagged INTERRUPT
  When interrupt validity is checked
  Then it fires only if an axis threshold is met AND the card supports interrupting AND motivation is strong
  And an invalid interrupt is downgraded and does not fire

Scenario: Resolve order decides who acts in-character
  Given multiple present characters with assigned priorities
  When the queue resolves
  Then the order is decided in character (not a fixed round-robin)
  And who acts at this NPC moment follows from relevance, priority, and motivation

Scenario: Each resolved NPC turn is the two-stage compile→act
  Given the queue grants a character a turn
  When the turn runs
  Then it is the two-stage compile→act assembly
```

**Acceptance Criteria - S-8.1.2:**
```gherkin
Feature: Player-Inaction Timer

Scenario: Short inaction lets others fill the silence
  Given the player has not acted for a short interval
  When the timer elapses
  Then present characters may fill the silence (the queue resolves an NPC moment)

Scenario: Medium inaction triggers a narrator atmosphere beat
  Given a medium inaction interval elapses
  Then the narrator produces an atmosphere beat

Scenario: Long inaction prompts Continue/Skip/Direct
  Given a long inaction interval elapses
  Then the system offers the player to continue, skip, or direct
```

> **Technical Notes E8.1:**
> - **Business Logic:**
>   - After each speech act, **per present character:** `relevance check → priority (RESPOND_NOW / WAIT / SILENT / INTERRUPT) → interrupt validity (axis threshold + card supports it + motivation strong) → resolve order` — who acts at an NPC moment is decided **in-character**.
>   - **Player-inaction timer:** short → others fill the silence; medium → narrator atmosphere beat; long → system "Continue/Skip/Direct".
>   - Each NPC turn is the **two-stage compile→act**.
> - **Reference:** ADR 0016 §5.

---

## EPIC E9: Compile→Act Orchestration

### E9.1 — Sequencing & Cost Control

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-9.1.1 | As a **system**, I want to sequence the many calls per beat (narrator ×2 + per-NPC ×2) with stable-block caching, safe batching/queueing, and progressive streaming so that the player is never blocked on a frozen screen and cost is controlled | 8 | High | 45 |
| S-9.1.2 | As a **user**, I want per-beat call-count + cost visibility hooks and backpressure/limits so that runaway spend is prevented | 5 | Medium | 45 |

**Acceptance Criteria - S-9.1.1:**
```gherkin
Feature: Per-Beat Call Sequencing and Streaming

Scenario: A multi-NPC beat sequences ~10+ calls without freezing the player
  Given a 3-NPC beat (narrator ×2 + per-NPC ×2 ≈ 10+ calls)
  When the beat runs
  Then the calls are sequenced and queued safely
  And prose streams progressively
  And the player is never blocked on a frozen screen — partial prose and progress are shown

Scenario: Stable-block caching reuses the assembler's split
  Given several NPC turns occur within one scene
  When their prompts are assembled
  Then stable blocks (identity, register) are reused from cache
  And only volatile snapshots recompile
  And the LLM client stays cache-agnostic — callers decide what to cache

Scenario: Safe batching and queueing preserve ordering guarantees
  Given concurrent NPC compiles are batched
  When they are scheduled
  Then no turn is reordered ahead of a delta it depends on
```

**Acceptance Criteria - S-9.1.2:**
```gherkin
Feature: Cost Visibility and Backpressure

Scenario: Per-beat call count and cost are visible
  Given a completed beat
  When its usage is inspected
  Then the per-beat call count and accumulated cost are available
  And cost is rendered in Rupiah for display while stored as the provider-reported value

Scenario: Backpressure and limits prevent runaway spend
  Given a configured per-beat or per-session limit
  When calls approach the limit
  Then backpressure throttles or halts further calls
  And the user is informed
  And runaway spend is prevented
```

> **Technical Notes E9.1:**
> - **Business Logic:**
>   - A **3-NPC beat is ~10+ calls** (narrator ×2 + per-NPC ×2). Orchestration sequences them with **stable-block caching** (reusing the assembler's **stable/volatile split**), **safe batching/queueing**, and **progressive streaming** so the player is never blocked.
>   - The **LLM client is cache-agnostic** (callers decide caching). **Per-beat call-count + cost visibility hooks** and **backpressure/limits** cap spend; cost stored as provider-reported, rendered in Rupiah for display.
> - **Reference:** ADR 0007 / 0017, GAPS O4.

---

## Sprint Roadmap

### Sprint 35: Assembler & Awareness-Fold (E1.1)
```
Sprint 35 (Week 1):
├── S-1.1.1: Assemble NPC prompt from registry blocks (own data only)
├── S-1.1.2: Awareness-fold + own-perspective-only
└── Test: capped feeling never stated plainly; no foreign edge exposed
```

### Sprint 36: Act Call & Isolation Tests (E1.1 + E1.2)
```
Sprint 36 (Week 2):
├── S-1.1.3: Two-stage compile→act, caching, model tiering
├── S-1.1.4: In-character act call (acts within what it knows)
├── S-1.2.1: Isolation negative tests (beat doc / cards / edges / true_state / narrator)
└── Test: surface-only read cannot reach another character's true_state
```

### Sprint 37: Runtime Edges & Appraisal Proposals (E2.1 + E3.1)
```
Sprint 37 (Week 3):
├── S-2.1.1: Awareness tier + (value×awareness) translation + effective floor
├── S-3.1.1: Appraisal proposals (own surface, match-only, multiple proposals)
└── Integration: snapshot reflects self-perceived feeling correctly
```

### Sprint 38: Magnitude, Vicarious & Review→Commit (E3.1 + E3.2)
```
Sprint 38 (Week 4):
├── S-3.1.2: magnitude = weight × severity; categorical ruptures; emotion proposals
├── S-3.1.3: Vicarious targeting (actor/beneficiary/witnessed_third_party)
├── S-3.2.1: Mandatory trigger + review gate + append-only delta log
└── Test: no silent deltas; append-only audit holds
```

### Sprint 39: Drift Batching vs Ruptures (E3.2)
```
Sprint 39 (Week 5):
├── S-3.2.2: Drift batched at SCENE_DONE (soft); ruptures immediate (hard, flip register, install sensitivities)
└── Test: deterministic drift/rupture clamping
```

### Sprint 40: Decay & Latching (E4.1 + E4.2)
```
Sprint 40 (Week 6):
├── S-4.1.1: Narrative-time decay (elapsed bucket; stops at latched floor; exempt)
├── S-4.2.1: Latch (commitment = positive, trauma = high-magnitude)
└── Test: decay never crosses a latched floor
```

### Sprint 41: Scar Triggers & Active Emotions (E4.2 + E5.1)
```
Sprint 41 (Week 7):
├── S-4.2.2: Scar triggers rupture-spike; only growth-arc rupture clears a latch
├── S-5.1.1: active_emotions (label/intensity/baseline/source; never latch)
└── Integration: "something reminds her" jolt
```

### Sprint 42: Emotion Clock & SELF Source (E5.1 + E5.2)
```
Sprint 42 (Week 8):
├── S-5.1.2: Own-clock drift (on-screen reversion / off-screen bounded random / explicit override)
├── S-5.2.1: Mood rollup (+override), structured motivation, masks (global + state)
└── Integration: fully-specified [SELF] source for the assembler + queue
```

### Sprint 43: Register Resolution & Directives (E6.1)
```
Sprint 43 (Week 9):
├── S-6.1.1: Per-turn resolution pipeline (base/pin → selector → override → modulation → gate)
├── S-6.1.2: Resolved register → concrete behavioral directives
└── Test: emotion shifts surface, not grammar; pin bypasses selector
```

### Sprint 44: Runtime Nudge (E7)
```
Sprint 44 (Week 10):
├── S-7.1.1: Nudge as register-gated bias term (internal-impulse framing)
├── S-7.2.1: Escalation ladder L0–L3 (word budget + goal-not-met)
├── S-7.2.2: Ceiling (stall flag → narrator push → player prompt → break-glass)
└── Test: stall flag injected into NO narrative agent; break-glass logged
```

### Sprint 45: Interaction Queue & Orchestration (E8 + E9)
```
Sprint 45 (Week 11):
├── S-8.1.1: Queue resolution (relevance → priority → interrupt validity → order)
├── S-8.1.2: Player-inaction timer (short/medium/long)
├── S-9.1.1: Per-beat call sequencing, caching, batching, streaming
├── S-9.1.2: Cost/call-count visibility + backpressure/limits
└── Phase 6 regression + end-to-end beat (3-NPC) hardening
```

---

## Definition of Done (DoD)

See the [program DoD](./README.md#7-global-definition-of-done-dod). Phase-6 emphasis:

- [ ] **Isolation/awareness-fold negative tests:** explicit tests assert the beat doc, other cards/edges, other characters' `true_state`, and narrator instructions never reach an NPC prompt; a capped feeling is never stated plainly; isolation holds at the cheapest model tier.
- [ ] **Mandatory-trigger + append-only audit:** every committed delta and emotion carries a non-empty trigger and links to a review item; `axis_deltas` / `nudges` / `beat_records` are never UPDATE/DELETE'd — corrections are new rows.
- [ ] **Deterministic drift/rupture clamping:** drift batched at `SCENE_DONE` and clamped to soft bounds; ruptures applied immediately within the soft↔hard band; effective floor = `max(soft_floor, scar.floor)`; decay stops at the latched floor; emotion drift clamped to ±cap and overridden deterministically by explicit narration — all covered by tests.
- [ ] **Two-call turn + caching** verified; model tiering routes major→strong / minor→cheap.
- [ ] **Register pipeline:** emotion modulates the surface, not the grammar; pinned base bypasses the selector; ruptures may flip the base.
- [ ] **Nudge:** register-gated bias term; ladder ratchets only on stall; stall flag injected into no narrative agent; break-glass is player-invoked and logged.
- [ ] **Orchestration:** a ~10+-call beat streams progressively (player never blocked); per-beat call count + cost visible; backpressure/limits enforced; every call logged.

---

## Success Metrics — Phase 6

| Metric | Target | Measurement |
|--------|--------|-------------|
| NPC prompt isolation | 0 forbidden inputs | Negative tests pass on every assembly path and at every model tier |
| Awareness-fold safety | 0 plainly-stated capped feelings | Snapshot rendering tests for capped axes |
| Delta auditability | 100% of committed deltas carry a trigger | Audit-log scan; no silent deltas |
| Append-only integrity | 0 UPDATE/DELETE on audit tables | Schema/test enforcement |
| Drift/rupture clamping | 100% within correct bounds | Drift ≤ soft bounds; rupture ≤ hard bounds; effective-floor tests |
| Decay correctness | 0 floors crossed | Decay stops at latched floor; continuous boundary → ~0 decay |
| Emotion drift containment | ≤ ±cap per gap | Off-screen wobble clamped; explicit override deterministic |
| Beat responsiveness | No frozen screen | 3-NPC beat streams partial prose + progress < perceptible block |
| Cost visibility | 100% of beats | Per-beat call count + cost rendered; backpressure trips at limit |

---

## Risk Register — Phase 6

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Context-isolation leak (forbidden data reaches an NPC prompt) | Critical | Medium | Assembler is the single boundary; registry-driven block selection; explicit negative tests on every path; separate `true_state` table; safety verified at all tiers |
| Capped feeling becomes self-aware (awareness-fold fails) | Critical | Medium | Fold `value×awareness` in the rendered prompt only; no separate AWARENESS block; capped renders "feels it, can't name it"; rendering tests |
| Silent or unexplainable axis movement | High | Medium | Mandatory `trigger` on every proposal; review gate before commit; append-only audit log feeds the relationship viewer |
| Drift/rupture clamping wrong (ceilings broken by ordinary events) | High | Medium | Drift→soft, rupture→hard; deterministic clamp tests; effective floor = max(soft_floor, scar.floor) |
| Scar/decay erases character development | High | Low | Latched/committed edges decay-exempt; decay stops at floor; only growth-arc rupture clears a latch |
| Emotion drift runs away over long gaps | Medium | Medium | Bounded ±cap mean-reverting wobble; explicit-narration override; emotions never latch |
| Nudge puppets the character (autonomy lost) | High | Medium | Bias term gated by register + mask + awareness; escalation changes intensity not autonomy; override only as logged, player-invoked break-glass |
| Runtime cost/latency too high (a beat is ~10+ calls) | High | High | Model tiering, stable-block caching, safe batching, progressive streaming, per-beat cost visibility + backpressure/limits |
| Stall flag leaks into a narrative agent | Critical | Low | Stall flag is engine-only, injected into NO narrative agent; ceiling acts around the NPC first |

---

*Document Version: 1.0 · Author: Zulfikar Hidayatullah · Created: June 2026*
