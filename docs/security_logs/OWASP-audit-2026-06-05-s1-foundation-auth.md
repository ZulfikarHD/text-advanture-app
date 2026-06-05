# OWASP Top 10 Audit — Sprint 1 (Foundation, Auth & App Shell)

> **Type:** OWASP Top 10 (2021) · **Sprint:** S1 — Foundation, Auth & App Shell
> **Date:** 2026-06-05 (Asia/Jakarta) · **Auditor:** Security audit subagent
> **Scope:** Authentication + scaffold surface changed/added in Sprint 1 (see file list below).
> **Append-only:** Per [`README.md`](./README.md) and [`DOCUMENTATION_STRUCTURE.md`](../DOCUMENTATION_STRUCTURE.md) §5.2 — never edit; new findings = new file.

---

## Scope audited

**Changed / added this sprint**

- `.env` (untracked) and `.env.example` — DB → MariaDB, `APP_NAME=DINE`, display-standards keys
- `config/database.php` — default connection `mariadb`
- `config/app.php` — `display_timezone`, `display_locale`, `currency`
- `phpunit.xml` — test DB `novel_engine_test` on `mariadb`
- `app/Http/Middleware/HandleInertiaRequests.php` — new `standards` shared prop block
- `resources/js/composables/useFormat.ts` — display formatting only
- `tests/Feature/ProjectStandardsTest.php`, `tests/Feature/Auth/AuthenticationTest.php`

**Pre-existing, verified in scope**

- `app/Providers/FortifyServiceProvider.php`, `config/fortify.php`, `config/auth.php`
- `routes/web.php`, `routes/settings.php`
- `app/Models/User.php`, `app/Actions/Fortify/*`, `app/Concerns/PasswordValidationRules.php`
- `app/Http/Controllers/Settings/{ProfileController,SecurityController}.php`
- `resources/js/pages/auth/Login.vue`, `bootstrap/app.php`

---

## Summary of findings

| ID | OWASP category | Severity | Status |
|------|----------------------------------------------|----------|--------|
| F-01 | A05 Security Misconfiguration / A01 Broken Access Control | Medium | Open |
| F-02 | A05 Security Misconfiguration | Low | Open (deployment-time) |
| F-03 | A04 Insecure Design / A07 Auth Failures | Low | Open (hardening) |
| F-04 | A02 Cryptographic Failures | Info | Advisory |

**Severity counts:** Critical 0 · High 0 · Medium 1 · Low 2 · Info 1

> **No Critical or High findings.** The single Medium is a latent control gap (an enabled email-verification feature that is not actually enforced because the model does not implement the contract).

---

## Findings

### F-01 — `verified` middleware is non-functional: email verification enabled but not enforced — **Medium**

**OWASP:** A05 Security Misconfiguration (primary), A01 Broken Access Control (effect).

**Evidence**

- Email verification is enabled as a Fortify feature:

```163:175:config/fortify.php
    'features' => [
        Features::registration(),
        Features::resetPasswords(),
        Features::emailVerification(),
        Features::twoFactorAuthentication([
            'confirm' => true,
            'confirmPassword' => true,
            // 'window' => 0
        ]),
        Features::passkeys([
            'confirmPassword' => true,
        ]),
    ],
```

- Protected routes rely on the `verified` middleware:

```7:9:routes/web.php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});
```

(also `settings/appearance`, `settings/security`, `settings/password`, `settings/profile` DELETE — confirmed via `php artisan route:list -v`.)

- But the `User` model does **not** implement `MustVerifyEmail` — the import is commented out and the class only implements `PasskeyUser`:

```5:18:app/Models/User.php
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
```

**Why it matters**

Laravel's `EnsureEmailIsVerified` (`verified`) middleware only blocks a user when the authenticated model implements `MustVerifyEmail`; otherwise it passes through. As configured, every `verified`-gated route is effectively protected by `auth` **only**. The intent (verification feature on, `verified` middleware applied, `ProfileController::edit` exposing a `mustVerifyEmail` prop, and `ProfileController::update` nulling `email_verified_at` on email change) all assume verification is enforced — but it never is. This is a control that appears present yet does nothing (a false sense of protection). Impact is currently limited because no sensitive data sits behind the wall in Sprint 1, hence Medium rather than High.

Supporting evidence that verification is *intended*:

```31:44:app/Http/Controllers/Settings/ProfileController.php
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();
```

**Recommendation**

Make the model implement the contract so the existing middleware actually enforces verification:

```php
use Illuminate\Contracts\Auth\MustVerifyEmail;

class User extends Authenticatable implements MustVerifyEmail, PasskeyUser
```

Then add a regression test asserting an unverified user is redirected away from a `verified` route (e.g. `GET /dashboard` → `route('verification.notice')`). Alternatively, if verification is intentionally deferred past Sprint 1, remove `Features::emailVerification()` and the `verified` middleware so the codebase does not imply a guarantee it doesn't provide, and record the deferral in `guides/PLACEHOLDER_TRACKING.md`.

---

### F-02 — `.env.example` ships `APP_DEBUG=true` / `APP_ENV=local`; dev debug tooling present — **Low** (deployment-time)

