# Auth Sign-in & Route Protection

Sign-in, sign-out, and route protection for the Phase 1 / Sprint 1 application shell (Laravel Fortify). Storage is UTC; the only public surfaces are the auth pages. Source of truth: `routes/web.php`, `app/Providers/FortifyServiceProvider.php`, `config/fortify.php`.

## Sign-in / sign-out sequence

```mermaid
sequenceDiagram
    actor User
    participant Welcome as "Welcome (/)"
    participant Login as "auth/Login"
    participant Fortify as "Fortify AuthenticatedSessionController"
    participant Throttle as "RateLimiter login (5/min email|ip)"
    participant Dashboard as "Dashboard (/dashboard)"

    User->>Welcome: open app root
    Welcome-->>User: shows "Log in" link (Wayfinder login())
    User->>Login: navigate to GET /login
    User->>Fortify: POST /login (login.store)
    Fortify->>Throttle: check attempts for email|ip
    alt throttle exceeded
        Throttle-->>User: "429 Too Many Requests (cool-down)"
    else valid credentials
        Fortify->>Fortify: regenerate session
        Fortify-->>Dashboard: "redirect()->intended(/dashboard)"
    else invalid credentials
        Fortify-->>Login: "generic auth.failed (field not disclosed)"
    end

    User->>Fortify: POST /logout
    Fortify->>Fortify: invalidate session + regenerate token
    Fortify-->>Welcome: redirect home
```

## Route protection (intended-destination)

```mermaid
flowchart TD
    Request["Request to protected page (e.g. /dashboard)"] --> AuthCheck{authenticated?}
    AuthCheck -->|yes| Page["Render page"]
    AuthCheck -->|no| Store["Store intended URL in session"]
    Store --> Redirect["Redirect to /login"]
    Redirect --> SignIn["User signs in"]
    SignIn --> Intended["redirect to intended destination"]
    Intended --> Page
```

## Notes

- `auth` guards every authoring/play surface. Email verification was removed in Sprint 2 (the `verified` guard was a no-op — PH-10 resolved), so there is no `verified` middleware and no mailer is needed to sign in. Owner-scoping for owned resources is layered on top of `auth` — see [Account_Ownership_Isolation.md](./Account_Ownership_Isolation.md).
- The throttle key is `Str::transliterate(Str::lower(email).'|'.ip)` — see `FortifyServiceProvider::configureRateLimiting()`.
- Times shown anywhere render in Asia/Jakarta via the shared `standards` prop + `useFormat` composable.

## Related

- [../../ARCHITECTURE.md](../../ARCHITECTURE.md) §11 — Application foundation
- [../../../api/auth.md](../../../api/auth.md) — endpoint & Inertia-props contract
- [../../../manual-qa-check/ui/S-1-foundation-auth.md](../../../manual-qa-check/ui/S-1-foundation-auth.md) — manual QA path
