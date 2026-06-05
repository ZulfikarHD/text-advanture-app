# API Contract — Authentication (Sprint 1)

Endpoint and Inertia-props contract for the Phase 1 / Sprint 1 auth surface. Routes are provided by **Laravel Fortify** (registered as `web` middleware) and consumed via **Wayfinder** typed helpers on the client. Only the auth pages are public; everything else requires the `auth` middleware.

> Storage is UTC; all timestamps in props render in Asia/Jakarta via the shared `standards` prop (see §4).

## 1. Endpoints (Sprint 1 scope)

| Method | URI | Route name | Auth | Purpose |
|--------|-----|------------|------|---------|
| GET | `/login` | `login` | guest | Render the sign-in page (`auth/Login`) |
| POST | `/login` | `login.store` | guest | Authenticate; throttled 5/min per `email\|ip` |
| POST | `/logout` | `logout` | auth | Invalidate the session |
| GET | `/` | `home` | public | Landing page (`Welcome`) with a Log in link |
| GET | `/dashboard` | `dashboard` | auth, verified | Workspace home (post-login destination) |

Wayfinder usage (client):

```ts
import { login, logout } from '@/routes';
// <Form v-bind="login.form()"> ... </Form>
// <Link :href="logout()" method="post" as="button">Log out</Link>
```

Out of Sprint 1 scope but registered by the starter kit (documented in later sprints): register, password reset, email verification, two-factor challenge, passkeys.

## 2. POST `/login` (login.store)

**Request body**

| Field | Type | Rules | Notes |
|-------|------|-------|-------|
| `email` | string | required, email | Lowercased before lookup (`fortify.lowercase_usernames`) |
| `password` | string | required | Verified against the bcrypt hash |
| `remember` | boolean | optional | "Remember me" persistent session |

**Responses**

| Outcome | Result |
|---------|--------|
| Success | 302 redirect to `intended()` → falls back to `/dashboard` (`fortify.home`); session regenerated, user authenticated |
| Invalid credentials | 302 back with a **single generic** validation error on `email` (`auth.failed`: "These credentials do not match our records."). The `password` field is never flagged — the response never discloses which field was wrong (no user enumeration). |
| Throttled | 422/429 with a throttle message after 5 failed attempts/min for the `email\|ip` key; locked for the cool-down window |
| 2FA enabled | 302 redirect to `two-factor.login` with `login.id` in session (the user is not yet authenticated) |

## 3. POST `/logout`

Invalidates the session, regenerates the CSRF token, and redirects to `home` (`/`). Visiting any protected page afterward redirects back to `/login`.

## 4. Shared Inertia props (every response)

Provided by `App\Http\Middleware\HandleInertiaRequests::share()`:

```jsonc
{
  "name": "DINE",
  "auth": { "user": null | { "id": 1, "name": "...", "email": "...", "email_verified_at": "...", "created_at": "...", "updated_at": "..." } },
  "standards": { "timezone": "Asia/Jakarta", "locale": "id-ID", "currency": "IDR" },
  "sidebarOpen": true
}
```

- `auth.user` is the `#[Hidden]`-filtered model — `password`, `two_factor_secret`, `two_factor_recovery_codes`, and `remember_token` are **never** serialized to the client.
- `standards` drives all client-side date/money formatting (see `resources/js/composables/useFormat.ts`). No secrets are shared.

## 5. Page-specific props

| Page | Props | Source |
|------|-------|--------|
| `auth/Login` | `canResetPassword: boolean`, `status?: string` | `FortifyServiceProvider::configureViews()` |
| `Dashboard` | — (shared props only) | `routes/web.php` (`Route::inertia`) |
| `Welcome` | — (shared props only; uses `auth.user` to toggle Log in / Dashboard link) | `routes/web.php` |

## 6. Security contract

- Login throttling: 5/min per transliterated+lowercased `email\|ip` (`FortifyServiceProvider::configureRateLimiting()`).
- Generic failure message → no account enumeration (locked by `AuthenticationTest::test_invalid_credentials_do_not_disclose_which_field_was_wrong`).
- All non-auth routes require `auth`; unauthenticated requests are redirected to `/login` and returned to their intended destination after sign-in.

## Related

- [../architecture/Diagrams/App/Auth_Signin_Flow.md](../architecture/Diagrams/App/Auth_Signin_Flow.md) — sequence + route-protection diagrams
- [../architecture/ARCHITECTURE.md](../architecture/ARCHITECTURE.md) §11 — Application foundation
- [../manual-qa-check/ui/S-1-foundation-auth.md](../manual-qa-check/ui/S-1-foundation-auth.md) — manual QA path
