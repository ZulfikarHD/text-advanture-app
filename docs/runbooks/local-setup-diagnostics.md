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

## 9. Authoring-realm schema (Sprint 3)

Sprint 3 (S-4.1.1) migrated the 11 authoring tables (`stories`, `chapters`, `characters`, `scenes`, `beats`, `character_cards`, `reveal_ledger`, `lorebook_entries`, `registers`, `sensitivities`, `chapter_outlines`). They are part of the normal migration set, so the clean-clone `php artisan migrate` (§1) creates them — no extra step.

- **Apply just the new tables (incremental):** `php artisan migrate` — runs any not-yet-run authoring migrations.
- **Verify reversibility** (the DoD is "both realms migrate and roll back cleanly"):

```bash
php artisan migrate:fresh   # drops everything and re-runs all migrations
php artisan migrate:rollback # rolls back the last batch; children drop before parents (no FK error)
php artisan migrate          # re-apply
```

  This is also asserted automatically by `tests/Feature/Database/AuthoringRealmMigrationTest.php`.

- **Deferred FKs (PH-16) — now resolved in Sprint 4 (see §10).** Through Sprint 3 these were nullable columns without an FK; Sprint 4 added the constraints once `review_items` / `register_archetypes` existed.
- **`migrate:fresh` is blocked in production** (`DB::prohibitDestructiveCommands` in `AppServiceProvider`) — it only runs in local/testing.

## 10. Save realm & provider key (Sprint 4)

Sprint 4 (S-4.1.2 / S-4.2.x / S-5.1.x) migrated the 5 global libraries (`register_archetypes`, `universal_priors`, `character_archetypes`, `prompt_blocks`, `model_profiles`), the 16 save-realm tables, the PH-16 ALTER, and `provider_credentials`. All are part of the normal migration set — the clean-clone `php artisan migrate` (§1) creates them, no extra step.

- **The save "session" table is `play_sessions`** (PH-17). The framework reserves `sessions` for the database session driver, so the save root is `play_sessions` while child FK columns keep the spec name `session_id`. If `config('session.driver')` is `database`, expect both `sessions` (framework) and `play_sessions` (save realm) — that is intentional, not a duplicate.
- **PH-16 FKs are now enforced.** `registers.archetype_id → register_archetypes`, `character_cards.review_item_id → review_items`, `chapter_outlines.review_item_id → review_items`. Inserting a non-existent id now fails. Asserted by `tests/Feature/Database/DeferredForeignKeysTest.php`.
- **Full-schema reversibility** (both realms) is asserted by `tests/Feature/Database/SaveRealmMigrationTest.php`; structural isolation + append-only invariants by `SaveRealmSchemaTest` / `AppendOnlyInvariantTest`.
- **Provider key (no seeder, no `.env` key).** The LLM key is **not** an env var — each account stores its own at **Settings → Provider** (`provider_credentials`, encrypted at rest; PH-18 divergence from ADR 0017). Only the gateway URL is config:

```bash
php artisan config:show services.openrouter   # base_url default; .env: OPENROUTER_BASE_URL
```

  No key is needed to boot or run tests; the live `LlmClient` and connection test landed in Sprint 5 — see §11.

## 11. LLM client & connection test (Sprint 5)

Sprint 5 built the LLM client on top of the Sprint-4 schema ([ADR 0017](../../adr/0017-llm-orchestration-openrouter.md)). It is a backend subsystem with three settings surfaces (Provider connection test, Model roles, Usage); there is no engine caller yet.

- **`laravel/ai` was removed.** `composer remove laravel/ai` dropped the package; `config/ai.php` and the `…_create_agent_conversations_table` migration were deleted (the SDK had no per-request DB-key support). If you pull this change with an old DB, `agent_conversations` lingers as a stale table — `php artisan migrate:fresh` (local/test only) gives a clean schema. The SDK remains a future swap *behind* the `App\Contracts\Llm\LlmClient` interface.
- **The key comes from the DB, never `.env`.** `OpenRouterClient` authenticates with the **owner's** decrypted `provider_credentials` key (PH-18). Only the gateway URL + transport tunables are config:

```bash
php artisan config:show services.openrouter
# base_url, timeout, connect_timeout, max_retries, retry_base_delay_ms, log_messages
```

- **Transport tunables** (`.env`, all optional): `OPENROUTER_TIMEOUT` (60s), `OPENROUTER_CONNECT_TIMEOUT` (10s), `OPENROUTER_MAX_RETRIES` (2 → 3 attempts; retries 429/5xx + malformed structured output with exponential backoff), `OPENROUTER_RETRY_BASE_DELAY_MS` (250).
- **`OPENROUTER_LOG_MESSAGES`** (default `false`) is the debug gate (S-5.3.2): when on, full message bodies are persisted to `llm_calls.messages`. Leave it **off** outside debugging — `llm_calls` is as sensitive as the save realm (a prompt can embed a character's private state), and the column is `#[Hidden]` regardless.
- **Connection test** (Settings → Provider → "Test connection"): probes `GET {base_url}/models` with the owner's key. It validates the key only — it never writes `llm_calls`. Fully testable with `Http::fake()`.
- **Owner-scoped log:** `llm_calls` gained a nullable `user_id` (PH-20); the **Settings → Usage** screen renders the owner's calls (deferred prop, USD cost, WIB time).

## 12. Global libraries & review gate (Sprint 6)

Sprint 6 (S-6.1 / S-6.2) seeds the five global libraries and adds the review-gate foundation.

- **Seed the libraries** (idempotent — safe to re-run, keyed on natural keys):

```bash
php artisan db:seed --class=GlobalLibrarySeeder   # priors, register/character archetypes, prompt blocks, model profiles
php artisan db:seed                                # also runs the above, then the dev Test User (guarded against duplicates)
```

  Expected rows after seeding: 4 `universal_priors`, 4 `register_archetypes`, 1 `character_archetypes` (`koakuma`), 16 `prompt_blocks`, 8 `model_profiles` (one per `LlmRole`). Re-running changes nothing (`updateOrCreate`).

- **Model roles are now seeded.** Settings → Model roles is no longer empty on a fresh install — every role resolves to a default slug; the "mid" appraiser tier seeds `anthropic/claude-3.5-sonnet` (PH-26). Defaults stay author-editable.
- **Review gate** lives at the top-level **Review** sidebar entry (`/reviews`). With no producers yet it shows the empty teaching state; it is fully nav-reachable (no URL typing).
- **Test-database guard (safety).** `tests/TestCase.php::guardTestDatabase()` aborts the suite before any migration unless the resolved DB name contains `test` (or is `:memory:`). Since `RefreshDatabase` wipes its connection, a misconfigured `DB_DATABASE=novel_engine` fails fast instead of destroying dev data:

```
RuntimeException: Refusing to run tests against database [novel_engine]: it is not a test database.
```

  Fix by pointing `DB_DATABASE` at `novel_engine_test` (already pinned in `phpunit.xml`).

- **Story slug collision (Sprint 7).** The `stories` table uses a **per-owner unique** slug (`unique (user_id, slug)`). If you see a `UNIQUE constraint` error when creating a story, the slug already exists for that owner. When slug is omitted the service derives one from the title and auto-suffixes (`-2`, `-3`, …) on collision. If you supply an explicit slug that collides, the form request rejects it with a validation error. Two different owners *can* hold the same slug — the constraint is not global.

## Related

- [../architecture/ARCHITECTURE.md](../architecture/ARCHITECTURE.md) §11 — Application foundation
- [../guides/getting-started-onboarding.md](../guides/getting-started-onboarding.md) — developer onboarding
- [../security_logs/OWASP-audit-2026-06-05-s1-foundation-auth.md](../security_logs/OWASP-audit-2026-06-05-s1-foundation-auth.md) — security notes (debug/secret handling)
