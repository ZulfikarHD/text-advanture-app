# Phase 1: Foundation, Auth & App Shell — Directed Interactive Novel Engine
## Directed Interactive Novel Engine (DINE)

**Timeline:** ~1.5 Months (6 Sprints)
**Sprint Duration:** 1 Week
**Team Size Recommendation:** 1 Full-stack Dev (+ optional QA)
**Depends on:** nothing (greenfield start)
**Governing ADRs:** 0011 (tech stack), 0012 (persistence), 0017 (LLM/OpenRouter), 0020 (prompt blocks)

> Goal: stand up a running, themed, authenticated application with the complete two-realm database schema, the OpenRouter LLM client, API-key/provider management, the seeded global libraries, and the foundation of the shared review gate. After this phase the product can be logged into and navigated — it just has no stories yet.

---

## Product Backlog Overview

### Epic Summary

| Epic ID | Epic Name | Priority | Story Points | Sprints |
|---------|-----------|----------|--------------|---------|
| E1 | Project Scaffold & Tooling | Critical | 16 | 1 |
| E2 | Authentication & User Management | Critical | 21 | 1–2 |
| E3 | App Shell & UI/UX Foundation | High | 18 | 2–3 |
| E4 | Persistence Schema (Two Realms) | Critical | 21 | 3–4 |
| E5 | LLM Provider & API-Key Management | Critical | 21 | 4–5 |
| E6 | Global Libraries & Review-Gate Foundation | High | 18 | 5–6 |

**Total Estimated:** ~115 Story Points

---

## EPIC E1: Project Scaffold & Tooling

### E1.1 — Application Skeleton

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-1.1.1 | As an **author**, I want the application scaffolded on the locked stack so that I have a consistent, opinionated foundation to build the engine on | 5 | Critical | 1 |
| S-1.1.2 | As a **system**, I want the database connection, timezone, and currency configured to project standards so that all data and displays are consistent | 2 | Critical | 1 |
| S-1.1.3 | As an **author**, I want typed routing and linting wired up so that route references and code style are verified at build time | 3 | High | 1 |

**Acceptance Criteria - S-1.1.1:**
```gherkin
Feature: Application Skeleton

Scenario: A fresh checkout boots a running app
  Given a clean clone of the repository
  When I install dependencies with the project package manager and run the dev server
  Then the application boots without errors
  And it serves a server-driven frontend using the locked frontend framework
  And the default styling system and component kit are available to build with

Scenario: Stack matches the locked technical decisions
  Given the scaffolded application
  Then the backend, frontend, routing, styling, build tool, and package manager
       match the locked technical context defined for the project
  And no rejected alternative (e.g. the legacy route helper) is used for routing
```

**Acceptance Criteria - S-1.1.2:**
```gherkin
Feature: Project Standards Configuration

Scenario: Times and money render to project standards
  Given the application is configured
  Then stored timestamps are kept in UTC
  And times shown to the user render in Asia/Jakarta (WIB)
  And any provider monetary cost is rendered for display in Rupiah (Rp)

Scenario: Database connection is the project engine
  Given the application is configured
  When the app connects to its database
  Then it uses a MySQL-8-compatible engine with JSON column support
  And a connection failure surfaces a clear, actionable error rather than a blank screen
```

**Acceptance Criteria - S-1.1.3:**
```gherkin
Feature: Typed Routing and Linting

Scenario: Lint passes on a clean tree
  When I run the project lint task
  Then it completes with no errors

Scenario: A removed/disabled route reference fails the build, not production
  Given a frontend reference to a route that no longer exists
  When the typed-route generation runs at build time
  Then the build fails with a clear message identifying the missing route
```

> **Technical Notes E1.1:**
> - **Business Logic:**
>   - The app is a single integrated server-driven application (no separate standalone API/SPA split).
>   - Routing must be **typed** and generated at build time; the legacy string-helper route approach is forbidden by project rule.
>   - Standards: store time in UTC, render in Asia/Jakarta; render provider cost in Rupiah; lint must be runnable as a single task and required to be clean for "done".
> - **Reference:** ADR 0011 (tech stack), README tech-stack snapshot.

