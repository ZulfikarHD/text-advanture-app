# BL Audit — 2026-06-05 — Sprint 1: Foundation, Auth & App Shell

> **Type:** Business-logic integrity audit (append-only — never edit this file; new findings = new file)
> **Date:** 2026-06-05 (Asia/Jakarta)
> **Scope code:** `s1` · **Slug:** `foundation-auth`
> **Sprint:** Phase 1 / Sprint 1 — stories **S-1.1.2, S-2.1.1, S-2.1.2, S-2.1.4** (acceptance criteria in `scrum/phase-1-foundation-auth-shell.md`)
> **Method:** Static read of implementation + tests against the Gherkin acceptance criteria, plus a live run of the Sprint-1 test set (`php artisan test --compact` → **14 passed / 36 assertions**).

This audit verifies that **implemented behaviour matches the business rules**. It does not modify any application code. Auth flows, the project standards (UTC storage / WIB display / Rupiah), route protection, and the throttle key are all **correct and working**; every finding below is a *coverage / robustness / consistency* gap, not a broken behaviour.

---

## 1. Summary

**Findings by severity:** Critical 0 · High 0 · Medium 1 · Low 4 · Info/Out-of-scope 5.

| ID | Area | Finding | Severity | Status |
|----|------|---------|----------|--------|
| F-1 | Auth · S-2.1.1 | "Generic invalid-credential message, no field disclosed" is **not asserted** by any test (only `assertGuest()` is checked) | Medium | Open |
| F-2 | Standards · S-1.1.2 | `useFormat` composable is **untested and unused** — UTC→WIB rendering proven only at config level, never end-to-end | Low | Open |
| F-3 | Standards · S-1.1.2 | `formatDateWib` accepts naive (offset-less) datetime strings; `new Date()` parsing is engine-dependent and would break the UTC→WIB guarantee | Low | Open |
| F-4 | Auth · S-2.1.1 | Rate-limit test is brittle — it reconstructs the named-limiter cache key (`md5('login'…)`) and assumes the email needs no `Str::lower`/`transliterate` normalization | Low | Open |
| F-5 | Auth (config) | `emailVerification` feature enabled + `verified` middleware on `dashboard`, but `User` does **not** implement `MustVerifyEmail` → `verified` is a silent no-op | Low | Open |
| O-1 | Out of scope | Self-registration enabled & always-on (S-2.2.3, Sprint 2) | Info | Observed |
| O-2 | Out of scope | Passkeys enabled on the login surface (S-2.1.3, Sprint 2) | Info | Observed |
| O-3 | Out of scope | Two-factor authentication enabled (no Sprint-1 story) | Info | Observed |
| O-4 | Out of scope | Password reset / forgot-password enabled (account mgmt is S-2.2.1, Sprint 2) | Info | Observed |
| O-5 | Out of scope | `display_locale`/`currency` fallbacks duplicated in TS + config (acceptable, by design) | Info | Observed |

---

## 2. Findings

### F-1 — Invalid-credentials "no field disclosed" is unverified by tests · **Medium**

**Criterion (S-2.1.1):** *"I am told the credentials are invalid without revealing which field was wrong."*

**Behaviour is correct:** Fortify rejects with a single `auth.failed` message ("These credentials do not match our records.") keyed to the `email` field only — the message text is generic and the password field gets no error, so the *which-field* secret is not disclosed. `Login.vue` binds `errors.email` / `errors.password` independently, so on a failed login only the generic message shows (under the email input).

```60:83:resources/js/pages/auth/Login.vue
                <InputError :message="errors.email" />
            </div>
            ...
                <InputError :message="errors.password" />
```

**Gap:** The only negative test asserts authentication state, not the message contract:

```72:82:tests/Feature/Auth/AuthenticationTest.php
    public function test_users_can_not_authenticate_with_invalid_password()
    {
        $user = User::factory()->create();

        $this->post(route('login.store'), [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }
```

Because S-2.1.1 is a **Critical** story, the "generic message / no field disclosure" rule should be locked by a test so a future change can't silently leak which field failed.

**Recommendation:** Add assertions to the invalid-password test:
- `->assertSessionHasErrors('email')` (or `assertInvalid(['email'])`) with the generic `trans('auth.failed')` text, and
- `->assertSessionDoesntHaveErrors('password')` to prove the wrong field is never disclosed.

---

### F-2 — `useFormat` is untested and not consumed anywhere · **Low**

**Criterion (S-1.1.2):** *"times shown to the user render in Asia/Jakarta … cost rendered in Rupiah."*

