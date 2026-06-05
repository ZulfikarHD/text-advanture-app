# Local Setup & Boot Diagnostics

Operational playbook for booting DINE from a clean clone and triaging the most common foundation failures (database connection, assets, lint/build). Targets the Phase 1 / Sprint 1 stack (Laravel 13 + Inertia v3 + Vue 3 + Wayfinder + MariaDB 11.7).

## 1. Clean-clone boot (happy path)

```bash
cp .env.example .env          # then set DB_* and a real DB_PASSWORD (see §3)
composer install
pnpm install
php artisan key:generate      # APP_KEY — required for sessions/encryption
php artisan migrate            # against the mariadb connection
composer run dev               # serves app + queue + vite (or: php artisan serve & pnpm dev)
```

Success metric (Phase 1): a fresh setup boots without manual fixes. If it does not, work through the sections below.

## 2. Database engine

- Engine: **MariaDB 11.7** (MySQL-8-compatible, JSON columns). Default connection is `mariadb` (`config/database.php`).
- Dev database: `novel_engine`. Test database: `novel_engine_test` (configured in `phpunit.xml`).
- Both databases must exist and the `DB_USERNAME` must have privileges on them. Create them once:

```sql
CREATE DATABASE IF NOT EXISTS novel_engine CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS novel_engine_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON novel_engine.* TO 'your_user'@'%';
GRANT ALL PRIVILEGES ON novel_engine_test.* TO 'your_user'@'%';
FLUSH PRIVILEGES;
```

## 3. Connection failure triage

Symptom: a page errors instead of rendering, or `php artisan migrate` reports `SQLSTATE[HY000] [2002]` / `[1045]`.

| Error | Meaning | Fix |
|-------|---------|-----|
| `[2002] Connection refused` | DB server not reachable on `DB_HOST:DB_PORT` | Start MariaDB; confirm `ss -ltn \| grep 3306`; check `DB_HOST`/`DB_PORT` |
| `[1045] Access denied` | Wrong `DB_USERNAME`/`DB_PASSWORD` | Fix credentials in `.env`; quote passwords with special chars: `DB_PASSWORD="p4ss<word!"` |
| `[1049] Unknown database` | DB does not exist | Create it (§2) |
| Blank/500 with no detail | `APP_DEBUG=false` hiding the cause | In local, set `APP_DEBUG=true` to see the stack trace; never leave it true in production |

A connection failure surfaces Laravel's error page (detailed when `APP_DEBUG=true`, a generic styled error otherwise) — never a blank screen. After changing `.env`, run `php artisan config:clear`.

## 4. Verify the configured standards

```bash
php artisan tinker --execute 'echo config("app.timezone")."/".config("app.display_timezone")."/".config("app.currency").PHP_EOL;'
# expected: UTC/Asia/Jakarta/IDR
```

Times are stored in UTC and rendered in Asia/Jakarta; cost is rendered in Rupiah (via the `standards` shared prop + `resources/js/composables/useFormat.ts`).

## 5. Frontend not updating / Vite manifest error

- `Unable to locate file in Vite manifest` → assets not built. Run `pnpm dev` (HMR) or `pnpm build` (one-off).
- After changing routes, regenerate typed routes: `php artisan wayfinder:generate --with-form` (the Vite plugin also regenerates on dev).

## 6. Quality gate (must be clean for "done")

```bash
pnpm lint:check     # eslint, no errors
pnpm types:check    # vue-tsc, no errors
pnpm build          # production build succeeds (vueuse @__PURE__ warnings are harmless)
php artisan test --compact   # full suite, runs on novel_engine_test
vendor/bin/pint --dirty --format agent   # PHP formatting
```

## 7. Production checklist (deploy-time)

- `APP_ENV=production`, `APP_DEBUG=false`, a unique `APP_KEY` per environment (`php artisan key:generate`).
- `.env` is never committed (it is gitignored). Secrets (`APP_KEY`, `DB_PASSWORD`) live only in the environment.
- Password policy auto-strengthens in production (`AppServiceProvider`: min 12, mixed case, uncompromised).

## 8. Feature toggles & mail (Sprint 2)

- **Self-registration** is controlled by `REGISTRATION_ENABLED` (`.env`, default `true` → `config('app.registration_enabled')`). Set `REGISTRATION_ENABLED=false` for a single-author deployment: the `/register` page and POST return **404** and the "Sign up" links disappear, while existing users can still sign in. Run `php artisan config:clear` after changing it (and `config:cache` rebuilds on deploy).

```bash
php artisan tinker --execute 'var_dump(config("app.registration_enabled"));'
```

- **No mailer is required to sign in.** Email verification was removed in Sprint 2 (PH-10), so the default `MAIL_MAILER=log` is fine for local auth work; mail config only matters once a feature actually sends mail (e.g. password-reset links).

## Related

- [../architecture/ARCHITECTURE.md](../architecture/ARCHITECTURE.md) §11 — Application foundation
- [../guides/getting-started-onboarding.md](../guides/getting-started-onboarding.md) — developer onboarding
- [../security_logs/OWASP-audit-2026-06-05-s1-foundation-auth.md](../security_logs/OWASP-audit-2026-06-05-s1-foundation-auth.md) — security notes (debug/secret handling)