---

## EPIC E2: Authentication & User Management

### E2.1 — Sign In / Sign Out

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.1.1 | As a **user**, I want to sign in with my credentials so that only I can access my authoring workspace and saves | 5 | Critical | 1 |
| S-2.1.2 | As a **user**, I want to sign out so that my session is ended on a shared device | 1 | Critical | 1 |
| S-2.1.3 | As a **user**, I want to optionally use a passkey so that I can authenticate without a password | 3 | Medium | 2 |
| S-2.1.4 | As a **user**, I want protected pages to require authentication so that unauthenticated visitors cannot reach my content | 2 | Critical | 1 |

**Acceptance Criteria - S-2.1.1:**
```gherkin
Feature: Sign In

Scenario: Successful sign in with valid credentials
  Given I am a registered user
  When I authenticate with valid credentials
  Then I am granted an authenticated session
  And I am taken to my workspace home

Scenario: Sign in fails with invalid credentials
  Given I am on the sign-in surface
  When I authenticate with an incorrect password
  Then I am not signed in
  And I am told the credentials are invalid without revealing which field was wrong

Scenario: Brute-force protection
  Given repeated failed sign-in attempts for the same account
  When the failure threshold is exceeded
  Then further attempts are throttled for a cool-down period
```

**Acceptance Criteria - S-2.1.2:**
```gherkin
Feature: Sign Out

Scenario: User signs out
  Given I am signed in
  When I sign out
  Then my authenticated session is invalidated
  And visiting a protected page sends me back to sign in
```

**Acceptance Criteria - S-2.1.3:**
```gherkin
Feature: Passkey Authentication

Scenario: Register and use a passkey
  Given I am signed in
  When I register a passkey for my account
  Then I can subsequently sign in using that passkey without a password

Scenario: Passkey is optional
  Given I have not registered a passkey
  Then I can still sign in with my password
```

**Acceptance Criteria - S-2.1.4:**
```gherkin
Feature: Route Protection

Scenario: Unauthenticated access is blocked
  Given I am not signed in
  When I attempt to open any authoring or play page
  Then I am redirected to sign in
  And after signing in I am returned to my intended destination
```

> **Technical Notes E2.1:**
> - **Business Logic:**
>   - Authentication is required for all authoring and play surfaces; only auth surfaces are public.
>   - Repeated failed sign-ins must be rate-limited (throttle + cool-down).
>   - Passkey is an optional, additive credential; password remains a valid path.
>   - Error messaging must not disclose whether the username or the password was the incorrect field.
> - **Reference:** ADR 0011 (Fortify via the Vue starter kit), GAPS "Auth / multi-user scope".

### E2.2 — Account, Users & Ownership

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-2.2.1 | As a **user**, I want to manage my account profile and password so that I keep my access current and secure | 3 | High | 2 |
| S-2.2.2 | As a **system**, I want all authoring and save data scoped to its owner so that one user can never read or modify another user's content | 5 | Critical | 2 |
| S-2.2.3 | As a **user**, I want optional self-registration (configurable) so that the deployment can be single-author or open to invited users | 2 | Medium | 2 |

**Acceptance Criteria - S-2.2.1:**
```gherkin
Feature: Account Management

Scenario: Update profile details
  Given I am signed in
  When I update my display name or email and save
  Then my account reflects the new details
  And invalid input (e.g. malformed email) is rejected with a clear message

Scenario: Change password
  Given I am signed in
  When I change my password with the correct current password and a valid new password
  Then my password is updated
  And an incorrect current password is rejected
```

**Acceptance Criteria - S-2.2.2:**
```gherkin
Feature: Account-Scoped Data Isolation

Scenario: Owner sees only their own content
  Given two users each own separate stories and saves
  When user A lists or opens content
  Then user A sees only content they own
  And user A cannot open, edit, or delete content owned by user B even with a direct reference

Scenario: Cross-owner access is denied
  Given a resource owned by user B
  When user A requests it directly
  Then access is denied (not found / forbidden) and nothing about B's data is leaked
```