**OWASP:** A05 Security Misconfiguration.

**Evidence**

```1:5:.env.example
APP_NAME=DINE
APP_ENV=local
APP_KEY=
APP_DEBUG=true
APP_URL=http://localhost
```

- `config/app.php` defaults are safe (`env` → `production`, `debug` → `false`), so this is purely the template/runtime value, not a code defect:

```29:42:config/app.php
    'env' => env('APP_ENV', 'production'),
    ...
    'debug' => (bool) env('APP_DEBUG', false),
```

- `barryvdh/laravel-debugbar` is installed and registers `_debugbar/*` routes (confirmed via `php artisan route:list`). Debugbar self-disables when `APP_DEBUG=false`, so this is gated by the same flag.

**Why it matters**

If a real environment is provisioned by copying `.env.example` without flipping these values, `APP_DEBUG=true` leaks stack traces, environment values, and query data (and enables Debugbar) — classic A05 exposure. No tracked file forces a safe value.

**Recommendation**

Keep `.env.example` as-is for local DX, but add a deployment guard: a startup/CI check (or `AppServiceProvider::boot()` assertion) that fails when `app()->isProduction()` and `config('app.debug') === true`. Document the production values (`APP_ENV=production`, `APP_DEBUG=false`) in the deploy runbook. Ensure `laravel/debugbar` stays a `require-dev` dependency.

---

### F-03 — Password policy is the framework default (min 8, no breach check) — **Low** (hardening)

**OWASP:** A04 Insecure Design, A07 Identification & Authentication Failures.

**Evidence**

```15:18:app/Concerns/PasswordValidationRules.php
    protected function passwordRules(): array
    {
        return ['required', 'string', Password::default(), 'confirmed'];
    }
```

`Password::default()` resolves to `Password::min(8)` because no `Password::defaults(...)` callback is registered in any service provider (`FortifyServiceProvider` and `AppServiceProvider` do not set one). Hashing strength itself is good — `BCRYPT_ROUNDS=12` and the `password` `hashed` cast — see "Verified secure".

**Why it matters**

8 characters with no compromised-password check permits weak/known-breached credentials. Not a vulnerability per se, but below current guidance for an app that will gate narrative/LLM-cost-bearing features.

**Recommendation**

Register a stronger shared policy once in `AppServiceProvider::boot()`, e.g. `Password::defaults(fn () => Password::min(12)->uncompromised())` (in production only if HIBP calls are undesirable in tests). This automatically flows into registration, reset, and the `passwordRules` view string. Add a unit test asserting a known-weak password is rejected.

---

### F-04 — Secrets handling reminder: `APP_KEY` must be generated; never commit real secrets — **Info** (advisory)

**OWASP:** A02 Cryptographic Failures.

**Evidence**

- `.env.example` correctly ships empty secrets (`APP_KEY=`, `DB_PASSWORD=`, `AWS_*=`), so encryption keys/credentials are not templated into VCS.
- `config/app.php` derives the cipher key from `APP_KEY`:

```116:118:config/app.php
    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),
```

**Why it matters**

Cookie/session encryption, signed URLs (incl. email-verification links), and Fortify's `two_factor_secret` encryption all depend on a properly set `APP_KEY`. An empty or shared key undermines all of these.

**Recommendation**

No code change. Operationally: run `php artisan key:generate` per environment, keep `APP_KEY` only in the untracked `.env`, and confirm `.env` stays out of VCS (see "Verified secure" A05). This is recorded as advisory for the deploy runbook.

---

## Verified secure

The following were checked and passed for Sprint 1.

**A01 — Broken Access Control**
- All non-public routes are gated by `auth`; `dashboard`, `settings/appearance`, `settings/security`, `settings/password`, and `settings/profile` DELETE additionally carry `verified` (functional state tracked in F-01). Confirmed via `php artisan route:list -v`.
- `settings/security` sits behind `RequirePassword` so 2FA secrets/passkeys require re-authentication — `routes/settings.php:18-20`.
- Password change is throttled — `Route::put('settings/password', ...)->middleware('throttle:6,1')` (`routes/settings.php:22-24`).
- Intended-redirect after login works and is regression-tested — `tests/Feature/Auth/AuthenticationTest.php:56-70`.
- No unprotected sensitive route found; `/` and `/up` are intentionally public.

**A02 — Cryptographic Failures**
- Passwords hashed via the `hashed` cast with `BCRYPT_ROUNDS=12` — `app/Models/User.php:28-35`, `.env.example:16`.
- Sensitive attributes never serialized to the client: `#[Hidden(['password','two_factor_secret','two_factor_recovery_codes','remember_token'])]` — `app/Models/User.php:17`.
- Cookies encrypted except two non-sensitive UI cookies — `bootstrap/app.php:18`.

**A03 — Injection**
- No raw SQL / `DB::raw` with user input introduced. `SecurityController::edit` uses Eloquent with an explicit column allow-list (`->select([...])`) — `app/Http/Controllers/Settings/SecurityController.php:24-39`. Display formatting (`useFormat.ts`) is client-side `Intl` only.