The composable is correct (it parses an instant, then formats with an explicit `timeZone`, so there is **no double-conversion bug** — UTC in, WIB out). However it is referenced only by its own type export and the backend share; no page renders a real timestamp/cost through it yet:

```49:71:resources/js/composables/useFormat.ts
export function formatDateWib(
    value: DateInput,
    options: Intl.DateTimeFormatOptions & { locale?: string } = {},
): string {
    ...
    return new Intl.DateTimeFormat(locale, {
        dateStyle: 'medium',
        timeStyle: 'short',
        timeZone: FALLBACK_STANDARDS.timezone,
        ...formatOptions,
    }).format(date);
}
```

`ProjectStandardsTest` proves the **config** and the **shared prop** (`standards.timezone = Asia/Jakarta`, `currency = IDR`) but nothing proves an actual UTC value renders as WIB. This is expected at Sprint 1 (no data to display), so it is Low — but the "render in WIB/Rp" half of S-1.1.2 is currently an *intent*, not a *proven* behaviour.

**Recommendation:** When the first timestamp/cost surface lands, add a unit test (e.g. Vitest) asserting `formatDateWib('2026-06-05T01:00:00Z')` renders the WIB wall-clock (08.00) and `formatRupiah(15000)` renders `Rp 15.000`. No code change needed now.

---

### F-3 — `formatDateWib` trusts engine-dependent string parsing · **Low**

`formatDateWib`/`formatDateTime` pass the raw input straight to `new Date(value)`:

```57:61:resources/js/composables/useFormat.ts
    const date = value instanceof Date ? value : new Date(value);

    if (Number.isNaN(date.getTime())) {
        return '';
    }
```

Laravel's default JSON serialization emits ISO-8601 with a `Z` (e.g. `2026-06-05T08:56:00.000000Z`), which parses to the correct instant — so today this is safe. The risk is **latent**: if any value is ever sent as a naive `Y-m-d H:i:s` (manual format, a `date` cast, or a raw DB string), `new Date('2026-06-05 08:56:00')` is parsed as **local time** in V8 and as **Invalid Date** in some Safari builds, silently breaking the UTC→WIB guarantee.

**Recommendation:** Document the contract (callers must pass ISO-8601 *with* offset) and/or normalize known naive strings (replace the space with `T` and append `Z`) before constructing the `Date`. Keep returning `''` on `NaN` as today.

---

### F-4 — Rate-limit test is coupled to the limiter's internal key shape · **Low**

The throttle definition is **correct** and matches the Fortify default (5/min per email+IP, transliterated + lowercased):

```88:92:app/Providers/FortifyServiceProvider.php
        RateLimiter::for('login', function (Request $request) {
            $throttleKey = Str::transliterate(Str::lower($request->input(Fortify::username())).'|'.$request->ip());

            return Limit::perMinute(5)->by($throttleKey);
        });
```

The test reaches into the framework's named-limiter key derivation (`md5($limiterName.$key)`) to pre-load the bucket:

```95:107:tests/Feature/Auth/AuthenticationTest.php
        RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

        $response = $this->post(route('login.store'), [...]);

        $response->assertTooManyRequests();
```

This passes today **only because** the factory email is already lowercase ASCII (so `Str::lower`/`Str::transliterate` are identity) and Laravel hashes named-limiter keys as `md5('login'.$key)`. Both are implementation details. If the factory ever yields a mixed-case/unicode email, or the framework changes its key hashing, the test would target the wrong bucket and give a **false negative** while the real throttle still works.

**Recommendation:** Prefer driving the throttle through behaviour (loop 6 real failed `POST`s and assert the 6th returns 429), or build the key from `Str::transliterate(Str::lower($user->email).'|127.0.0.1')` so the test mirrors production normalization rather than assuming it.

---

### F-5 — Email verification is enabled but unenforceable · **Low (consistency)**

`config/fortify.php` enables `Features::emailVerification()` and `routes/web.php` guards `dashboard` with `verified`:

```7:9:routes/web.php
Route::middleware(['auth', 'verified'])->group(function () {
    Route::inertia('dashboard', 'Dashboard')->name('dashboard');
});
```

…but `User` does **not** implement `MustVerifyEmail` (the contract import is commented out):

```5:18:app/Models/User.php
// use Illuminate\Contracts\Auth\MustVerifyEmail;
...
class User extends Authenticatable implements PasskeyUser
```