**Acceptance Criteria - S-2.2.3:**
```gherkin
Feature: Configurable Registration

Scenario: Registration open
  Given self-registration is enabled for the deployment
  When a new visitor registers with valid details
  Then a new account is created and they can sign in

Scenario: Registration closed (single-author)
  Given self-registration is disabled
  When a visitor attempts to register
  Then registration is not available and existing users can still sign in
```

> **Technical Notes E2.2:**
> - **Business Logic:**
>   - Every owned resource (stories, characters, lorebook, saves, settings, API keys) carries an owner; all reads/writes are filtered by the current owner.
>   - Cross-owner access must fail closed (deny) and must not leak existence or content.
>   - Self-registration is a deployment toggle (single-author default; invited multi-user optional).
>   - Account isolation is *not* role-based access control — there is no admin/operator hierarchy.
> - **Reference:** ADR 0012 (save realm scoping), program NFR "Security".

---

## EPIC E3: App Shell & UI/UX Foundation

### E3.1 — Navigation, Layout & Theming

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.1.1 | As a **user**, I want a consistent app shell with primary navigation so that I can move between authoring, playing, and settings | 3 | High | 2 |
| S-3.1.2 | As a **user**, I want light and dark themes so that I can read comfortably in different conditions | 3 | Medium | 3 |
| S-3.1.3 | As a **user**, I want the app to be responsive and keyboard-accessible so that I can use it on different screens and without a mouse | 3 | High | 3 |

**Acceptance Criteria - S-3.1.1:**
```gherkin
Feature: App Shell & Navigation

Scenario: Authenticated shell with primary navigation
  Given I am signed in
  Then I see a persistent application shell with primary navigation to my authoring workspace, play surface, and settings
  And the currently active area is indicated

Scenario: Navigation reflects what exists
  Given I have no stories yet
  When I open the workspace
  Then I am guided toward creating my first story rather than shown an empty, unexplained screen
```

**Acceptance Criteria - S-3.1.2:**
```gherkin
Feature: Theming

Scenario: Switch theme
  Given I am signed in
  When I switch between light and dark themes
  Then the entire interface updates accordingly
  And my preference persists across sessions
  And text remains legible with sufficient contrast in both themes
```

**Acceptance Criteria - S-3.1.3:**
```gherkin
Feature: Responsive & Accessible Shell

Scenario: Usable on smaller screens
  Given I open the app on a tablet-width screen
  Then the layout adapts and all primary actions remain reachable

Scenario: Keyboard navigation
  Given I am using only a keyboard
  When I navigate interactive controls
  Then focus order is logical and the focused control is clearly indicated
```

### E3.2 — Global Interaction States

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-3.2.1 | As a **user**, I want consistent loading, empty, and error states so that I always understand what the app is doing | 3 | High | 3 |
| S-3.2.2 | As a **user**, I want consistent confirmation before destructive actions so that I don't lose work by accident | 3 | High | 3 |

**Acceptance Criteria - S-3.2.1:**
```gherkin
Feature: Global Interaction States

Scenario: Long operation shows progress
  Given an action that takes noticeable time
  When it is running
  Then an indicator that work is in progress is shown
  And the indicator does not flicker for near-instant operations

Scenario: Errors are explained
  Given an operation fails
  Then I receive a clear message describing what went wrong
  And, where possible, how to recover

Scenario: Empty states guide the next step
  Given a list with no items yet
  Then I see an explanation and a clear path to create the first item
```

**Acceptance Criteria - S-3.2.2:**
```gherkin
Feature: Destructive Action Confirmation

Scenario: Confirm before deletion
  Given I trigger a destructive action (e.g. delete)
  Then I am asked to confirm before it proceeds
  And I have a clear way to cancel with no change made
```

> **Technical Notes E3.1/E3.2:**
> - **Business Logic:**
>   - The authenticated shell exposes three top-level areas: authoring workspace, play, settings.
>   - Theme preference is per-user and persisted; both themes must meet contrast guidance.
>   - Every async surface defines loading / empty / error / success states; destructive actions always reversible-by-confirmation (ask first).
> - **Reference:** program NFR "Accessibility", "UI/UX note" in the program README.

---

## EPIC E4: Persistence Schema (Two Realms)

