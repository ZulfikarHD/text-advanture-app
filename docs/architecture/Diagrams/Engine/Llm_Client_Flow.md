# LLM Client Flow

How an engine call flows through the role resolver, the thin OpenRouter client (with retry/backoff + structured validation), and the append-only logger — the implementation of [ADR 0017](../../../adr/0017-llm-orchestration-openrouter.md) built in Sprint 5. The client is **dumb transport**: context isolation stays upstream in the assembler and leak guards; the client sends whatever messages it is handed and authenticates with the **owner's** key.

```mermaid
sequenceDiagram
  autonumber
  participant Caller as "Caller (ConnectionTester / future engine)"
  participant Resolver as ModelRoleResolver
  participant Profiles as "model_profiles"
  participant Client as "OpenRouterClient (LlmClient)"
  participant Creds as "provider_credentials"
  participant OR as OpenRouter
  participant Logger as LlmCallLogger
  participant Log as "llm_calls (append-only)"

  Caller->>Resolver: resolve(role, story?)
  Resolver->>Profiles: per-story override then global default
  Profiles-->>Resolver: ModelProfile (slug + params)
  Resolver-->>Caller: ModelProfile (or UnresolvedModelRoleException)

  Caller->>Client: complete / completeStructured(LlmRequest{role, slug, messages, owner})
  Client->>Creds: owner's encrypted key
  Creds-->>Client: decrypted Bearer key (never logged)

  loop up to max_retries + 1
    Client->>OR: POST /chat/completions (Bearer key)
    alt 429 / 5xx / connection error
      OR-->>Client: transient failure
      Client->>Client: backoff (exponential)
    else 2xx
      OR-->>Client: choices + usage{tokens, cost}
      opt structured call
        Client->>Client: parse + validate against schema (required keys, types, enums)
        Note over Client: malformed (bad JSON / missing field / wrong type / out-of-enum) -> retry within bound
      end
    end
  end

  alt success
    Client->>Logger: record(Ok, tokens, cost_micros_usd, latency)
    Logger->>Log: append owner-scoped row (messages debug-gated)
    Client-->>Caller: LlmResponse{text|parsed, usage, cost, latency}
  else retries exhausted / non-retryable
    Client->>Logger: record(Failed, reason)
    Logger->>Log: append Failed row
    Client-->>Caller: throw LlmCallFailedException / LlmStructuredOutputException
  end
```

> **Structured validation (S-4.2.2).** A structured call validates the parsed payload against the schema's `required` keys, property `type`s, **and any declared `enum`** — so an out-of-vocabulary value (e.g. a `handoff` the loop can't route this phase) is treated as malformed, not trusted. Malformed/non-conforming output is retried with backoff up to `max_retries`, then recorded as a `Failed` row and surfaced as `LlmStructuredOutputException`; the engine never receives unvalidated data as if it were valid. The first consumer is the [narrator prose call](./Narrator_Turn_Prose_Call.md).
>
> The **connection test** (S-5.1.2) is a sibling of this flow but a key-validation probe, not a role call: it hits `GET /models` with the owner's key and **does not** write `llm_calls`.
>
> Cost is captured as the provider-reported USD value (`usage.cost`) and stored as integer USD micro-units (`cost_micros_usd`); the Usage surface renders it in USD (PH-12). The `llm_calls` log is **save-realm-sensitive** — full message bodies persist only behind the `services.openrouter.log_messages` gate and are `#[Hidden]`.