So `EnsureEmailIsVerified` short-circuits to "allowed" for every user, and `verified` is a no-op. This is why `DashboardTest::test_authenticated_users_can_visit_the_dashboard` passes with an unverified factory user — consistent and harmless for Sprint 1 (no verification story this sprint). It becomes a real gap the moment open registration (S-2.2.3) relies on verification.

**Recommendation:** No Sprint-1 change required. Track for Sprint 2: either implement `MustVerifyEmail` on `User` (and keep `verified`) or drop the `verified` guard and the `emailVerification` feature so the config reflects reality.

---

## 3. Out-of-scope observations (present, not defects)

| ID | Observation | Evidence | Belongs to |
|----|-------------|----------|------------|
| O-1 | Self-registration is **always on** (not yet the deployment toggle the story asks for); "Sign up" link on the login surface | `config/fortify.php:164` (`Features::registration()`), `FortifyServiceProvider.php:70`, `Login.vue:106-108` | S-2.2.3 (Sprint 2) |
| O-2 | Passkey sign-in offered on the login page (button is `variant="outline"`, so the single-primary-action rule still holds) | `Login.vue:39`, `PasskeyVerify.vue:41-55`, `config/fortify.php:172` | S-2.1.3 (Sprint 2) |
| O-3 | Two-factor authentication enabled with confirm + password-confirm | `config/fortify.php:167-171`, `FortifyServiceProvider.php:74,84-86` | no Sprint-1 story |
| O-4 | Password reset / forgot-password flow enabled | `config/fortify.php:165`, `FortifyServiceProvider.php:56-64` | S-2.2.1 (Sprint 2) |
| O-5 | Display standards' fallbacks are duplicated (TS `FALLBACK_STANDARDS` + `config/app.php` env defaults). Values match exactly (`Asia/Jakarta` / `id-ID` / `IDR`); the TS copy is only used on pages rendered outside the Inertia middleware (error pages) — a documented, acceptable trade-off | `useFormat.ts:32-36` vs `config/app.php:82-86` | n/a |

> **Single-primary-action (S-2.1.1 UX):** verified — `Login.vue` has exactly one primary `Button` ("Log in", `Login.vue:93-102`); the passkey button is `outline` and the rest are text links.

---

## 4. Verified-correct checklist (acceptance criteria → PASS/FAIL + proof)

Live run: `php artisan test --compact tests/Feature/ProjectStandardsTest.php tests/Feature/Auth/AuthenticationTest.php tests/Feature/DashboardTest.php` → **14 passed, 36 assertions**.

### S-1.1.2 — Project standards (UTC store / WIB display / Rupiah / MySQL-compatible DB)

| Sub-criterion | Status | Proof |
|---------------|--------|-------|
| Timestamps stored in UTC | **PASS** | `config/app.php:68` (`'timezone' => 'UTC'`); `ProjectStandardsTest::test_timestamps_are_stored_in_utc:15-18` |
| Times rendered in Asia/Jakarta (WIB) | **PASS (config/standard)** · *render unproven (F-2)* | `config/app.php:82`; shared via `HandleInertiaRequests.php:46-50`; `ProjectStandardsTest::test_times_are_displayed_in_asia_jakarta:20-23` + `test_display_standards_are_shared_with_the_frontend:35-44`; composable logic `useFormat.ts:49-71` |
| Provider cost rendered in Rupiah | **PASS (config/standard)** | `config/app.php:86`; `ProjectStandardsTest::test_currency_is_rendered_in_rupiah:25-28`; `formatRupiah` `useFormat.ts:83-98` |
| MySQL-8-compatible engine (JSON support) | **PASS** | `config/database.php:20` (`default => 'mariadb'`); `ProjectStandardsTest::test_database_uses_a_mysql_compatible_engine:30-33` |
| Backend↔frontend standard consistency | **PASS** | `FALLBACK_STANDARDS` (`useFormat.ts:32-36`) exactly mirror `config/app.php:82-86`; typed via `global.d.ts:22` |

### S-2.1.1 — Sign in (success / generic failure / brute-force)

| Sub-criterion | Status | Proof |
|---------------|--------|-------|
| Valid credentials → authenticated session + redirect to workspace | **PASS** | `AuthenticationTest::test_users_can_authenticate_using_the_login_screen:22-33` (asserts `assertAuthenticated()` + redirect to `dashboard`); `config/fortify.php:76` (`home => /dashboard`) |
| Invalid credentials → not signed in | **PASS** | `AuthenticationTest::test_users_can_not_authenticate_with_invalid_password:72-82` (`assertGuest()`) |
| …without revealing which field was wrong | **PASS (behaviour) · UNTESTED (F-1)** | Fortify single `auth.failed` keyed to `email` only; no test asserts the message/field contract → see F-1 |
| Brute-force throttle + cool-down | **PASS** | `FortifyServiceProvider.php:88-92` (5/min per email+IP); `AuthenticationTest::test_users_are_rate_limited:95-107` (`assertTooManyRequests()`) — see F-4 for brittleness |