### E4.1 — Authoring Realm (Immutable at Runtime)

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.1.1 | As a **system**, I want the authoring-realm tables created so that template data (stories, characters, cards, registers, sensitivities, chapters, scenes, beats, outlines, libraries) has a home that is immutable at runtime | 8 | Critical | 3 |
| S-4.1.2 | As a **system**, I want the global shared-library tables created so that app-wide grammar/priors/archetypes/blocks/model-profiles are not duplicated per story | 3 | Critical | 4 |

**Acceptance Criteria - S-4.1.1:**
```gherkin
Feature: Authoring Realm Schema

Scenario: Authoring tables exist with correct shape
  Given the migrations have run
  Then the authoring realm provides tables for: stories, characters, character cards (per character per chapter),
       reveal ledger, registers, sensitivities, lorebook entries, chapters, scenes, beats, and chapter outlines
  And each table enforces its documented keys, uniqueness, and foreign keys

Scenario: Authoring data is template, not per-save
  Given an authoring row (e.g. a character card)
  Then it belongs to a story (or is global) and carries no per-playthrough state
```

**Acceptance Criteria - S-4.1.2:**
```gherkin
Feature: Global Shared Libraries Schema

Scenario: Global libraries are story-independent
  Given the migrations have run
  Then global tables exist for register archetypes, universal priors, character archetypes,
       prompt blocks, and model profiles
  And these tables carry no story reference (they are app-wide)
```

> **Technical Notes E4.1:**
> - **Business Logic:**
>   - Two realms (ADR 0012): authoring (immutable at runtime) and save (mutable per playthrough). This story builds the **authoring** realm + global libraries.
>   - A character card is keyed **per (character, chapter)** — epistemic state advances per chapter.
>   - Global libraries (register archetypes, universal priors, character archetypes, prompt blocks, model profiles) carry no story scope.
>   - Source bibles are **not** stored as engine rows — referenced by path and never injected.
> - **Reference:** ADR 0012 §1–§3, `docs/architecture/DATABASE.md` §3.

### E4.2 — Save Realm (Mutable, Per-Session) & Structural Isolation

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-4.2.1 | As a **system**, I want the save-realm tables created so that an evolving playthrough (edges, deltas, internal state, beat records, nudges, summaries, events) is stored separately from the template | 8 | Critical | 4 |
| S-4.2.2 | As a **system**, I want isolation and audit invariants enforced at the data layer so that private state cannot be read across characters and history cannot be silently rewritten | 5 | Critical | 4 |

**Acceptance Criteria - S-4.2.1:**
```gherkin
Feature: Save Realm Schema

Scenario: Save tables exist and are session-scoped
  Given the migrations have run
  Then the save realm provides tables for: sessions, relationship edges, edge axes, axis deltas,
       internal states, active emotions, acquired sensitivities, beat records, beat true-states,
       beat witnesses, nudges, review items, scene summaries, chapter logs, events, and the LLM call log
  And every save row is scoped to a session
```

**Acceptance Criteria - S-4.2.2:**
```gherkin
Feature: Structural Isolation & Audit Invariants

Scenario: Private true-state cannot be pulled with the public surface
  Given a beat record with a public surface layer and per-character private true-state
  Then the private true-state lives in a separate table from the public surface
  And a query that reads only the public surface cannot return any character's private true-state

Scenario: Audit tables are append-only
  Given an append-only table (axis deltas, beat records and children, nudges, LLM calls)
  Then rows carry only a creation timestamp
  And updates/deletes are not permitted; corrections are new rows
```

> **Technical Notes E4.2:**
> - **Business Logic:**
>   - The **save realm** is mutable and FK-scoped to a session; a session is a *fork* of the template.
>   - `beat_true_states` is a **separate child table** of `beat_records` so a "read surface only" query physically cannot pull another character's private state (structural isolation).
>   - Append-only tables (`axis_deltas`, `beat_records` + children, `nudges`, `llm_calls`) carry only `created_at`; never updated/deleted — corrections are new rows via the review gate.
>   - Materialized current values (`edge_axes`, `internal_states`) for fast reads; logs are history (not event-sourced).
> - **Reference:** ADR 0012 §4, `docs/architecture/DATABASE.md` §4–§5.

