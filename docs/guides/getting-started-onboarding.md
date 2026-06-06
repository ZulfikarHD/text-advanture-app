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
- **Standards:** time is stored UTC, rendered **Asia/Jakarta (WIB)**; money renders in **Rupiah (Rp)**. Use `useFormat()` from `resources/js/composables/useFormat.ts` (`formatDateTime` / `formatCurrency`) — do not call `Intl` ad-hoc. **Exception:** provider/LLM cost renders in **USD** (`formatUsdFromMicros`), because the OpenRouter balance is held in USD (PH-12).
- **UI/UX:** follow the `ui-ux-standards` rule — semantic tokens only (no hardcoded hex), four states (loading/empty/error/success), ≥44px touch targets, one primary action per view, confirm destructive actions, never native browser alerts.
- **PHP:** typed signatures, constructor property promotion, curly braces always; run `vendor/bin/pint --dirty` before committing.
- **Tests:** every change is programmatically tested (PHPUnit). Isolation/leak-guard stories require explicit negative tests. Run `php artisan test --compact`.
- **Docs:** follow [`DOCUMENTATION_STRUCTURE.md`](../DOCUMENTATION_STRUCTURE.md). Inline docstrings first (PHPDoc/JSDoc); `docs/` is for cross-file knowledge. Leave a note in [`PLACEHOLDER_TRACKING.md`](./PLACEHOLDER_TRACKING.md) for any divergence.

## 4. Where things live

```
app/Http/Middleware/HandleInertiaRequests.php   # shared Inertia props (auth, standards)
app/Providers/FortifyServiceProvider.php        # auth views, rate limiters
app/Models/                                      # User + authoring realm (Story, Chapter, Character, …)
app/Enums/                                       # backed enums mirrored by DB enums (ModelTier, OutlineStatus, …)
app/Policies/OwnerPolicy.php, StoryPolicy.php   # ownership authorization
config/app.php                                   # timezone (UTC) + display_timezone/locale/currency
resources/js/pages/                             # Inertia page components (auth/, settings/, Dashboard, Welcome)
resources/js/components/                         # EmptyState, ErrorState, ConfirmDialog, ui/* primitives
resources/js/composables/                        # useFormat (WIB/Rupiah), useAppearance (theme), useConfirm
routes/web.php, routes/settings.php             # web + settings routes
database/migrations/, database/factories/        # authoring-realm schema + factories
docs/                                            # ADRs, architecture, features, audits, runbooks (this folder's parent)
```

## 5. Auth & access model

Auth surfaces are the only public pages; everything else is behind the `auth` middleware. After signing in you land on `/dashboard` (the **Workspace**) — or your intended destination if you were redirected from a protected page. The sidebar surfaces **Workspace** + **Settings** (Play arrives in Phase 5).

- **Account isolation (Sprint 2 foundation, Sprint 3 first model).** There is **no role hierarchy** — "multi-user" means account *isolation* (each owner sees only their own content). Owned models adopt `App\Models\Concerns\BelongsToOwner` (applies `OwnerScope`, stamps `user_id` on create) and a policy extending `App\Policies\OwnerPolicy`. A foreign row is invisible (route-model binding → **404**); an out-of-scope row checked against the policy is **403**. **`Story` is the first real owned model** (Sprint 3: `BelongsToOwner` + `StoryPolicy`); authoring children (chapters, scenes, characters, …) carry no `user_id` and are isolated transitively through their story. Invariants are proven by `tests/Feature/Auth/OwnershipIsolationTest.php` (abstract foundation) and `tests/Feature/Authoring/StoryOwnershipTest.php` (the real model).
- **Registration toggle (Sprint 2).** `REGISTRATION_ENABLED` (`config('app.registration_enabled')`, default `true`) gates self-registration; when off, `/register` is 404 and the sign-up links hide via the shared `canRegister` prop. Sign-in is unaffected.
- **No email verification.** It was removed in Sprint 2 (it was a no-op) — no `verified` middleware and **no mailer needed to sign in**.

