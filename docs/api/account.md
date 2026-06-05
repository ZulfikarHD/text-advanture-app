# API Contract — Account, Registration & Ownership (Sprint 2)

Endpoint and Inertia-props contract for the Sprint 2 account surface: self-service **account management** (profile / password / passkeys / 2FA), the **configurable registration** toggle (S-2.2.3), and the **account-isolation** convention every owned resource inherits (S-2.2.2). Routes come from **Laravel Fortify** + the **Passkeys** package and are consumed through **Wayfinder** typed helpers. Everything here requires the `auth` middleware unless noted.

> Sign-in itself is documented in [auth.md](./auth.md). Storage is UTC; timestamps render in Asia/Jakarta via the shared `standards` prop.

## 1. Account management endpoints

| Method | URI | Route name | Auth | Purpose |
|--------|-----|------------|------|---------|
| GET | `/settings` | — (redirect) | auth | Redirects to `/settings/profile` |
| GET | `/settings/profile` | `profile.edit` | auth | Render profile settings (`settings/Profile`) |
| PATCH | `/settings/profile` | `profile.update` | auth | Update name + email |
| DELETE | `/settings/profile` | `profile.destroy` | auth | Delete the account (password-confirmed dialog) |
| GET | `/settings/security` | `security.edit` | auth + `password.confirm` | Render security settings (password, 2FA, passkeys) |
| PUT | `/settings/password` | `user-password.update` | auth (throttle 6/min) | Change password |
| GET | `/settings/appearance` | `appearance.edit` | auth | Theme / appearance preferences |

### Passkeys (WebAuthn) — `Laravel\Passkeys`

| Method | URI | Route name | Purpose |
|--------|-----|------------|---------|
| GET | `/user/passkeys/options` | `passkey.registration-options` | Begin registration (challenge) |
| POST | `/user/passkeys` | `passkey.store` | Store a newly registered passkey |
| DELETE | `/user/passkeys/{passkey}` | `passkey.destroy` | Remove a passkey (confirmed in a dialog) |
| GET | `/passkeys/login/options` | `passkey.login-options` | Begin passkey sign-in (challenge) |
| POST | `/passkeys/login` | `passkey.login` | Complete passkey sign-in |

### Two-factor authentication — `Laravel\Fortify`

`two-factor.enable` / `.confirm` / `.disable`, `two-factor.qr-code`, `two-factor.secret-key`, `two-factor.recovery-codes` (+ regenerate), and `two-factor.login` for the challenge during sign-in. The Security page renders the enable/confirm/recovery flow inline.

Destructive actions (account delete, passkey removal, 2FA disable) are always confirmed through a dialog — there are **no native `alert()`/`confirm()` calls** anywhere in the client. Toasts use `sonner`.

## 2. PATCH `/settings/profile` (profile.update)

| Field | Type | Rules |
|-------|------|-------|
| `name` | string | required, max 255 |
| `email` | string | required, email, unique (ignoring self) |

On a successful email change the user's `email_verified_at` is reset to `null`. Email verification is **not** enforced (removed in Sprint 2, PH-10); the column is retained for a possible future opt-in.

## 3. Registration toggle (S-2.2.3)

Self-registration is a deployment switch, not a code change:

| Setting | Value |
|---------|-------|
| Config | `config('app.registration_enabled')` |
| Env | `REGISTRATION_ENABLED` (default `true`) |
| Shared prop | `canRegister: boolean` (see [auth.md](./auth.md) §4) |

| Method | URI | Route name | Enabled | Disabled |
|--------|-----|------------|---------|----------|
| GET | `/register` | `register` | Render `auth/Register` | **404** |
| POST | `/register` | `register.store` | Create account + sign in | **404** |

The `register` route stays registered in both states so the Wayfinder-typed `register` helper survives the build; the toggle is enforced at the application layer (`FortifyServiceProvider::registerView` closure + `App\Actions\Fortify\CreateNewUser`), and `canRegister` hides the "Register" / "Sign up" links on Welcome + Login. Sign-in is never affected. Covered by `tests/Feature/Auth/RegistrationToggleTest.php`.

## 4. Account isolation (ownership) convention (S-2.2.2)

Owned resources are **owner-scoped by default** — "multi-user" means account isolation, not roles/admin. Every owned model adopts the same three pieces:

| Piece | Responsibility |
|-------|----------------|
| `App\Models\Concerns\BelongsToOwner` (trait) | Applies the owner global scope, stamps `user_id` on create, exposes `owner()` (belongsTo `User`) |
| `App\Models\Scopes\OwnerScope` (global scope) | Constrains queries to `user_id = Auth::id()` **while authenticated** (no-op for console/seeders/jobs) |
| `App\Policies\OwnerPolicy` (abstract policy) | Authorizes `view`/`update`/`delete` by ownership; concrete policies extend it |

**Contract for owned endpoints (lands with the first owned model in Phase 2):**

- A resource owned by another user is **invisible** — route-model binding resolves to **404** (existence is never leaked).
- A resource reached out of scope but explicitly checked against the policy is **403**.
- `user_id` is the ownership foreign key everywhere (consistent with `sessions` / `agent_conversations`).
- New rows created by an authenticated request are stamped with the current user automatically.

Validated today by a fixture model + `tests/Feature/Auth/OwnershipIsolationTest.php`. Diagram: [../architecture/Diagrams/App/Account_Ownership_Isolation.md](../architecture/Diagrams/App/Account_Ownership_Isolation.md).

## 5. Security contract

- Account deletion and password change sit behind password confirmation / re-entry; password change is throttled (6/min).
- `auth.user` is `#[Hidden]`-filtered — secrets (`password`, `two_factor_secret`, `two_factor_recovery_codes`, `remember_token`) are never serialized.
- The registration toggle is enforced server-side; hiding the UI link is defence-in-depth, not the control.

## Related

- [auth.md](./auth.md) — sign-in, shared props & `canRegister`
- [../architecture/Diagrams/App/Account_Ownership_Isolation.md](../architecture/Diagrams/App/Account_Ownership_Isolation.md) — isolation flow
- [../architecture/Diagrams/App/App_Shell_Navigation.md](../architecture/Diagrams/App/App_Shell_Navigation.md) — shell & nav
- [../architecture/ARCHITECTURE.md](../architecture/ARCHITECTURE.md) §11 — Application foundation (Sprint 2)
- [../manual-qa-check/ui/S-2-account-shell.md](../manual-qa-check/ui/S-2-account-shell.md) — manual QA path