---

## EPIC E5: LLM Provider & API-Key Management

### E5.1 — Provider Connection & API-Key Management

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.1.1 | As a **user**, I want to securely store and manage my OpenRouter API key so that the engine can make model calls on my behalf | 5 | Critical | 4 |
| S-5.1.2 | As a **user**, I want to test the provider connection so that I know my key works before I rely on it during play | 3 | High | 5 |
| S-5.1.3 | As a **system**, I want the API key encrypted at rest and never exposed so that a leak of stored data or logs does not reveal it | 3 | Critical | 4 |

**Acceptance Criteria - S-5.1.1:**
```gherkin
Feature: API Key Management

Scenario: Save an API key
  Given I am signed in and on the provider settings
  When I enter and save my OpenRouter API key
  Then the key is stored for my account
  And after saving, the key is shown only masked (not the full value)

Scenario: Replace or remove the key
  Given I have a stored key
  When I replace it with a new value or remove it
  Then the engine uses the new value going forward, or has no key if removed
```

**Acceptance Criteria - S-5.1.2:**
```gherkin
Feature: Provider Connection Test

Scenario: Connection test with a valid key
  Given I have stored a valid API key
  When I run the connection test
  Then I am told the connection succeeded
  And, where available, the set of reachable models is indicated

Scenario: Connection test with an invalid key
  Given I have stored an invalid key
  When I run the connection test
  Then I am told the connection failed with the provider's reason
  And no engine feature silently fails later because of it
```

**Acceptance Criteria - S-5.1.3:**
```gherkin
Feature: API Key Security

Scenario: Key is never echoed or logged
  Given a stored API key
  Then it is encrypted at rest
  And it is never returned in plaintext to the client after saving
  And it never appears in the LLM call log or application logs
```

> **Technical Notes E5.1:**
> - **Business Logic:**
>   - One OpenRouter base URL + one key per owner reaches every model by slug.
>   - The key is encrypted at rest, masked after save, never returned in plaintext, never logged.
>   - A connection test validates the key against the provider and reports a clear pass/fail with reason.
> - **Reference:** ADR 0017 §1, program NFR "Security".

### E5.2 — `LlmClient`, Role Tiering & Structured Output

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.2.1 | As a **system**, I want a provider-agnostic LLM client (text + structured) so that all model traffic flows through one swappable chokepoint | 5 | Critical | 5 |
| S-5.2.2 | As a **user**, I want to map engine roles to models and parameters so that I can tier strong/cheap models without code changes | 5 | High | 5 |
| S-5.2.3 | As a **system**, I want structured outputs validated and malformed responses retried so that the engine never trusts an unparseable result | 3 | Critical | 5 |

**Acceptance Criteria - S-5.2.1:**
```gherkin
Feature: Provider-Agnostic LLM Client

Scenario: All calls route through one client
  Given any engine feature that needs a model
  When it makes a call
  Then it calls through the single LLM client interface (text or structured)
  And the caller passes a role, not a hard-coded model slug

Scenario: Provider is swappable
  Given the client interface
  Then the OpenRouter implementation is the active one
  And replacing the implementation requires no change to any caller
```

**Acceptance Criteria - S-5.2.2:**
```gherkin
Feature: Model-Role Tiering

Scenario: Roles resolve to models via configuration
  Given the engine roles (e.g. narrator prose, recorder, major NPC, minor NPC, compiler, appraiser, beat judge, nudge compiler)
  When a call is made for a role
  Then the model slug and parameters are resolved from configuration
  And a per-story override takes precedence over the global default

Scenario: Author edits a role mapping
  Given I am on model-profile settings
  When I change the model or parameters for a role
  Then subsequent calls for that role use the new mapping
```

**Acceptance Criteria - S-5.2.3:**
```gherkin
Feature: Structured Output Validation

Scenario: Valid structured output is parsed
  Given a call that requests a structured result against a schema
  When the provider returns a conforming payload
  Then it is parsed and returned to the caller

Scenario: Malformed structured output is retried, then surfaced
  Given a call that requests a structured result
  When the provider returns an unparseable or non-conforming payload
  Then the client retries with backoff up to a bound
  And if still failing, the call is recorded as failed and the caller is informed — the bad output is never trusted
```

