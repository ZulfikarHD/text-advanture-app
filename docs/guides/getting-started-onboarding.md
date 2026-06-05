# Getting Started — Developer Onboarding

Onboarding for new contributors to DINE (Directed Interactive Novel Engine). Covers the stack, how to run the app, and the conventions you must follow. Read the [glossary](./glossary.md) for the engine's domain vocabulary first; this guide is the *developer* entry point.

## 1. The stack (locked — ADR 0011)

| Layer | Choice |
|-------|--------|
| Backend | Laravel 13, PHP 8.4, pragmatic Service pattern |
| Frontend | Vue 3 + Inertia.js v3 (server-driven SPA) |
| Routing | **Wayfinder** typed routes — never Ziggy `route()` |
| UI | Tailwind 4 + shadcn-vue (`resources/js/components/ui/*`) |
| Tooling | pnpm + Vite |
| Database | MariaDB 11.7 (MySQL-8-compatible, JSON) |
| Auth | Laravel Fortify (passkeys available) via the Vue starter kit |

## 2. Run it locally

See the [local setup & boot diagnostics runbook](../runbooks/local-setup-diagnostics.md) for the full clean-clone sequence and troubleshooting. Short version:

```bash
cp .env.example .env   # set DB_* + DB_PASSWORD
composer install && pnpm install
php artisan key:generate && php artisan migrate
composer run dev       # app + queue + vite
```

Then open the app, click **Log in**, and sign in. New here? Create a user:

```bash
php artisan tinker --execute 'App\Models\User::factory()->create(["email" => "you@example.com"]);'
# password is "password"
```

## 3. Project conventions you must follow

- **Routing:** import typed helpers from `@/routes` / `@/actions` (Wayfinder). Never hardcode URLs or use Ziggy.
- **Standards:** time is stored UTC, rendered **Asia/Jakarta (WIB)**; money renders in **Rupiah (Rp)**. Use `useFormat()` from `resources/js/composables/useFormat.ts` (`formatDateTime` / `formatCurrency`) — do not call `Intl` ad-hoc.
- **UI/UX:** follow the `ui-ux-standards` rule — semantic tokens only (no hardcoded hex), four states (loading/empty/error/success), ≥44px touch targets, one primary action per view, confirm destructive actions, never native browser alerts.
- **PHP:** typed signatures, constructor property promotion, curly braces always; run `vendor/bin/pint --dirty` before committing.
- **Tests:** every change is programmatically tested (PHPUnit). Isolation/leak-guard stories require explicit negative tests. Run `php artisan test --compact`.
- **Docs:** follow [`DOCUMENTATION_STRUCTURE.md`](../DOCUMENTATION_STRUCTURE.md). Inline docstrings first (PHPDoc/JSDoc); `docs/` is for cross-file knowledge. Leave a note in [`PLACEHOLDER_TRACKING.md`](./PLACEHOLDER_TRACKING.md) for any divergence.

## 4. Where things live

```
app/Http/Middleware/HandleInertiaRequests.php   # shared Inertia props (auth, standards)
app/Providers/FortifyServiceProvider.php        # auth views, rate limiters
config/app.php                                   # timezone (UTC) + display_timezone/locale/currency
resources/js/pages/                             # Inertia page components (auth/, settings/, Dashboard, Welcome)
resources/js/composables/useFormat.ts           # WIB date + Rupiah formatting
routes/web.php, routes/settings.php             # web + settings routes
docs/                                            # ADRs, architecture, features, audits, runbooks (this folder's parent)
```

## 5. Auth & access model

Auth surfaces are the only public pages; everything else is behind the `auth` middleware. There is **no role hierarchy** — "multi-user" means account *isolation* (each owner sees only their own content), which is implemented in Sprint 2. After signing in you land on `/dashboard` (or your intended destination if you were redirected from a protected page).

See: [auth sign-in flow diagram](../architecture/Diagrams/App/Auth_Signin_Flow.md) · [auth API contract](../api/auth.md).

## 6. What exists today (Phase 1, Sprint 1)

The app boots, authenticates, and navigates — but has **no stories yet**. The narrative engine (characters, beats, narrator loop, delta engine, review gate) is designed in the [ADRs](../adr/README.md) and built across Phases 2–7. To understand the destination, read [ARCHITECTURE.md](../architecture/ARCHITECTURE.md).
