# Account Ownership & Isolation

Fail-closed, owner-scoped-by-default access for owned resources (Sprint 2 / S-2.2.2). "Multi-user" here means **account isolation only** — there is no role or admin hierarchy. Source of truth: `app/Models/Concerns/BelongsToOwner.php`, `app/Models/Scopes/OwnerScope.php`, `app/Policies/OwnerPolicy.php`.

## Read / mutate path for an owned resource

```mermaid
flowchart TD
    Req["Request for an owned resource (auth middleware already passed)"] --> Scope{"OwnerScope applied? (Auth::check())"}
    Scope -->|"no auth (console/seed/job)"| Unscoped["Query runs unscoped"]
    Scope -->|"authenticated"| Filter["Constrain: table.user_id = Auth::id()"]
    Filter --> Bind{"Row matches current owner?"}
    Bind -->|"no match"| NotFound["Route-model binding fails to 404 (existence not leaked)"]
    Bind -->|"match"| Policy{"OwnerPolicy: user.id === model.user_id?"}
    Policy -->|"deny"| Forbidden["403 Forbidden"]
    Policy -->|"allow"| Ok["Render / mutate"]
```

## Ownership stamping on create

```mermaid
flowchart TD
    New["Model::create(attrs)"] --> Creating["creating event (BelongsToOwner)"]
    Creating --> Has{"user_id already set?"}
    Has -->|"yes"| Keep["Keep provided owner"]
    Has -->|"no"| AuthSet{"Auth::check()?"}
    AuthSet -->|"yes"| Stamp["user_id = Auth::id()"]
    AuthSet -->|"no"| Skip["No stamp (caller must set it)"]
    Keep --> Save["Persist"]
    Stamp --> Save
    Skip --> Save
```

## Notes

- The global scope is the **HTTP isolation boundary**: web owned-resources always sit behind `auth`, so every web query is scoped. The deliberate no-op when unauthenticated keeps console commands, seeders, and queued jobs able to operate across owners.
- `404` (scope) vs `403` (policy) is intentional: a foreign row should look like it does not exist, while a row reached out-of-scope is explicitly forbidden.
- The first real owned model is **stories** (Phase 2). The foundation is validated today by a fixture model + `tests/Feature/Auth/OwnershipIsolationTest.php`.
- `user_id` is the ownership key everywhere (matches `sessions` / `agent_conversations` and Laravel convention); the relation is named `owner()` for intent.

## Related

- [../../ARCHITECTURE.md](../../ARCHITECTURE.md) §11 — Application foundation (Sprint 2)
- [../../../api/account.md](../../../api/account.md) — account & ownership contract
- [Auth_Signin_Flow.md](./Auth_Signin_Flow.md) — sign-in & route protection