> **Technical Notes E5.2:**
> - **Business Logic:**
>   - `LlmClient` exposes `complete` (text/chat) and `completeStructured` (schema/tool-call); callers pass a **role**.
>   - Role → model slug + params resolution order: per-story override → global default.
>   - Structured calls are parse-validated; malformed = retryable error (bounded backoff), then a failed record; never trusted.
>   - The client is dumb transport — it never decides isolation; what enters a message is decided upstream.
> - **Reference:** ADR 0017 §1–§3, §5.

### E5.3 — Call Log & Cost/Latency Record

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-5.3.1 | As a **user**, I want every model call logged with usage, cost, and latency so that I can monitor spend and debug a call that misbehaves | 3 | High | 5 |
| S-5.3.2 | As a **system**, I want the call log treated as save-realm-sensitive and never agent-readable so that a logged prompt cannot leak into a narrative agent | 2 | Critical | 5 |

**Acceptance Criteria - S-5.3.1:**
```gherkin
Feature: LLM Call Log

Scenario: A call is recorded
  Given any model call completes (success or failure)
  Then an append-only log entry records the role, resolved model, token usage, provider cost, latency, and status
  And it optionally links to the session/story and to any reviewable artifact it produced

Scenario: Cost is rendered in the project currency
  Given logged calls with provider cost
  When I view cost
  Then it is rendered for display in Rupiah while stored as the provider-reported value
```

**Acceptance Criteria - S-5.3.2:**
```gherkin
Feature: Call Log Sensitivity

Scenario: Full request bodies are gated and never agent-readable
  Given a call whose prompt may embed a character's private true-state
  Then full message bodies are stored only when debugging is enabled (otherwise a summary + token counts)
  And no narrative agent ever reads the call log as a source
```

> **Technical Notes E5.3:**
> - **Business Logic:**
>   - Append-only call log: role, model slug, prompt/completion tokens, provider cost (USD micro-units), latency, status, optional session/story + review-item link.
>   - Cost stored as provider-reported; Rupiah is a display rendering only.
>   - Full message bodies are debug-gated; the log is save-realm-sensitive and never an agent-readable source.
> - **Reference:** ADR 0017 §4–§5, `docs/architecture/DATABASE.md` §4.16.

---

## EPIC E6: Global Libraries & Review-Gate Foundation

### E6.1 — Seed the Global Libraries

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-6.1.1 | As a **system**, I want the universal priors and register-archetype libraries seeded so that appraisal and register authoring start from a shared baseline | 5 | High | 5 |
| S-6.1.2 | As a **system**, I want the character-archetype library seeded so that character creation can start from common shapes | 3 | Medium | 6 |
| S-6.1.3 | As a **system**, I want the prompt-block registry seeded with the ~15 engine blocks so that prompt assembly is data-driven from day one | 5 | High | 6 |
| S-6.1.4 | As a **system**, I want default model profiles seeded so that the engine has a working role→model mapping out of the box | 2 | High | 6 |

**Acceptance Criteria - S-6.1.1:**
```gherkin
Feature: Seed Shared Appraisal & Register Libraries

Scenario: Universal priors are available
  Given the seeders have run
  Then the universal-priors library contains the baseline human reactions (e.g. insult, kindness, threat, broken promise)
       each with its affected axes, default weight, and channel

Scenario: Register archetypes are available
  Given the seeders have run
  Then the register-archetype library contains reusable grammar skeletons (e.g. one-way-mirror, romantic-deflection, unguarded, wary)
       each defined over the fixed canonical dimension set
```

**Acceptance Criteria - S-6.1.2:**
```gherkin
Feature: Seed Character Archetypes

Scenario: Character archetypes seed a whole character shape
  Given the seeders have run
  Then the character-archetype library contains at least one seedable shape (e.g. koakuma)
       carrying base opacity, suggested live axes, default disposition priors, default registers, default sensitivities, and a voice scaffold
  And selecting one is a starting point, never a constraint (every field stays editable later)
```