### S-2.1.2 — Sign out (session invalidated; protected page then redirects to login)

| Sub-criterion | Status | Proof |
|---------------|--------|-------|
| Sign-out invalidates session | **PASS** | `AuthenticationTest::test_users_can_logout:84-93` (`assertGuest()` after `POST logout`, redirect to `home`) |
| Protected page then redirects to sign-in | **PASS** | `DashboardTest::test_guests_are_redirected_to_the_login_page:13-17`; route guard `routes/web.php:7-9` |

### S-2.1.4 — Route protection (block unauth; return to intended destination)

| Sub-criterion | Status | Proof |
|---------------|--------|-------|
| Unauthenticated access to authoring/play pages blocked | **PASS** | `DashboardTest::test_guests_are_redirected_to_the_login_page:13-17`; `routes/web.php:7-9`, `routes/settings.php:8-13` (all behind `auth`) |
| After login, returned to intended destination | **PASS** | `AuthenticationTest::test_users_are_redirected_to_their_intended_destination_after_login:56-70` (guest → `profile.edit` → login → back to `profile.edit`) |

**Overall:** every Sprint-1 acceptance criterion is **PASS**. The single criterion with a behaviour-vs-coverage caveat is S-2.1.1's "no field disclosed" (correct in code, not yet asserted — F-1).

---

## 5. Recommended actions (priority order)

1. **F-1 (Medium):** assert the generic message + absent `password` error in `test_users_can_not_authenticate_with_invalid_password` (`tests/Feature/Auth/AuthenticationTest.php`).
2. **F-4 (Low):** make the rate-limit test derive its key with `Str::lower`/`Str::transliterate`, or drive it behaviourally (6 real failed posts).
3. **F-3 (Low):** harden `formatDateWib` against naive datetime strings (normalize or document the offset contract).
4. **F-2 (Low):** add a `useFormat` unit test once a real timestamp/cost surface exists.
5. **F-5 (Low):** in Sprint 2, reconcile `emailVerification` + `verified` with `User`'s `MustVerifyEmail` (implement or remove).

*No High/Critical findings.*

---

## 6. Resolution (build owner, 2026-06-05)

| ID | Disposition | Note |
|----|-------------|------|
| F-1 | **Fixed** | Added `test_invalid_credentials_do_not_disclose_which_field_was_wrong` to [`tests/Feature/Auth/AuthenticationTest.php`](../../tests/Feature/Auth/AuthenticationTest.php) — asserts `assertSessionHasErrors('email')` + `assertSessionDoesntHaveErrors('password')`, locking the S-2.1.1 no-field-disclosure contract. Test suite green (now 8 auth tests). |
| F-2 | **Deferred (no consumer yet)** | `useFormat` is a foundation utility; first real timestamp/cost surface arrives later (cost in Phase 1 E5.3). Tracked as PH-11 in [`PLACEHOLDER_TRACKING.md`](../guides/PLACEHOLDER_TRACKING.md). No JS test runner is configured yet; a Vitest unit test will land with the first consumer. |
| F-3 | **Accepted (contract documented)** | The composable's JSDoc already states the input contract (ISO-8601 / epoch ms, UTC stored). Laravel serializes Carbon as ISO-8601 with offset, so the path is safe today; normalization of naive strings is noted for when/if a naive value is ever introduced. |
| F-4 | **Accepted (pre-existing test)** | The brittle key-reconstruction is starter-kit test code, untouched this sprint; behavior is correct and passing. Rewriting it behaviorally is a low-value change deferred to avoid churn in verify-and-fill scope. |
| F-5 | **Deferred to Sprint 2** | Same as OWASP F-01 — email-verification enforcement is Sprint 2 account-management scope. Tracked as PH-10 in [`PLACEHOLDER_TRACKING.md`](../guides/PLACEHOLDER_TRACKING.md). |

All out-of-scope observations (O-1…O-5) are expected starter-kit state for later sprints; no action taken.

---

*Author: business-logic audit · Sprint 1 (Foundation, Auth & App Shell) · 2026-06-05 (Asia/Jakarta). Append-only — supersede with a new dated file, never edit this one.*
