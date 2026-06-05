# 0017 — LLM orchestration & OpenRouter client

- **Status:** Proposed
- **Date:** 2026-06-04

> **Implementation note (Sprint 5, 2026-06-05).** The decisions below are now built: `App\Contracts\Llm\LlmClient` (interface) + `App\Services\Llm\OpenRouterClient` (thin `Http` implementation, bound in `AppServiceProvider`), `ModelRoleResolver`, `LlmCallLogger`, and `ConnectionTester`, with role/structured/log/connection-test tests. Two adjustments to the sketch above: **(1)** the key is **not** a single `.env` value — it is the per-owner encrypted `provider_credentials` row (PH-18), read at call time. **(2)** The **`laravel/ai`** SDK named as a candidate swap has been **removed from the project** — it has no official per-request DB-key support ([#105](https://github.com/laravel/ai/issues/105); only unsafe global-config mutation works), which conflicts with the per-owner store. The thin `Http` client is the implemented choice; Prism / an SDK remain a future swap **behind the interface** (PH-21). The `llm_calls` log also gained a nullable `user_id` + `BelongsToOwner` so it is owner-scoped (PH-20).

## Context

[ADR 0011](0011-tech-stack.md) named the **Claude API** as the LLM and the **Laravel AI SDK** as a
*candidate* orchestration layer for the compile→act calls — explicitly leaving the actual client
and the call-routing strategy undecided. Every runtime and authoring subsystem now assumes LLM
calls exist but none specifies **which provider**, **which model per call**, **how structured
output is obtained**, or **what is stored** about a call:

- The narrator turn is **two calls** — a structured prose call + a recorder sub-call ([ADR 0016](0016-narrator-agent-and-turn-loop.md)).
- Each NPC turn is **two calls** — compile + act ([ADR 0007](0007-npc-context-assembly.md)), with **model tiering** (major = strong model, minor = cheap model).
- Appraisal ([ADR 0005](0005-appraisal-trigger-taxonomy.md)), the `BEAT_DONE` judge ([ADR 0015](0015-beat-document-and-boundaries.md)), and nudge-compile ([ADR 0008](0008-psychological-nudge.md)) are each LLM steps.
- The authoring-time compile pipeline ([ADR 0013](0013-authoring-and-compile-pipeline.md)) and the new authoring ADRs ([ADR 0018](0018-character-creation-pipeline.md) / [ADR 0019](0019-outline-compilation.md)) are LLM-assisted.

A single 3-NPC beat is **~10+ calls** ([ADR 0011](0011-tech-stack.md) / [GAPS O4](GAPS.md)), so the provider, the per-call model
choice, and a cost/latency record are load-bearing. The developer wants to use **OpenRouter** as
the provider gateway.

## Decision

### 1. OpenRouter is the provider gateway behind a thin `LlmClient` interface

All LLM traffic goes through **OpenRouter** — an **OpenAI-compatible** Chat Completions endpoint
(`POST {base_url}/chat/completions`, `Authorization: Bearer <key>`). One base URL + one key
(`config('services.openrouter.*')`, key in `.env`) reaches every model by **slug**
(`anthropic/claude-sonnet-4`, `anthropic/claude-3.5-haiku`, …), so the major/minor tiering of
[ADR 0007](0007-npc-context-assembly.md) becomes a slug swap, not a code path.

The application depends on a **provider-agnostic `LlmClient` interface**, not on OpenRouter
directly. OpenRouter is the first (and only, for now) implementation:

```
interface LlmClient
  complete(LlmRequest): LlmResponse           # text / chat
  completeStructured(LlmRequest, schema): LlmResponse   # JSON-schema / tool-call

LlmRequest  { role, model_slug, messages[], params{temperature,max_tokens,…}, schema? }
LlmResponse { text, parsed?, usage{prompt,completion}, cost, latency_ms, model_slug, raw_id }
```

Keeping the interface thin and provider-agnostic leaves **Prism (`prism-php`) or the Laravel AI
SDK** as drop-in replacements later (they ship an OpenRouter provider) without touching the
assembler or any caller. For this stage the thin client (Laravel `Http` facade) is preferred: it
gives full visibility into the **exact bytes sent** — which matters because the whole engine is
about *what reaches the final context* ([ADR 0007](0007-npc-context-assembly.md) / [ADR 0016](0016-narrator-agent-and-turn-loop.md)).

### 2. Model-role tiering — calls are routed by ROLE, not hard-coded slugs

Every LLM call in the engine declares a **role**; the role resolves to a model slug + params. This
generalizes [ADR 0007](0007-npc-context-assembly.md)'s major/minor split to the whole call graph.

| Role | Used by | Default tier |
|------|---------|--------------|
| `narrator_prose` | narrator prose call ([ADR 0016](0016-narrator-agent-and-turn-loop.md) ①) | strong |
| `recorder` | recorder sub-call ([ADR 0010](0010-recorder-mechanics.md) / [ADR 0016](0016-narrator-agent-and-turn-loop.md) ②) | strong |
| `npc_major` | major-NPC compile + act ([ADR 0007](0007-npc-context-assembly.md)) | strong |
| `npc_minor` | minor-NPC compile + act ([ADR 0007](0007-npc-context-assembly.md)) | cheap |
| `compiler` | block fold ([ADR 0007](0007-npc-context-assembly.md)) + card/outline compile ([ADR 0013](0013-authoring-and-compile-pipeline.md) / [0019](0019-outline-compilation.md)) | strong |
| `appraiser` | per-character appraisal ([ADR 0005](0005-appraisal-trigger-taxonomy.md)) | mid |
| `beat_judge` | `BEAT_DONE` goal judge ([ADR 0015](0015-beat-document-and-boundaries.md)) | cheap |
| `nudge_compiler` | beat intent → leak-checked nudge ([ADR 0008](0008-psychological-nudge.md)) | strong |

Resolution order: **per-story override** (`stories.settings.model_roles`) → **global default**
(`model_profiles` rows / seeder). The tier→slug mapping is **shared tunable config** alongside the
severity rubric and elapsed buckets (the [PLACEHOLDER_TRACKING](../guides/PLACEHOLDER_TRACKING.md) PH-8 config home).

### 3. Structured output — JSON-schema first, parse-validated

Calls that must return structured data — narrator prose (`{prose, handoff, elapsed_bucket}`), the
recorder record, appraisal proposals, the beat judge verdict, the nudge-compile output — use
**`completeStructured`** (OpenRouter `response_format: json_schema`, or tool-calling for models that
prefer it). The result is **parse-validated** by the app; a malformed payload is a retryable error,
never trusted. This is the transport mechanism for the structured handoff [ADR 0016](0016-narrator-agent-and-turn-loop.md) §1 requires
(handoff is the prose call's structured output, not a separate classifier).

### 4. What gets stored — `model_profiles` (config) + `llm_calls` (append-only log)

The "it stores something" half of OpenRouter integration:

- **`model_profiles`** — the role→slug+params mapping. Global rows (`story_id` null) are the
  defaults; per-story rows override. UI-manageable later; seeder-backed now. (Schema in
  [DATABASE.md](../architecture/DATABASE.md).)
- **`llm_calls`** — an **append-only** record per call: `role`, `model_slug`, token usage, provider
  `cost`, `latency_ms`, `status`, optional `session_id` / `story_id`, and an optional
  `review_item_id` link when the call produced a reviewable artifact. This is the data behind the
  cost/latency planning [GAPS O4](GAPS.md) flags and the debugging surface for a beat that "feels off."

Cost is stored as the provider reports it (USD micro-units, integer); a Rupiah rendering is a
display concern (the [README](../README.md) currency standard), not a stored second column.

### 5. Caching, retries, isolation

- **Caching** reuses [ADR 0007](0007-npc-context-assembly.md): stable compiled blocks (identity, register) are cached within a
  scene; only volatile blocks recompile. The client is cache-agnostic; callers decide.
- **Retries / timeouts / backoff** live in the client; a structured-parse failure or a 429/5xx is
  retried with backoff up to a bound, then surfaced as a failed `llm_calls` row.
- **Isolation is NOT the client's job.** The `LlmClient` is dumb transport — it sends whatever
  messages it is handed. Context isolation stays where it belongs: the assembler ([ADR 0007](0007-npc-context-assembly.md))
  and the leak guards ([ADR 0008](0008-psychological-nudge.md)/[0009](0009-pov-projection.md)/[0010](0010-recorder-mechanics.md)) decide what may enter a message. **Consequence for
  the log:** `llm_calls` may contain prompts that include a character's `true_state` (the NPC act
  prompt carries that NPC's own SELF block). The log is therefore **as sensitive as the save
  realm** and is single-author–scoped; full message bodies are stored only when debugging is on
  (otherwise a summary + token counts), and the log is never an agent-readable source.

### 6. This supersedes the ADR 0011 "AI SDK candidate" note

[ADR 0011](0011-tech-stack.md)'s LLM row ("Laravel AI SDK is the candidate orchestration layer") is **resolved here**:
OpenRouter gateway + thin `LlmClient` now; the AI SDK / Prism remain a future swap behind the
interface. ADR 0011 is edited in place (still `Proposed`) to cross-reference this ADR.

## Alternatives considered

- **Direct Anthropic API (no gateway).** Rejected per the developer's preference for OpenRouter;
  also loses single-endpoint multi-model routing and the easy model-tier swap.
- **Prism / Laravel AI SDK as the primary client now.** Deferred, not rejected: more ergonomic
  structured-output + provider abstractions, but more dependency and less visibility into the raw
  payload during the prompt-design stage. It drops in behind `LlmClient` later.
- **Hard-coded model slugs at each call site.** Rejected: model choice is tunable per story and per
  tier; routing by role keeps it config, not code.
- **No call log (rely on provider dashboard).** Rejected: the relationship-viewer/debugging story
  and the O4 cost/latency plan need a local, queryable, review-linkable record.
- **Decode structured output from free text.** Rejected: `response_format`/tool-calling +
  parse-validation is model-independent and matches the "does not trust the model" posture of the
  leak guards.

## Consequences

- A provider-agnostic **`LlmClient`** interface + an **OpenRouter implementation** become the single
  chokepoint for every LLM call; callers pass a **role**, not a slug.
- **Two new tables** — `model_profiles` (config) and `llm_calls` (append-only log) — land in
  [DATABASE.md](../architecture/DATABASE.md); `stories.settings.model_roles` carries per-story overrides.
- **Cost/latency (O4) is partially closed** at the design level: the log is specified; the caching
  and queue/batch orchestration remain implementation (the future orchestration ADR).
- The **tier→slug mapping** joins the shared tunable-config home ([PH-8](../guides/PLACEHOLDER_TRACKING.md)).
- [ADR 0011](0011-tech-stack.md) is cross-referenced; the [README](../README.md) tech-stack snapshot updates the LLM line to
  "OpenRouter gateway (ADR 0017)."
- **Isolation is unchanged** — the client is transport only; the `llm_calls` log is flagged as
  save-realm-sensitive and never agent-readable.