**Acceptance Criteria - S-6.1.3:**
```gherkin
Feature: Seed Prompt Block Registry

Scenario: The engine blocks are registered with their leak rules
  Given the seeders have run
  Then the prompt-block registry contains the engine's blocks for both narrator and NPC prompts
  And each block declares its agent, section, label, purpose, source producers, fold instruction, order, and leak rules
  And the leak rules name existing guards only (awareness fold, knowledge boundary, hedged attribution, own-perspective-only, omniscient authoring, or none) — no new guard is invented
```

**Acceptance Criteria - S-6.1.4:**
```gherkin
Feature: Seed Model Profiles

Scenario: Default role→model mapping exists
  Given the seeders have run
  Then a global model profile exists for every engine role with a default model slug and parameters
  And the engine can resolve a model for any role without manual setup
```

> **Technical Notes E6.1:**
> - **Business Logic:**
>   - Seed the global, story-independent libraries: universal priors, register archetypes, character archetypes, prompt blocks (~15), model profiles.
>   - Register dimensions are a **fixed, versioned** set: disclosure, proximity, flow, deflection, sincerity, composure, reads_target, tells, speech.
>   - Prompt-block `leak_rules` map to existing guards only; the registry *names which guard applies where*, it does not add guards.
> - **Reference:** ADR 0005 (priors), 0006 (register dimensions), 0018 (character archetypes), 0020 (prompt blocks), 0017 (model profiles).

### E6.2 — Shared Review-Gate Foundation

| ID | User Story | Story Points | Priority | Sprint |
|----|------------|--------------|----------|--------|
| S-6.2.1 | As a **system**, I want one shared review queue (propose → review → commit) so that every producer (deltas, emotions, nudge compile, beat records, card/outline/bible compiles) uses the same gate | 5 | High | 6 |
| S-6.2.2 | As an **author**, I want to accept, edit, or reject a pending proposal so that I remain the fidelity floor for everything the engine generates | 3 | High | 6 |

**Acceptance Criteria - S-6.2.1:**
```gherkin
Feature: Shared Review Queue Foundation

Scenario: A producer enqueues a proposal
  Given any engine producer creates a proposal
  Then a review item is created with its producer type, a reference to the proposed content, the payload, and a pending status
  And authoring-time compiles may enqueue without a session (no save context required)
```

**Acceptance Criteria - S-6.2.2:**
```gherkin
Feature: Review Decision

Scenario: Accept a proposal
  Given a pending review item
  When I accept it
  Then its content is committed and the item is marked accepted with who/when

Scenario: Edit then commit
  Given a pending review item
  When I edit the payload and commit
  Then the edited content is committed and the item is marked edited

Scenario: Reject a proposal
  Given a pending review item
  When I reject it
  Then nothing is committed and the item is marked rejected
```

> **Technical Notes E6.2:**
> - **Business Logic:**
>   - One queue, polymorphic by `producer_type`: delta, emotion delta, nudge compile, beat record, card compile, bible generate, outline compile.
>   - States: pending → accepted | edited | rejected; edits captured separately from the original payload; reviewer + timestamp recorded.
>   - Authoring-time compiles enqueue with **no** session (null session context) — a deliberate authoring-realm row in a save-realm table.
>   - This is the **foundation**; the full review-gate UX (batching, inline diff, per-producer rendering) lands in Phase 7 E4.
> - **Reference:** ADR 0003 (gate), 0012 §5, 0013/0018/0019 (producers), `docs/architecture/DATABASE.md` §4.12.

---

## Sprint Roadmap

### Sprint 1: Scaffold & Sign-in (E1 + E2.1)
```
Sprint 1 (Week 1):
├── S-1.1.1: Application skeleton on the locked stack
├── S-1.1.2: Project standards (timezone, currency, DB)
├── S-1.1.3: Typed routing + lint
├── S-2.1.1: Sign in
├── S-2.1.2: Sign out
├── S-2.1.4: Route protection
└── Smoke test: boot, lint, sign-in flow
```