See: [auth sign-in flow diagram](../architecture/Diagrams/App/Auth_Signin_Flow.md) · [auth API contract](../api/auth.md) · [account & ownership contract](../api/account.md) · [ownership isolation diagram](../architecture/Diagrams/App/Account_Ownership_Isolation.md).

## 6. What exists today (Phase 1, Sprints 1–6)

The app boots, authenticates, isolates accounts, themes (light/dark/system, with a quick toggle in the user menu), and navigates the **Workspace + Settings** shell — but has **no story-authoring UI yet**. Sprint 2 added the account-isolation foundation, the registration toggle, and the shell; Sprint 3 added the theming/accessibility polish (skip-link, tokenized `Welcome`), reusable four-state components (`EmptyState`/`ErrorState`) plus a promise-based `useConfirm()` for destructive actions, and stood up the **authoring-realm schema** — 11 tables with enums, models, and factories, with `stories` as the first owner-scoped model. Sprint 4 completed the **persistence engine**: the 5 global libraries and 16 save-realm tables (the save root is `play_sessions`), with append-only audit tables guarded by the `AppendOnly` trait and `beat_true_states` kept structurally isolated from `beat_records.surface`; it also resolved the deferred FKs (PH-16) and shipped the first **Settings → Provider** screen for storing a per-account **encrypted** LLM key (`provider_credentials`). Sprint 5 built the **LLM client** behind the provider-agnostic `App\Contracts\Llm\LlmClient` interface (`OpenRouterClient`, thin `Http` wrapper with retry/backoff + structured-output validation; `laravel/ai` was removed) and three settings surfaces: a **connection test** on the Provider page (`useHttp`), a **Model roles** editor (global role→model mapping), and an owner-scoped **Usage** log (deferred prop, USD cost, WIB time) — but there is no engine caller yet. Sprint 6 **seeded the five global libraries** (universal priors, register + character archetypes, the 16-row prompt-block registry, and the 8 default `model_profiles` — so Model roles is no longer empty) via the idempotent `GlobalLibrarySeeder`, and built the **review-gate foundation**: a `ReviewGateService` state machine (propose → accept/edit/reject, recording who/when) behind a new top-level **Review** (`/reviews`) surface, owner-scoped by a nullable `review_items.user_id`. The gate's per-producer commit handlers are deferred to Phase 7 (PH-27), so today it shows an empty teaching state. **Sprint 7 (Phase 2 kickoff)** delivers the first **authoring CRUD**: the workspace dashboard now lists the owner's stories as a card grid, with **create** (Dialog from dashboard), **edit** (dedicated `/stories/{slug}/edit` page), and **delete** (useConfirm, FK cascade). The `stories.slug` constraint was relaxed from global unique to per-owner unique (`(user_id, slug)`), and slug derivation from title with auto-suffix on collision is centralised in `StoryService`. **E1.2** then wraps each story in a **workspace sub-nav** (**Overview · Details · Settings**, reached by opening a story card): **Overview** shows derived authoring counts + a play-readiness gate (both computed on read), and **Settings** edits the story's default POV (`stories.settings.default_pov`) and per-role model overrides (`model_profiles` scope=`story`, preferred over the global defaults). **E2.1** then grows that sub-nav into the **full authoring surface set** — **Overview · Characters · Structure · Lorebook · Settings · Saves · Details**; the four not-yet-built surfaces (Characters/Structure/Lorebook/Saves) render a reachable **`stories/ComingSoon`** teaching placeholder (`StoryPlaceholderController`) so the workspace shows its full shape without dead nav items, each repointed at its real controller when its feature ships (PH-30). The rest of the narrative engine (narrator loop, delta engine, full review commit) is designed in the [ADRs](../adr/README.md) and built across Phases 2–7. To understand the destination, read [ARCHITECTURE.md](../architecture/ARCHITECTURE.md).