**A04 — Insecure Design**
- Login throttling = 5/min keyed on `email|ip` — `FortifyServiceProvider.php:88-92`; enforced and regression-tested — `AuthenticationTest.php:95-107`.
- Generic invalid-credential handling: Fortify returns the `auth.failed` message attached to the email field without disclosing whether the account exists (no user enumeration). `Login.vue` renders only the server-provided `errors.email`/`errors.password` — `Login.vue:60,83`.

**A05 — Security Misconfiguration**
- `.env` is **gitignored and NOT tracked**: `.gitignore:15` lists `.env`, and `git ls-files --error-unmatch .env` returns `error: pathspec '.env' did not match any file(s)` (exit 1 = untracked). The real local DB password is therefore not committed.
- `.env.example` contains no real secrets (empty `APP_KEY`, `DB_PASSWORD`, `AWS_*`).
- Shared Inertia props leak nothing sensitive: `standards` exposes only `timezone`/`locale`/`currency`, and `auth.user` is the `#[Hidden]`-filtered model — `HandleInertiaRequests.php:41-52`.

**A07 — Identification & Authentication Failures**
- Session is regenerated on login and invalidated on logout (Fortify `AuthenticatedSessionController` defaults); logout flow regression-tested — `AuthenticationTest.php:84-93`.
- Account deletion invalidates the session and regenerates the CSRF token — `ProfileController.php:53-58`.
- Rate-limit keys are correctly scoped: login by `email|ip`, two-factor by `login.id` session, passkeys by `credential.id|ip` — `FortifyServiceProvider.php:84-98`.

**A09 — Logging & Monitoring (note)**
- Sprint 1 has **no LLM/provider call log yet** — out of scope, nothing to audit. Standard Laravel stack logging is configured (`LOG_CHANNEL=stack`). Recommendation for a later sprint: add structured logging of auth failures/throttle events and (when introduced) an append-only provider-call/cost log.

**Engine A0 / A0b leak guards (note — not yet applicable)**
- Per [`README.md`](./README.md), the engine's context-isolation boundary (assembler, ADR 0007) and the A0/A0b leak guards (ADR 0008–0010) are **not present in Sprint 1** — there are no agents, `true_state`, edges, beat docs, narrator/nudge compile, or recorder `surface` yet. No leak surface exists to test.
- **Boundary expectation recorded:** once the NPC context assembler and narrator/recorder land, this audit series must verify (A0) no NPC ever receives another character's `true_state`, others' edges, the beat doc, or narrator instructions; and (A0b) own capped feelings are never stated plainly, authorial omniscience never crosses the nudge compile, and others' hidden truth never crosses the recorder `surface` (hedged-attribution enforced structurally). Player-input prompt-injection and Claude API key handling enter scope at the same time.

---

## Methodology

- Read project security skills (`fortify-development`, `laravel-best-practices`) and the `security_logs` / documentation-structure conventions before auditing.
- Static review of every in-scope file (cited above by `path:line`).
- Dynamic confirmation of route protection via `php artisan route:list -v` (middleware stacks for `dashboard` and `settings/*`).
- Git tracking check via `git ls-files --error-unmatch .env` (untracked) and `.gitignore` inspection.
- No application or code files were modified; this audit created only this markdown file.

---

## Resolution (build owner, 2026-06-05)

Disposition of each finding after review by the implementing agent.

| ID | Disposition | Note |
|------|-------------|------|
| F-01 | **Deferred to Sprint 2** | Enforcing email verification (implementing `MustVerifyEmail`) requires a working mail/verification UX, which is Sprint 2 account-management scope (E2.2). Turning it on now would add friction to the Sprint-1 sign-in → dashboard happy path. Recorded as a placeholder (PH-10) in [`PLACEHOLDER_TRACKING.md`](../guides/PLACEHOLDER_TRACKING.md). Factory users are verified, so behavior is correct in Sprint 1; the latent inconsistency is tracked, not shipped silently. |
| F-02 | **Accepted / runbook** | Documented production values (`APP_ENV=production`, `APP_DEBUG=false`) and a connection/debug-safety check in [`runbooks/local-setup-diagnostics.md`](../runbooks/local-setup-diagnostics.md). A hard startup guard is a low-priority hardening item for a later sprint. |
| F-03 | **Not a defect (correction)** | The finding states no `Password::defaults()` callback is registered. This is incorrect: [`app/Providers/AppServiceProvider.php`](../../app/Providers/AppServiceProvider.php) `configureDefaults()` registers `Password::min(12)->mixedCase()->letters()->numbers()->symbols()->uncompromised()` for production (and `null` in dev/test so tests may use `password`). Production policy is therefore already strong; no change required. |
| F-04 | **Accepted / runbook** | Advisory only; `APP_KEY` generation and secret handling captured in the setup runbook. |

**Fix applied this sprint:** none of the above required code changes. The related business-logic finding (no-field-disclosure contract untested) was fixed by adding `test_invalid_credentials_do_not_disclose_which_field_was_wrong` to [`tests/Feature/Auth/AuthenticationTest.php`](../../tests/Feature/Auth/AuthenticationTest.php), which now locks the A04/A07 "no user enumeration" guarantee.