### Sprint 2: Users, Ownership & Shell (E2.2 + E2.1.3 + E3.1 start)
```
Sprint 2 (Week 2):
├── S-2.2.1: Account & password management
├── S-2.2.2: Account-scoped data isolation
├── S-2.2.3: Configurable registration
├── S-2.1.3: Optional passkey
├── S-3.1.1: App shell & navigation
└── Test: cross-owner access denial
```

### Sprint 3: UX Foundation & Authoring Schema (E3 + E4.1)
```
Sprint 3 (Week 3):
├── S-3.1.2: Light/dark theming
├── S-3.1.3: Responsive & accessible shell
├── S-3.2.1: Loading/empty/error states
├── S-3.2.2: Destructive-action confirmation
├── S-4.1.1: Authoring realm schema
└── Migration review
```

### Sprint 4: Schema & Provider (E4 + E5.1)
```
Sprint 4 (Week 4):
├── S-4.1.2: Global shared-library tables
├── S-4.2.1: Save realm schema
├── S-4.2.2: Structural isolation & audit invariants
├── S-5.1.1: API key management
├── S-5.1.3: API key security
└── Test: surface-only query cannot pull true-state
```

### Sprint 5: LLM Client & Logging (E5)
```
Sprint 5 (Week 5):
├── S-5.1.2: Connection test
├── S-5.2.1: Provider-agnostic LLM client
├── S-5.2.2: Model-role tiering
├── S-5.2.3: Structured output validation + retry
├── S-5.3.1: Call log (cost/latency)
├── S-5.3.2: Call-log sensitivity
└── Test: malformed structured output never trusted
```

### Sprint 6: Libraries & Review Gate (E6)
```
Sprint 6 (Week 6):
├── S-6.1.1: Seed universal priors + register archetypes
├── S-6.1.2: Seed character archetypes
├── S-6.1.3: Seed prompt-block registry
├── S-6.1.4: Seed model profiles
├── S-6.2.1: Shared review-queue foundation
├── S-6.2.2: Review decision (accept/edit/reject)
└── Phase 1 regression + hardening
```

---

## Definition of Done (DoD)

See the [program DoD](./README.md#7-global-definition-of-done-dod). Phase-1 emphasis:

- [ ] App boots from a clean clone; `pnpm lint` clean; typed routes regenerate.
- [ ] Auth flows (sign in/out, protection, throttle) tested; cross-owner isolation has explicit negative tests.
- [ ] Both realms migrate and roll back cleanly; structural-isolation and append-only invariants tested.
- [ ] API key encrypted, masked, never logged; connection test pass/fail verified.
- [ ] LLM client routes by role; malformed structured output retried then surfaced; every call logged.
- [ ] Global libraries seeded idempotently; review-gate foundation accept/edit/reject works.

---

## Success Metrics — Phase 1

| Metric | Target | Measurement |
|--------|--------|-------------|
| Clean-clone boot success | 100% | Fresh setup boots without manual fixes |
| Auth isolation | 0 cross-owner reads | Negative tests pass; no data bleed |
| Migration reversibility | 100% | Up/down migrations succeed on both realms |
| Structured-output safety | 0 trusted malformed payloads | Validation + retry tests pass |
| API-key leakage | 0 occurrences | Key absent from all logs and client responses |
| Seed idempotency | 100% | Re-running seeders produces no duplicates |

---

## Risk Register — Phase 1

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| API key leaks via logs or client responses | Critical | Low | Encrypt at rest, mask after save, exclude from all logs; security test |
| Cross-owner data exposure | Critical | Medium | Owner-scoped queries by default; explicit deny + negative tests |
| Structural isolation not actually enforced by schema | Critical | Medium | Separate true-state table; test a surface-only read cannot reach it |
| Provider/key misconfiguration discovered only at play time | High | Medium | Connection test + clear failure surfacing before reliance |
| Starter-kit route references break the build when a feature is disabled | Medium | Medium | Remove disabled route references; typed-route generation in CI |
| Seeders create duplicates on re-run | Medium | Low | Idempotent seeders keyed by slug/role |

---

*Document Version: 1.0 · Author: Zulfikar Hidayatullah · Created: June 2026*
