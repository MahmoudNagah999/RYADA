# RYADA - Project Context & Roadmap (v1.0)

> Paste this file at the start of a new chat so Claude can continue from here.
> Scope: **v1.0 only** (multi-sport clubs + player subscriptions). Karate-specific work and tournaments live in `RYADA-roadmap-v2.0.md`; paste that file too only when working on v2.0.

## Who / how to work with me
- I'm Mahmoud, a senior backend developer (Laravel/PHP).
- Reply in **Egyptian Arabic** mixed with technical English terms. Keep answers concise, direct, practical.
- Work **step by step**: give me one step, wait until I confirm it works, then move on.
- Give **complete, copy-pasteable code blocks** (not patches or diffs).
- Environment: **Windows + Laravel Herd + PowerShell** (project path `C:\Users\Ru\Herd\RYADA`). Adapt any bash commands.
- PowerShell scripts need `powershell -ExecutionPolicy Bypass -File .\script.ps1` (execution policy is restricted). `gh` CLI is installed and logged in.
- Preferred code style: business logic in **Action classes with DTOs**, `DB::transaction` for multi-step operations, Form Requests, API Resources, Policies. Actions expose a single `execute()` method.
- PR flow: `git add .` + commit + push, write the PR description to `pr-body.md` (`notepad pr-body.md`), then `gh pr create --title "..." --body-file pr-body.md --label ...`, then `Remove-Item pr-body.md`. Always give me the PR description.
- **Naming (from #9):** the issue number goes in the branch name and the PR title. Branch: `<type>/<issue>-<slug>` (e.g. `feat/10-api-conventions`, created with `gh issue develop 10 --name feat/10-api-conventions --checkout`). PR title and commit: `feat(api): add API conventions (#10)`. Keep `Closes #N` in the PR body.

## Goal
My GitHub repos are all tutorial-level. I'm building one strong showcase project, **from scratch**, to show senior-level Laravel skills.

**RYADA**: a multi-tenant SaaS for managing sports clubs and academies. **v1.0:** several sports per club, players, staff, training, and player subscriptions (membership plans, subscriptions, payments). **v2.0:** karate module and tournaments.
Repo: `github.com/MahmoudNagah999/RYADA`

## Decisions made
- **Scope split v1.0 / v2.0 (decided 2026-10-28):**
  - **v1.0 = multi-sport club management with player subscriptions.** A club offers one or more sports; players are enrolled in sports; membership plans, player subscriptions and payments; training groups and attendance; staff; the SaaS side (plans, admin panel, audit log); Redis, notifications and scheduled jobs; quality and release.
  - **v2.0 = everything karate-specific and tournaments:** belts, age categories and weight classes, tournaments and registration, kumite brackets, kata scoring, results and certificates, live scoreboard (Reverb). Plan in `RYADA-roadmap-v2.0.md`; GitHub milestones `V2-M1` to `V2-M4`.
  - In v1.0 karate is just one more sport in the catalog (#19): no belts, no weight classes, no tournaments. `Player` has no belt or weight (#18).
  - Already built and kept: roles (`referee`) and permissions (`tournaments.*`, `scores.submit`) from #7, and the empty `Tournament` domain folder. Nothing is removed; they are used from v2.0.
  - Design first: ADR-007 (#56) decides the multi-sport model before #18 to #21 are built. Sport-specific rules for v2.0 are designed in ADR-008 (#59), not now.
  - Naming: club membership plans are `MembershipPlan` (#20), never `Plan`, which is the SaaS plan of the platform (#13).
  - Issue numbers: #23 to #28 and #30 moved to v2.0; #19 and #20 were rewritten; #56, #57 and #58 were added to v1.0 and #59 to #61 to v2.0.
- **Stack:** Laravel 13 (PHP 8.4), MySQL, REST APIs, Pest, Sanctum. Redis only where genuinely needed (Milestone 6). Reverb comes with the live scoreboard in v2.0.
- **Admin panel:** Filament, for the super-admin (platform owner). Separate guard, no auth sharing with the API.
- **Club dashboard:** API only (`/api/v1`), consumed by a mobile/web client (Flutter dev collaborates with me).
- **Tenancy (ADR-001):** single database, `club_id` column + global scope via a `BelongsToClub` trait. Mitigations: every tenant-owned model uses the trait, mandatory tenant isolation tests (#9), no `withoutGlobalScopes()` in club-facing code, composite indexes start with `club_id`, per-club unique constraints, tenant carried explicitly in jobs / cache keys / channels.
  - **Implemented in #5:** `App\Domain\Shared\Tenancy\{CurrentClub, ClubScope, BelongsToClub}`.
    - `CurrentClub` holds the current tenant, registered as `scoped` in `AppServiceProvider` (fresh per request / queue job). `runAs($club, fn)` runs a callback in a club context and restores the previous one (use it in jobs, seeders, commands, tests, and in Actions that receive a `Club`).
    - `ClubScope` is **fail-closed**: no current club means `WHERE 0 = 1` (zero rows), never all rows.
    - `BelongsToClub` auto-fills `club_id` on create from `CurrentClub`; creating with no current club and no explicit `club_id` throws `LogicException` (no orphan rows). Also provides the `club()` relation.
    - `Club` itself does NOT use the trait (it is the tenant).
    - **Implemented in #8:** `App\Http\Middleware\ResolveCurrentClub` (alias `tenant`), runs after `auth:sanctum`. It sets `CurrentClub` **and** `setPermissionsTeamId($club->id)` from the authenticated user, drops preloaded `roles` / `permissions` relations, and restores the previous values in `finally` (no state leaks between requests, tests or Octane). A user without a club gets `403` with code `tenant.no_club`.
    - Convention: every club-scoped route goes inside the `tenant` group, after `auth:sanctum`. `logout` stays outside it on purpose (any valid token can log out).
    - In jobs, seeders, commands and tests, context is set with `CurrentClub::runAs()` / `set()` (and `setPermissionsTeamId`).
  - **Verified in #9:** `tests/Support/Tenancy/TenantFixture::create(RoleName $role = Owner)` returns a club, a user with that role and a token (use two to test isolation). `TenantIsolationTest` covers cross-club access. `RouteTenancyTest` fails if any `/api` route lacks `auth:sanctum` followed by `tenant`, unless it is in the allowlists inside that test (`TENANT_PUBLIC_ROUTES`: login, register-club; `TENANT_AUTH_ONLY_ROUTES`: logout); a new allowlist entry needs a reason in the PR. `tests/Unit/TenancyRulesTest.php` fails if `withoutGlobalScope(s)` appears in `app/Http` or `app/Domain` (outside `Shared/Tenancy`).
- **Project structure (ADR-002):** domain folders inside `app/`:
  ```
  app/
    Domain/<Domain>/{Actions,DTOs,Enums,Models,Policies}
    Domain/Shared/            # cross-domain pieces (BelongsToClub goes here)
    Http/{Controllers/Api/V1,Errors,Middleware,Requests,Resources}
  ```
  - Domain code never depends on `App\Http` (enforced by a Pest arch test).
  - Policies and factories are registered explicitly with `#[UsePolicy]` / `#[UseFactory]` on the model (auto-discovery does not look inside domain folders).
  - A domain folder is created with its first issue, not in advance. Existing: `Shared`, `Club`, `Player`, `Tournament`, `Auth` (created in #6), `Subscription`, `Audit`, `Sport` (created in #19), `Membership` (created in #20). `Tournament` stays empty until v2.0.
  - `User` stays in `App\Models` (Laravel default, `config/auth.php` points to it).
- **Auth (#6):**
  - Sanctum personal access tokens. Endpoints under `/api/v1/auth`: `POST login`, `POST register-club` (public), `POST logout`, `GET me` (authenticated).
  - `User` does **not** use `BelongsToClub`: at login there is no current club yet and the fail-closed scope would return no rows. Queries that list a club's users must filter by `club_id` explicitly (covered by #9).
  - `users.club_id` is required (FK, cascade delete) and **not mass-assignable**; Actions associate the club explicitly.
  - Email is unique platform-wide (one user belongs to one club).
  - Wrong password and unknown email return the same `401` with code `auth.invalid_credentials` (no user enumeration).
  - **Token abilities:** `read` / `write` (`App\Domain\Auth\Enums\TokenAbility`). If `abilities` is omitted at login, the token is `['*']`. Abilities cap what a token can do; real permissions come from roles and policies (#7). Middleware aliases in `bootstrap/app.php`: `abilities` (all of) and `ability` (any of). Convention: read routes use `ability:read,write`, write routes use `abilities:write`. Logout is allowed for any valid token.
  - **Register club:** `RegisterClubAction` creates Club + first user in `DB::transaction`, generates a unique slug (Arabic names supported, falls back to `club`), user locale defaults to the locale resolved by `SetLocale`, returns a token. It also calls `SyncClubRolesAction` and assigns the Owner role to the first user in the same transaction (#7).
  - `SetLocale` runs before `auth:sanctum`, so it resolves the user through `$request->user('sanctum')` (covered by a test).
- **RBAC (ADR-004, #7):** `spatie/laravel-permission` in Teams mode (`teams => true`, `team_foreign_key => club_id` in `config/permission.php`, set **before** running the migration). Roles: `owner`, `coach`, `staff`, `referee` (`App\Domain\Auth\Enums\RoleName`).
  - **Roles are per club, permissions are global.** Permissions are defined in code (`PermissionName`: `club.view`, `club.update`, `staff.view`, `staff.manage`, `players.view`, `players.manage`, `tournaments.view`, `tournaments.manage`, `scores.submit`, `membership_plans.view`, `membership_plans.manage`) and mapped to roles in `RoleName::permissions()`. Owner has all. Coach: club.view, players.view/manage, tournaments.view, membership_plans.view. Staff: club.view, staff.view, players.view/manage, tournaments.view/manage, membership_plans.view/manage. Referee: club.view, tournaments.view, scores.submit.
  - `SyncClubRolesAction::execute(Club)` creates the club's four roles and syncs their permissions (idempotent, passes `club_id` explicitly). `RegisterClubAction` calls it, then assigns Owner (sets the team id and restores the previous one). To add a permission: edit the two enums, then re-run the action for existing clubs (migration or command).
  - `User` uses `HasRoles`. The current team must be set before any role check: the `tenant` middleware does it per request; Actions, jobs, seeders and tests set it explicitly and restore it.
  - **Policies check two things:** same club **and** permission (`checkPermissionTo`, which returns false instead of throwing for an unknown permission). Registered with `#[UsePolicy]`. Existing: `ClubPolicy` (view, update), `StaffPolicy`, `PlayerPolicy`, `MembershipPlanPolicy` (viewAny, view, create, update). Each later resource ships its own policy with its first issue.
  - **Always authorize against the model** (`$this->authorize('update', $club)`), never `can('club.update')`: spatie registers permissions as Gate abilities, and a bare permission string skips the club check.
  - Token abilities (`read` / `write`) only cap a token; real permissions come from roles and policies.
- **Localization (ADR-003, Arabic + English):**
  - Locale resolved by the `SetLocale` middleware (**global** middleware in `bootstrap/app.php`, so it also covers 401/404/405 responses thrown before route middleware): `Accept-Language` header > `users.locale` > default `en`. Supported locales in `config/locales.php`. Response carries `Content-Language`.
  - Clients must send the user's chosen language in `Accept-Language` on every request (the header wins over `users.locale`).
  - Static strings: `lang/en` and `lang/ar` with `__()`; Arabic framework translations via `laravel-lang/common` (dev dependency, files are committed). Pint excludes `lang/`.
  - Stored reference data (sports, plans; belts and categories come with the karate module in v2.0): `spatie/laravel-translatable` (JSON columns). Applied to `Plan` (#13) and `Sport` (#19). Player and club names are stored as entered, not translated.
  - API errors: Actions throw `App\Domain\Shared\Exceptions\BusinessRuleException(code, params, status)`; `App\Http\Errors\ApiExceptionRenderer` (called from `bootstrap/app.php`) returns `{"code": "player.limit_reached", "message": "..."}` with the message from `lang/*/errors.php` (key `errors.<code>`, **flat** keys like `'auth.invalid_credentials'`; a key nested inside another array never resolves). Clients branch on `code`, never on `message`.
  - API returns dates as ISO 8601 UTC; formatting is done by the client.
  - Filament admin gets Arabic + RTL; admin choice persisted. (**Not done yet**, see Known gaps in Current status.)
  - Later impact: PDF certificates need an Arabic-capable library (mPDF or Browsershot, not DomPDF); notifications/mails go out in the recipient's locale.
  - **Definition of Done (every issue):** any user-facing string is a translation key present in both `en` and `ar`. `tests/Unit/LangFilesTest.php` enforces matching files/keys (and placeholders for `errors`); hardcoded strings are caught by code review only. Validation attribute names (e.g. `club_name`, `sport_id`) go in `validation.php` `attributes` in both languages.
- **API conventions (ADR-005, #10):**
  - Versioning: every route is under `/api/vN/`. `routes/api.php` only includes one file per version (`routes/api/v1.php`, prefix `v1`, `throttle:api`). A test fails for unversioned API routes.
  - Success: API Resources wrapped in `data`; extra info in `meta` (e.g. the login token).
  - Errors: every error under `api/*` is `{"code", "message"}` (422 adds `errors`), rendered by `App\Http\Errors\ApiExceptionRenderer`. Codes: `http.unauthenticated` (401), `http.forbidden` (403), `http.not_found` (404), `http.method_not_allowed` (405), `validation.failed` (422), `http.too_many_requests` (429, with `Retry-After`), `server.error` (500, no internals unless `APP_DEBUG=true`), plus domain codes from `BusinessRuleException`. Every code needs a flat key in both `lang/en/errors.php` and `lang/ar/errors.php`.
  - Rate limiting (limiters in `AppServiceProvider`): `api` on every route (120/min per user, 60/min per IP for guests), `auth` on login (5/min per email+IP, 20/min per IP), `register` on register-club (10/hour per IP). A test fails if an API route misses `throttle:api`. A new abusable public endpoint needs its own limiter.
- **Audit log (ADR-006, #15):**
  - One entry point: `RecordActivityAction::execute(ActivityEvent $event, ?Model $subject, array $properties, ?int $clubId, ?Model $causer)`. Actions never call `activity()` directly. It takes `CurrentClub` in its constructor, so Actions that use it are resolved from the container (`app()` or injection), never created with `new`.
  - Events are the `ActivityEvent` enum. The key (e.g. `club.suspended`) is stored in `description` and `event`; the log name is the part before the dot. The text shown in the panel is translated at display time (`audit.events.<key>` in `lang/{en,ar}/admin.php`), so stored rows never depend on a locale.
  - The log is written **inside the same `DB::transaction`** as the change. Idempotent no-ops (suspending a suspended club, saving a plan with no changes, attaching a sport already attached) are not logged.
  - `club_id`: the subject's id when it is a `Club`, otherwise `CurrentClub`, otherwise passed explicitly. `null` for platform-level events (plans, sports). `nullOnDelete`, so the log survives a deleted club.
  - Causer is resolved explicitly: `admin` guard first, then `sanctum` (Filament does not use the default guard). Public registration has no causer.
  - `Activity` does **not** use `BelongsToClub`: the super-admin reads all clubs. A club-facing audit view would need its own scoped query.
  - Properties hold `from` / `to` for changed fields and ids / slugs for references. Never passwords, tokens or national IDs.
  - **Definition of Done (every issue):** a new sensitive operation = one `ActivityEvent` case + one `RecordActivityAction` call inside the Action's transaction + the event key in `en` and `ar` (`LangFilesTest` enforces matching keys).
  - Plan changes made outside `CreatePlanAction` / `UpdatePlanAction` are not logged, so the Filament plan pages must keep going through them. Same for sports (`CreateSportAction` / `UpdateSportAction`). The admin resource is read-only and append-only.
- **Process:** every issue = branch + PR + Conventional Commits (`feat(player): ...`), with the issue number in the branch name and the PR title. Roadmap lives as GitHub Issues and Milestones. Branch ruleset `Protect main` is active (PR required, checks `Pint & Larastan` and `Pest`, up-to-date branch, no force push, no deletion, no bypass).

## Current status
- GitHub labels, milestones and issues #1 to #39 exist in the RYADA repo.
- **Milestone 1 (Foundation) is complete and merged:** #1, #2, #3, #4, #39.
  - **#1:** Laravel + MySQL + Pest, Pint (`pint.json`, Laravel preset, excludes `lang/`), Larastan (`phpstan.neon`, level 5 on `app/`). Composer scripts `lint`, `analyse`, `check` (`check` also runs the tests).
  - **#2:** CI with jobs `Pint & Larastan` and `Pest` (PHP 8.4, MySQL 8.0 service) + `Protect main` ruleset.
  - **#3:** `docs/adr/001-tenancy-strategy.md`.
  - **#4:** domain folders, `docs/adr/002-project-structure.md`, arch tests in `tests/Unit/ArchTest.php`, README skeleton with Mermaid diagram.
  - **#39:** `SetLocale` middleware, `users.locale` column, `config/locales.php`, `BusinessRuleException` + error format, `lang/en` + `lang/ar` (+ `errors.php`), `docs/adr/003-localization.md`, README localization section. Tests: `SetLocaleTest`, `ErrorFormatTest`, `LangFilesTest`.
- **Milestone 2 (Tenancy & Auth) is complete and merged:** #5, #6, #7, #8, #9, #10.
  - **#5:** `clubs` table (`name`, unique `slug`), `Club` model + `ClubFactory` (in `database/factories`, registered with `#[UseFactory]`), `CurrentClub`, `ClubScope`, `BelongsToClub`. Tests in `tests/Feature/Tenancy/BelongsToClubTest.php` use a temporary table and a test-only model `tests/Support/Tenancy/TenantNote`.
  - **#6:** Sanctum, `users.club_id`, `User` with `HasApiTokens` + `club()`, `UserFactory` (`club_id => Club::factory()`, plus `forClub()` state), `LoginAction` / `RegisterClubAction` with DTOs (`LoginData`, `LoginResult`, `RegisterClubData`), `LoginRequest` / `RegisterClubRequest`, `UserResource` / `ClubResource`, `AuthController` + `RegisterClubController`, token abilities. Tests: `AuthTest`, `RegisterClubTest`.
  - **#7:** `spatie/laravel-permission` (Teams), `RoleName` / `PermissionName` enums, `SyncClubRolesAction`, Owner assigned in `RegisterClubAction`, `User` uses `HasRoles`, `ClubPolicy`, `docs/adr/004-roles-and-permissions.md`, README section. Tests: `SyncClubRolesTest`, `ClubPolicyTest`, extended `RegisterClubTest`.
  - **#8:** `ResolveCurrentClub` middleware (alias `tenant`), `errors.tenant.no_club`, `me` moved into the `tenant` group. Test: `ResolveCurrentClubTest` (probe route registered inside the test). Also fixed `lang/*/errors.php`: `auth.invalid_credentials` was nested inside `player` and never resolved; keys are flat now.
  - **#9:** `TenantFixture`, `TenantIsolationTest`, `RouteTenancyTest`, `TenancyRulesTest` (see Tenancy above).
  - **#10:** versioned routes (`routes/api/v1.php`), `ApiExceptionRenderer`, `SetLocale` made global, three rate limiters, `docs/adr/005-api-conventions.md`, README section. Tests: `ApiVersioningTest`, `ApiErrorsTest`, `RateLimitingTest`.
  - ADRs so far: 001 tenancy, 002 structure, 003 localization, 004 roles and permissions, 005 API conventions, 006 audit log, 007 multi-sport.
- **Milestone 3 (Admin Panel) is complete and merged:** #11, #12, #13, #14, #15.
  - **#11:** `filament/filament` v5.10.3 (Livewire v4.4.8). Panel `admin` at `/admin` (`app/Providers/Filament/AdminPanelProvider.php`, `->authGuard('admin')`). New `admins` table, `App\Models\Admin` (implements `FilamentUser`, `canAccessPanel()` only for panel `admin`), `AdminFactory`, `admin` session guard + `admins` provider in `config/auth.php`. No relation to `users`, `clubs`, Sanctum or club roles. Create a super-admin with `php artisan admin:create` (validated, prompts for the password). Tests: `AdminPanelAccessTest` (guest, admin, club user session and API token cannot open `/admin`, an admin session gets `401 http.unauthenticated` on the API).
  - **#12:** `clubs.status` (`App\Domain\Club\Enums\ClubStatus`: `active` / `suspended`, indexed) and `suspended_at`. `status` is **not** mass-assignable: it only changes through `SuspendClubAction` / `ActivateClubAction` (idempotent). `CreateClubAction` (club + unique slug + `SyncClubRolesAction`, in one transaction) was extracted from `RegisterClubAction`, so the API registration and the admin panel share it. `Club::users()` relation. Filament `ClubResource` in `app/Filament/Resources/Clubs/` (`Schemas`, `Tables`, `Pages`, `Actions/ClubStatusActions`, `RelationManagers/UsersRelationManager`, read-only): list, create, view, edit, suspend / activate with confirmation, status filter. **Clubs cannot be deleted from the panel** (cascade would delete their users). Strings in `lang/{en,ar}/admin.php`. Tests: `ClubStatusTest`, `CreateClubActionTest`, `ClubResourceTest`, `ClubUsersRelationManagerTest`.
  - **#13:** new domain `App\Domain\Subscription` with `Plan` (`slug` unique, translatable `name` ar/en, `max_players` / `max_coaches` where **null = unlimited**, `price_cents` + `currency` default EGP, `duration_months`, `is_active`) and `PlanFactory`. `clubs.plan_id` (FK, `restrictOnDelete`), `subscription_starts_at`, `subscription_ends_at` (indexed). `AssignPlanToClubAction` computes the end date with `addMonthsNoOverflow` and rejects an inactive plan (`plan.inactive`, 422). Filament `PlanResource` (list, create, edit; price typed in EGP and stored in cents; **no delete**, deactivate with `is_active`) and an `Assign plan` action on the clubs table and view page. Tests: `AssignPlanToClubActionTest`, `PlanResourceTest`, `ClubPlanActionTest`.
  - **#14:** `App\Http\Middleware\EnsureClubSubscriptionActive` (alias `subscription`, runs after `tenant`): suspended club gets `403 club.suspended`, expired subscription gets `402 subscription.expired`, suspension is reported first. `routes/api/v1.php` now has a nested `subscription` group inside `tenant`: **every club-scoped route goes there**. `GET /auth/me` is outside it on purpose (a suspended or expired club must still read its status). `RouteTenancyTest` fails when a club-scoped route lacks `subscription` after `tenant`, unless it is in `SUBSCRIPTION_EXEMPT_ROUTES` (reason needed in the PR). Plan limits: `PlanLimit` enum (`players`, `coaches`), `Plan::limitFor()`, `EnsureWithinPlanLimitAction::execute(Club, PlanLimit, currentCount, adding = 1)` throws `403 plan.players_limit_reached` / `plan.coaches_limit_reached` (message has `:max`). Called from `CreatePlayerAction` (#18) and the coach invitation / promotion flow (#17), with the count and the insert inside `DB::transaction` and a `lockForUpdate` on the club row. Tests: `SubscriptionEnforcementTest`, `EnsureWithinPlanLimitActionTest`, extended `RouteTenancyTest`.
  - **Decision (#14):** a club with no plan, a plan limit of `null`, or a null `subscription_ends_at` is **not restricted**. New clubs register without a plan, so blocking them would lock them out on day one. Revisit with the trial plan (Post-v1.0 backlog below).
  - **#15:** audit log with `spatie/laravel-activitylog` (ADR-006). New domain `App\Domain\Audit` (`Actions/RecordActivityAction`, `Enums/ActivityEvent`, `Models/Activity`); our own model is set in `activity_model` of `config/activitylog.php`. A migration adds `activity_log.club_id` (nullable FK, `nullOnDelete`, composite index with `created_at`). Events recorded: `club.created` (inside `CreateClubAction`, so register-club and the admin panel both log it), `club.suspended`, `club.activated`, `club.plan_assigned`, `plan.created`, `plan.updated`. `SuspendClubAction`, `ActivateClubAction`, `AssignPlanToClubAction` and `CreateClubAction` take `RecordActivityAction` in the constructor and write the log inside a `DB::transaction`. `CreatePlanAction` / `UpdatePlanAction` with the `PlanData` DTO (`fromArray`, `fromPlan`, `toAttributes`); the Filament `CreatePlan` / `EditPlan` pages call them from `handleRecordCreation` / `handleRecordUpdate`. Filament `ActivityResource` in `app/Filament/Resources/Activities/` (list only, filters by event and club, no create / edit / delete). Strings in `lang/{en,ar}/admin.php` under `audit`. Tests: `ActivityLogTest`, `PlanActivityTest`, `ActivityResourceTest`. Not done: retention / pruning of old logs.
  - **Known gaps (not bugs):** the admin panel has no Arabic / RTL or locale switcher yet (ADR-003 promised it; strings already exist in `lang/*/admin.php`); a club created from the panel has its roles but **no Owner**, so nobody can log in to it until a user is added; assigning a plan always starts a new subscription from the chosen date (no renewal from the current end date); no plans are seeded.
- **Milestone 4 (Club API Core) in progress.** Done: #16, #17, #56, #18, #19, #20. Next, in this order: #21, #22; then M5 player subscriptions (#57, #58). **#20 PR is open: confirm it is merged before starting #21.** Suite: 343 tests green at the end of #20 Step 4 (Step 5 adds 3 detach tests).
  - **#16:** migration adds `logo_path`, `contact_email`, `contact_phone`, `city`, `address` to `clubs` (all nullable). Endpoints (inside `auth:sanctum` + `tenant` + `subscription`): `GET /api/v1/club` (`ClubPolicy::view`, `ability:read,write`), `PUT /api/v1/club`, `POST /api/v1/club/logo` (multipart field `logo`), `DELETE /api/v1/club/logo` (all `ClubPolicy::update`, `abilities:write`). Domain: `UpdateClubProfileData` (DTO), `UpdateClubProfileAction`, `UpdateClubLogoAction`, `RemoveClubLogoAction`. HTTP: `Requests/Club/{UpdateClubProfileRequest,UpdateClubLogoRequest}`, `ClubController`, `ClubLogoController` (`Controllers/Api/V1/Club/`), `ClubResource` returns the contact fields and `logo_url`. Audit events: `club.profile_updated` (only changed fields, `changes.<field>.from/to`), `club.logo_updated`, `club.logo_removed`. Tests: `ClubProfileTest`, `ClubLogoTest`, `UpdateClubProfileActionTest`.
  - **Decisions (#16):** `PUT` replaces the profile (optional fields sent as `null` are cleared). `slug` never changes with the name (stable URLs). `slug`, `status` and `logo_path` are not editable through the profile `PUT`; `logo_path` is not mass-assignable and changes only through the logo Actions. Logo: jpg / jpeg / png / webp, max 2 MB, **no SVG** (can carry scripts); stored under `clubs/{id}/logo/` with a Laravel-generated name. Disk comes from `config('filesystems.club_logo_disk')` (`CLUB_LOGO_DISK`, default `public`) so the logo can move to S3 with Docker (#36) with no code change. The logo file is stored first, the DB is updated in a transaction, the old file is deleted after the commit, and the new file is deleted if the DB write fails.
  - **#17:** Staff management and invitations. Owners can list staff, invite, revoke invitations, change roles, and remove staff. Staff can view staff and invitations but cannot manage them; Coaches and Referees cannot access staff endpoints. Invitations use hashed tokens, localized email, expiry, and one-time acceptance. Coach plan limits are checked during acceptance and promotion. Audit events cover invitations, joins, role changes, and removals. Tests: `StaffActionsTest`, `StaffInvitationTest`, `StaffManagementTest`, `StaffPolicyTest`.
  - **Decisions (#17):** Only Owners manage staff; Staff has `staff.view` only, while Coach and Referee have no `staff.*` permissions. Creating a replacement invitation automatically revokes and audits an expired pending invitation; an unexpired duplicate is rejected. Staff listing defaults to 25 results per page, caps page size at 100, and sorts by name ascending. Only users with the Coach role count toward the coach plan limit; Staff and Referees do not.
  - **#56:** `docs/adr/007-multi-sport.md` (ADR-007) written with five decisions: (1) club and player are each many-to-many with sports (`club_sport`, `player_sport`, both tenant-owned, unique per club); a sport cannot be removed from a club while it still has enrollments, active membership plans or training groups; (2) `Sport` is a global catalog (`slug`, translatable ar/en `name`, `is_active`, no delete), managed by the super-admin in Filament, not using `BelongsToClub`; (3) `MembershipPlan` and `TrainingGroup` have a required `sport_id`; `PlayerSubscription` has no `sport_id` of its own (the sport comes from its plan) and snapshots price, currency and duration; (4) SaaS plan limits stay per club and count a player once (`COUNT(players)`, never `COUNT(player_sport)`, with an explicit test in #18); `EnsureWithinPlanLimitAction` and `PlanLimit` do not change; (5) sport-specific rules (belts, weight, categories) live in separate per-sport domains linked by `Sport.slug` (for example `App\Domain\Karate` in v2.0); the module registry design is left to ADR-008 (#59).
  - **#18:** `players` table (`club_id`, `name`, nullable `national_id`, `birth_date`, `gender`, nullable `phone`; unique `(club_id, national_id)`, indexes `(club_id, name)` and `(club_id, birth_date)`). Domain `App\Domain\Player`: `Player` (uses `BelongsToClub`, `club_id` not mass-assignable, `age()`), `Gender` enum, `PlayerFactory`, DTOs (`NationalIdInfo`, `CreatePlayerData`, `UpdatePlayerData`, `ListPlayersData`), Actions (`ParseEgyptianNationalIdAction`, `ResolvePlayerBirthDataAction`, `CreatePlayerAction`, `UpdatePlayerAction`, `DeletePlayerAction`, `ListPlayersAction`), `PlayerPolicy` (same club + `players.view` / `players.manage`). HTTP: `Rules/EgyptianNationalId`, `Requests/Player/{PlayerFormRequest,StorePlayerRequest,UpdatePlayerRequest,ListPlayersRequest}`, `PlayerResource`, `PlayerController`. Endpoints inside `auth:sanctum` + `tenant` + `subscription`: `GET /api/v1/players` (filters `search`, `gender`, `age_min`, `age_max`; `sort` = `name` / `birth_date` / `created_at` with `-` for descending; `per_page` default 25, max 100), `POST /players`, `GET /players/{player}`, `PUT /players/{player}`, `DELETE /players/{player}`. Audit events: `player.created`, `player.updated`, `player.deleted`. Error codes: `player.national_id_invalid`, `player.national_id_mismatch`, `player.national_id_taken`, `player.birth_data_required`. Tests: `ParseEgyptianNationalIdActionTest`, `EgyptianNationalIdRuleTest`, `CreatePlayerActionTest`, `PlayerApiTest`, `PlayerManagementTest`.
  - **Decisions (#18):** `national_id` is nullable (many youth players have none); when given, birth date and gender are derived from it and a conflicting value is rejected (`player.national_id_mismatch`); without it, `birth_date` and `gender` are required. The governorate code and the check digit are not verified (the algorithm is not officially documented). Arabic-Indic digits are rejected. `PUT` replaces the player. The plan limit is checked in `CreatePlayerAction` inside a transaction with a lock on the club row, and counts `COUNT(players)` (a player in several sports counts once); **this plan-limit approach is accepted for now and may change later** (it lives in one line of `CreatePlayerAction` plus `PlanLimit`). National ID and phone are never written to the audit log (field names only, `sensitive_fields_changed`). `DELETE` is a hard delete for now: #19 made it cascade the player's enrollments; #57 must block deleting a player with subscriptions. Authorization of players is in the FormRequest `authorize()`, so a user without permission gets `403` before `422`.
  - **#19 (sports catalog & player enrollment, merged):** new domain `App\Domain\Sport`.
    - **Catalog:** `sports` table (`slug` unique, translatable `name` ar/en, `is_active`, indexed), `Sport` (no `BelongsToClub`, `#[UseFactory]`), `SportFactory` (with `inactive()`), idempotent `SportSeeder` (10 sports: karate, football, swimming, basketball, volleyball, handball, tennis, judo, taekwondo, gymnastics; `updateOrCreate` on slug; called from `DatabaseSeeder`). `SportData` DTO, `CreateSportAction` / `UpdateSportAction` (audit in the same transaction; the update ignores any slug change and logs only real changes under `changes.<field>.from/to`). Filament `SportResource` in `app/Filament/Resources/Sports/` (list, create, edit, filter by `is_active`, search on both names; no delete; slug is `disabledOn('edit')` and validated `^[a-z0-9]+(-[a-z0-9]+)*$`, unique). `EditSport` fills both translations in `mutateFormDataBeforeFill` and forces the original slug in `handleRecordUpdate`.
    - **Club sports:** `club_sport` (`club_id` cascade, `sport_id` restrict, unique `(club_id, sport_id)`), `ClubSport` (uses `BelongsToClub`). `AttachSportToClubAction(Club, Sport)` and `DetachSportFromClubAction(Club, int $sportId)` run inside `CurrentClub::runAs($club, ...)` in a transaction. Endpoints (inside `tenant` + `subscription`): `GET /api/v1/club/sports` (`ClubPolicy::view`), `POST /club/sports` with `{sport_id}` and `DELETE /club/sports/{sport}` (both `ClubPolicy::update`, Owner only), `GET /api/v1/sports` (active catalog, `ClubPolicy::view`). HTTP: `Requests/Club/AttachClubSportRequest`, `ClubSportResource`, `SportResource` (API), `Controllers/Api/V1/Club/ClubSportController`, `Controllers/Api/V1/Sport/SportController`.
    - **Player enrollment:** `player_sport` (`club_id` cascade, `player_id` **cascade**, `sport_id` restrict, unique `(player_id, sport_id)`, index `(club_id, sport_id)`), `PlayerSport` (uses `BelongsToClub`). `EnrollPlayerInSportAction(Player, Sport)` and `UnenrollPlayerFromSportAction(Player, int $sportId)` run in the current club context. Endpoints: `GET /players/{player}/sports` (`PlayerPolicy::view`), `POST /players/{player}/sports` with `{sport_id}` and `DELETE /players/{player}/sports/{sport}` (`PlayerPolicy::update`). HTTP: `Requests/Player/EnrollPlayerSportRequest`, `PlayerSportResource`, `Controllers/Api/V1/Player/PlayerSportController`.
    - **Rules:** attach and enroll are idempotent (existing row returned, `200`, not logged; new row `201`). A sport cannot be newly attached or enrolled when `is_active = false` (`sport.inactive`, 422); clubs that already offer it keep it. A player can only be enrolled in a sport the club offers (`player.sport_not_offered`, 422). A sport cannot be removed from a club while players are enrolled (`player.sport_has_enrollments`, 422). Detaching a sport the club does not offer, or unenrolling a player who is not enrolled, is `404`.
    - **Audit events:** `sport.created`, `sport.updated` (platform-level, `club_id` null), `club.sport_added`, `club.sport_removed`, `player.sport_enrolled`, `player.sport_unenrolled`. Error codes (en + ar): `sport.inactive`, `player.sport_not_offered`, `player.sport_has_enrollments`.
    - Tests: `SportActionsTest`, `SportResourceTest`, `ClubSportApiTest`, `PlayerSportApiTest`. Suite: 300 tests green.
    - **Decisions (#19):** separate attach / detach endpoints instead of a full sync (each rule and audit event applies to one sport at a time); only Owners change the club's sports, no new permissions; the slug of a sport is immutable after creation (v2.0 modules link to it); deleting a player cascades its enrollments, and blocking deletion for players with subscriptions is #57's job; the "cannot remove a sport" rule is completed per table: #20 added the membership plans check (done) and #21 adds the training groups check to `DetachSportFromClubAction` (a TODO comment is in the Action).
  - **#20 (membership plans, PR open):** new domain `App\Domain\Membership`.
    - **Permissions:** `PermissionName::MembershipPlansView` (`membership_plans.view`) and `MembershipPlansManage` (`membership_plans.manage`). Owner: both, Staff: both, Coach: view, Referee: none. A migration re-runs `SyncClubRolesAction` for existing clubs and clears the permission cache.
    - **Model:** `membership_plans` (`club_id` cascade, `sport_id` restrict, `name` plain string, `price_cents` unsigned int, `currency` default `EGP`, `duration_months`, `is_active`; unique `(club_id, sport_id, name)`, index `(club_id, is_active)`). `MembershipPlan` (uses `BelongsToClub`, `#[UseFactory]`, `#[UsePolicy]`), `MembershipPlanFactory` (with `inactive()`), `MembershipPlanPolicy` (`viewAny` / `create` check only the permission, `view` / `update` also check the same club).
    - **Actions + DTOs:** `CreateMembershipPlanData`, `UpdateMembershipPlanData` (no `sportId`), `CreateMembershipPlanAction` (sport must be offered by the club and active; duplicate name rejected by a check and by catching `UniqueConstraintViolationException`; audit in the same transaction), `UpdateMembershipPlanAction` (only real changes logged under `changes.<field>.from/to`; the sport never changes), `ListMembershipPlansAction` (filters `sport_id`, `is_active`, no pagination, sorted by sport then name).
    - **Discount calculation:** `CalculateMembershipAmountAction::execute(int $priceCents, ?MembershipDiscount $discount)` returns `AmountBreakdown(priceCents, discountCents, totalCents)`. Pure integer math, no DB. `MembershipDiscount::percent(0..100)` rounds half up to the cent, `::fixed(cents)` may equal the price (free plan). Anything else throws `membership_plan.discount_invalid` (422). `DiscountType` enum (`percent`, `fixed`). #57 calls it when subscribing a player.
    - **Endpoints** (inside `tenant` + `subscription`): `GET /api/v1/membership-plans` (`viewAny`), `POST /membership-plans` (`create`), `GET /membership-plans/{plan}` (`view`), `PUT /membership-plans/{plan}` (`update`; replaces `name`, `price_cents`, `duration_months`, `is_active`; `sport_id` is `prohibited`). HTTP: `Requests/Membership/{ListMembershipPlansRequest,StoreMembershipPlanRequest,UpdateMembershipPlanRequest}` (authorization in `authorize()`; for an unknown plan the request returns `true` and the controller answers `404`), `MembershipPlanResource`, `Controllers/Api/V1/Membership/MembershipPlanController`.
    - **Rules:** `duration_months` 1 to 60, `price_cents` 0 to 100000000, name max 100. Plans have no delete; editing a plan never touches existing subscriptions (they snapshot price, currency and duration, #57). A sport cannot be removed from a club while it has active plans; inactive plans do not block it and other clubs' plans are ignored. `Sport` domain code now depends on `Membership` (domain to domain, allowed).
    - **Audit events:** `membership_plan.created`, `membership_plan.updated`. Error codes (en + ar): `membership_plan.sport_not_offered`, `membership_plan.name_taken`, `membership_plan.discount_invalid`, `membership_plan.sport_has_active_plans`.
    - Tests: `MembershipPlanPolicyTest`, `MembershipPlanActionsTest`, `CalculateMembershipAmountActionTest` (unit), `MembershipPlanApiTest`, `MembershipSportDetachTest`.
    - **Decisions (#20):** separate permissions for plans because prices are more sensitive than player data; discounts are applied at subscription time, not stored on the plan (stored promotions with validity dates are out of scope); currency is `EGP` only for now (no `currency` in the requests); plan name is plain text, not translatable (like player and club names).

## Technical notes (learned so far)
- Pest tests: Symfony adds `Accept-Language: en-us,en` to every test request. To test "no header", pass `['Accept-Language' => '']`.
- Arch tests live in `tests/Unit/` because `phpunit.xml` only runs the `Unit` and `Feature` suites.
- New endpoints go in `routes/api/v1.php` (inside the `tenant` group if club-scoped). `routes/api.php` only includes version files.
- IDE (Intelephense) may warn `Undefined method 'getJson'` inside Pest closures; harmless if the tests pass.
- Tests run on SQLite `:memory:`; tests without `RefreshDatabase` have no tables. `User::factory()->make()` still creates the nested `Club` factory, so pass `['club_id' => 1]` explicitly when a test needs no DB (as in `SetLocaleTest`).
- Auth guards cache the resolved user inside one test. When a single test sends requests with different tokens, call `$this->app['auth']->forgetGuards()` between them.
- Larastan: API Resources need `@mixin <Model>` in the class docblock (otherwise `property.notFound`). `ClubScope::apply` must type the builder as `Builder<covariant Model>`. `trait.unused` for `BelongsToClub` was removed from `phpstan.neon` in #17 when `StaffInvitation` became the first tenant-owned model using the trait.
- `StaffPolicy` checks model/club access and the relevant permission. The Owner role has all permissions; Staff has `staff.view` but not `staff.manage`; Coach and Referee have no `staff.*` permissions.
- Invitation tokens are stored as SHA-256 hashes; the plain token is sent only in the invitation link. Pending uniqueness is enforced per club/email.
- Coach invitation acceptance and promotion count only Coach-role users and enforce the plan limit inside a transaction with the club row locked.
- Windows: `composer require/update` can fail with "Could not delete ... vendor" because Defender / Search Indexer locks files. Close the IDE, add the project folder to Defender exclusions, delete the leftover `vendor\composer\<hash>` folder (or the whole `vendor`) and run `composer install`.
- Pint fixes style issues automatically: `vendor\bin\pint` (or on one file).
- **PowerShell:** a `--filter` with `|` must be in single quotes: `php artisan test --filter='A|B|C'`, otherwise PowerShell treats `|` as a pipe.
- Rate limiting is done in #10. #35 keeps Dependabot, SECURITY.md and the secrets check. IP-based limits need `trustProxies` behind a proxy or load balancer (done with Docker in #36), otherwise every client shares one IP. Rate limit tests rely on the `array` cache (default in `phpunit.xml`).
- Pest helper functions declared in a test file are **global**: names must be unique across files (existing: `registerPayload`, `makeClubUser`, `tenantProbeUser`, `tenantGet`, `tenantApiRoutes`, `tenantRouteIsProtected`, `throttledLogin`, `profilePayload`, `validPlayerPayload`, `makePlayerFor`, `seedPlayer`, `seedRoster`, `seedExistingAhmed`, `createPlayerIn`, `playerData`, `nationalIdErrors`, `attachSportFor`, `offerSport`, `playerOf`, `enrollDirectly`, `planPolicyCan`, `planClubWithSport`, `inClub`, `planSportFor`, `membershipPayload`, `makePlanFor`, `clubOffering`, `calculateAmount`). Prefer `TenantFixture` for new tests.
- In helper functions outside a test closure `$test->app` is protected: use `app()` (e.g. `app('auth')->forgetGuards()`).
- Routes registered inside a test (probe routes for middleware, 403, 429, 500) are fine: the app is rebuilt per test.
- Middleware order: route-priority middleware such as `auth:sanctum` runs before group middleware, so anything that must run earlier (locale, the `api` limiter reading `$request->user('sanctum')`) is global or resolves the user itself.
- Heads-up for the admin panel: `ClubScope` is fail-closed, so Filament code that reads tenant-owned models must set a club (`CurrentClub::runAs`) or bypass the scope on purpose. Keep such code under `app/Filament` (outside the directories `TenancyRulesTest` scans) and keep the super-admin separate from club roles. `Club`, `User` and the global `Sport` have no global scope; the first tenant-owned model shown in the panel (for example `Player`) will need this.
- **Admin vs club policies:** `ClubPolicy` is typed for `App\Models\User`. `Gate::before` in `AppServiceProvider` returns `true` for an `Admin` and `null` for everyone else, so admins skip club policies and club users still go through them (covered by a test). Filament asks the Gate for row actions and columns too, so overriding `canView()` on the Resource is not enough.
- Filament v5 notes: resources are generated as `Resources/<Name>/{Schemas,Tables,Pages}`; `php artisan make:filament-resource` does not generate a Create page unless asked, so add `CreateX` by hand. `$this->record` in a Page is typed `Model|int|string` for Larastan: use `/** @var Plan $plan */ $plan = $this->getRecord();`. Tests use `Livewire::test(...)`, `TestAction::make('x')->table($record)`, `assertHasNoActionErrors()`, and need `Filament::setCurrentPanel('admin')` plus `actingAs($admin, 'admin')` in `beforeEach`. Translatable fields use dotted inputs (`name.en`, `name.ar`); a page must fill both translations with `getTranslations('name')` before the form loads.
- Larastan does not see enum / date casts that come from `casts()`: add `@property ClubStatus $status`, `@property Carbon|null $x` to the model docblock.
- PHP docblocks: never write `lang/*/errors.php` inside a `/** */` comment, the `*/` closes the comment (Pint reports a parse error).
- A test that sends several requests must remember the guard cache (see above), so `SubscriptionEnforcementTest` sends one request per test and changes the club before it.
- Boost / Livewire: installing Livewire made Laravel Boost rewrite `.claude/skills/infer-conventions/*`. Commit those separately from feature code.
- Before `git add .`, run `git status --short` and check for unrelated files.
- Tests run on SQLite locally but on **MySQL 8 in CI**. MySQL reorders the keys of a JSON column (shorter keys first, then alphabetical), so `toBe()` on a decoded JSON array passes locally and fails in CI, and key order in audit `changes` is not stable. Assert JSON content with `toEqual()` and key lists with `toEqualCanonicalizing()`.
- activitylog API in the installed version: read properties with `$activity->properties->get('key')` (`getExtraProperty()` does not exist). `config('activitylog.table_name')` returned an empty value, so the `club_id` migration hard-codes `activity_log`.
- Tests for admin-caused events: `$this->actingAs(Admin::factory()->create(), 'admin')` before calling the Action; `Activity` is not tenant-scoped, so tests need no `CurrentClub`.
- `.env` with `DB_PASSWORD` empty makes `php artisan migrate` fail with `Access denied for user 'root'`: set the MySQL password from the Herd service, then `php artisan config:clear`.
- The base `Controller` is empty (Laravel 13): no `AuthorizesRequests`. Controllers authorize with `Gate::authorize('update', $club)` against the model. `AuthorizationException` is rendered as `403 http.forbidden` by `ApiExceptionRenderer`.
- A `FormRequest` validates **before** the controller's `Gate::authorize`, so a user without permission who sends invalid data gets `422` instead of `403`. To get `403` first, authorize inside the Request's `authorize()` (done for players, club sports and enrollment; for a record that may not exist, return `true` when it is `null` so the controller answers `404`).
- `ClubFactory` fills `contact_email`, `contact_phone` and `address`. A test that compares audit `changes` must set the fields it cares about explicitly (`null` included), not rely on factory defaults.
- File upload tests: `Storage::fake('public')` + `config(['filesystems.club_logo_disk' => 'public'])`, and `UploadedFile::fake()->image()` needs the GD extension. Multipart requests use `->post(url, data, ['Accept' => 'application/json'])`.
- `php artisan storage:link` is needed once per machine (the link already existed on Herd).
- New club-scoped routes were added as a second group in `routes/api/v1.php`, outside `prefix('auth')`: `Route::middleware(['auth:sanctum', 'tenant', 'subscription'])`. Pint's `ordered_imports` fails `composer check` if controller imports are not alphabetical: run `vendor\bin\pint` before committing.
- `php artisan test` runs Pest in a child process, so `php -d memory_limit=...` on the parent does not apply. The limit lives in `phpunit.xml` (`<ini name="memory_limit" value="512M"/>`); the suite had reached 128 MB and the real error was hidden behind a Whoops page.
- Implicit route model binding can resolve before the `tenant` middleware sets `CurrentClub` (the fail-closed scope would return 404 for everything). Controllers and FormRequests load tenant-owned models with `Model::query()->findOrFail($id)` inside the request, after the middleware ran, and use `->whereNumber()` on the route. Controller methods take the route id as `int $player` and load the model themselves.
- In tests, factories for tenant-owned models create a new club by default (`club_id => Club::factory()`): pass `['club_id' => $tenant->club->id]` explicitly, otherwise the record lives in another club and the scope hides it. To create a tenant-owned row for a tenant in a test, use `app(CurrentClub::class)->runAs($tenant->club, fn () => ...)`. Tests may use `withoutGlobalScopes()` to count rows across clubs (`TenancyRulesTest` only scans `app/Http` and `app/Domain`).
- List endpoints: the sort column comes from a validated whitelist and `per_page` is capped in the Request; the Action never trusts raw input. `LIKE` input is escaped with `addcslashes`.
- Actions that receive a `Club` (not the request context) take `CurrentClub` in the constructor and wrap their work in `$this->currentClub->runAs($club, fn)`; they are resolved from the container.
- A domain may depend on another domain (`Sport` actions read `MembershipPlan`); only `App\Http` is forbidden inside `App\Domain` (arch test).
- Pest: `->throws(Class::class)` on a test with a dataset applies to every dataset row. To assert the `code` of a `BusinessRuleException` in a Feature test, assert it through the API response (`assertJsonPath('code', ...)`) instead of the exception message.
- Validation rule `prohibited` is the way to reject a field that must not be sent (used for `sport_id` in the membership plan `PUT`).
- Adding a permission = enum case in `PermissionName` + mapping in `RoleName::permissions()` + a migration that runs `SyncClubRolesAction` for existing clubs and forgets the cached permissions; update tests that assert exact permission lists.
- Pure calculation Actions (like `CalculateMembershipAmountAction`) take primitives and DTOs, not models, so their unit tests need no database.

## Labels
`type:feature` `type:chore` `type:test` `type:docs` `area:api` `area:admin` `area:infra` `area:tenancy` `priority:high`

---

## M1 Foundation - DONE

**#1 chore: initialize project** - DONE
- [x] Laravel + MySQL + Pest
- [x] Complete .env.example
- [x] Pint + Larastan config

**#2 ci: GitHub Actions pipeline** - DONE
- [x] Workflow on every PR: Pest + Pint + Larastan (PHP 8.4)
- [x] MySQL service in the workflow
- [x] Branch protection on main (ruleset `Protect main`)

**#3 docs: ADR-001 tenancy strategy** - DONE
- [x] Compare single-DB (club_id) vs multi-DB
- [x] Document the decision and reasons in docs/adr/

**#4 chore: project structure** - DONE
- [x] Split into modules/domains (Club, Player, Tournament, ...)
- [x] Conventions: Actions + DTOs + Form Requests + API Resources
- [x] README skeleton with Mermaid architecture diagram

**#39 feat: localization foundation (ar/en)** - DONE
- [x] Publish lang files + Arabic translations (`laravel-lang/common`)
- [x] `users.locale` column + `SetLocale` middleware (Accept-Language > user locale > en)
- [x] Error format: stable `code` + translated `message`
- [x] `spatie/laravel-translatable` installed (applied to models in #13 and #19)
- [x] Tests: same request returns ar and en responses; lang files have matching keys
- [x] Convention documented (ADR-003 + README)

## M2 Tenancy & Auth - DONE

**#5 feat: Club model & tenant scoping** (`type:feature`, `area:tenancy`, `priority:high`) - DONE
- [x] clubs table
- [x] BelongsToClub trait + global scope
- [x] club_id auto-filled on create

**#6 feat: Sanctum authentication** (`type:feature`, `area:api`) - DONE
- [x] Login / logout / me
- [x] Token abilities
- [x] Register a new club (creates Club + first user; Owner role assigned in #7)

**#7 feat: roles & permissions** (`type:feature`, `area:api`) - DONE
- [x] Roles: Owner / Coach / Staff / Referee
- [x] spatie/laravel-permission in Teams mode (club_id)
- [x] Policy convention + `ClubPolicy` (each later resource ships its own policy: Staff #17, Player #18, Tournament #23)
- [x] Assign the Owner role inside `RegisterClubAction`

**#8 feat: tenant resolution middleware** (`type:feature`, `area:tenancy`) - DONE
- [x] Resolve current club from the authenticated user
- [x] Reject requests from users without a club

**#9 test: tenant isolation** (`type:test`, `area:tenancy`, `priority:high`) - DONE
- [x] User from club A cannot read or modify club B data
- [x] Cover every existing endpoint (and force every future one: `RouteTenancyTest`)

**#10 feat: API conventions** (`type:feature`, `area:api`) - DONE
- [x] Versioning: /api/v1
- [x] Unified response format + exception handler (401/403/404/405/422/429/500)
- [x] Rate limiting

## M3 Admin Panel - DONE

**#11 feat: Filament admin panel** (`type:feature`, `area:admin`) - DONE
- [x] Separate panel with a separate guard for super-admin
- [x] No auth sharing with the club API
- [x] Heads-up: admin code reading tenant-owned models must handle `ClubScope` explicitly (see Technical notes)

**#12 feat: clubs management** (`type:feature`, `area:admin`) - DONE
- [x] Clubs resource: create, edit, suspend, activate
- [x] View club users

**#13 feat: SaaS plans** (`type:feature`, `area:admin`) - DONE
- [x] plans table (max players/coaches, price, duration)
- [x] Attach club to a plan + subscription dates

**#14 feat: subscription enforcement** (`type:feature`, `area:api`, `priority:high`) - DONE
- [x] Middleware: suspended or expired club gets 403/402
- [x] Plan limit check as an Action (`EnsureWithinPlanLimitAction`); called from #17 (coaches) and #18 (players)

**#15 feat: audit log** (`type:feature`, `area:admin`) - DONE
- [x] Log sensitive operations (activity log): club created / suspended / activated / plan assigned, plan created / updated
- [x] Browse logs in the admin panel (read-only, filters by event and club)
- [x] ADR-006 + README section

## M4 Club API Core

Execution order: #56, #18, #19, #20, #21, #22.

**#16 feat: club profile & settings** (`type:feature`, `area:api`) - DONE
- [x] GET/PUT club profile (name, contact email / phone, city, address)
- [x] Logo upload and removal (configurable disk, no SVG)
- [x] Audit events: `club.profile_updated`, `club.logo_updated`, `club.logo_removed`

**#17 feat: staff management & invitations** (`type:feature`, `area:api`) - DONE
- [x] Invite coach/staff by token
- [x] Assign role
- [x] Remove staff; prevent Owner self-lockout
- [x] `StaffPolicy` (uses `staff.view` / `staff.manage`); listing users filters by `club_id` explicitly (`User` has no global scope); cross-club tests with `TenantFixture`

**#56 docs: ADR-007 multi-sport model** (`type:docs`) - DONE
- [x] Write `docs/adr/007-multi-sport.md` before any code in #18 to #21
- [x] Does a club offer one sport or several? Is a player enrolled in one or several?
- [x] Is the list of sports global (managed by the super-admin) or per club?
- [x] Do membership plans (#20), training groups (#21) and player subscriptions (#57) belong to a sport?
- [x] Do SaaS plan limits (players / coaches) stay per club, or count per sport?
- [x] How will sport-specific rules (belts, categories, weight classes, scoring, brackets) plug in later (v2.0) without changing the v1.0 core? Karate is just another sport in v1.0.

**#18 feat: players CRUD** (`type:feature`, `area:api`, `priority:high`) - DONE
- [x] Fields: national ID, birth date, phone (belt and weight are karate-specific and move to v2.0)
- [x] Filters + sorting + pagination
- [x] Egyptian national ID validation (derive age and gender)
- [x] `Player` uses `BelongsToClub`; the `trait.unused` ignore was already removed in #17
- [x] `PlayerPolicy` (club + `players.*` permissions) and cross-club tests with `TenantFixture`
- [x] Adding a player calls `EnsureWithinPlanLimitAction` inside a transaction with a lock on the club row
- [x] Test: a player enrolled in several sports counts once toward the plan limit (ADR-007)

**#19 feat: sports catalog & player enrollment** (`type:feature`, `area:api`) - DONE
- [x] `Sport` reference data with translatable ar/en name (`spatie/laravel-translatable`), seeded; managed by the super-admin in Filament
- [x] A club chooses the sports it offers (club-scoped endpoint, attach / detach)
- [x] Enroll a player in one or more of the club's sports
- [x] Policies + cross-club tests with `TenantFixture`
- [x] A sport cannot be removed from a club while players are enrolled
- [x] ADR-007 implementation notes
- [x] Not here: belts, age categories and weight classes (v2.0, #60)

**#20 feat: membership plans** (`type:feature`, `area:api`, `priority:high`) - DONE (PR open, confirm merge)
- [x] Club-defined membership plans per sport: name, price, duration in months (model `MembershipPlan`, not `Plan`)
- [x] The sport of a plan must be one the club offers (`club_sport`) and active
- [x] `DetachSportFromClubAction`: block removal while the sport has active membership plans (`membership_plan.sport_has_active_plans`)
- [x] Discounts: computed at subscription time by `CalculateMembershipAmountAction` (not stored on the plan)
- [x] Amount calculation in an Action class with unit tests
- [x] Policy + cross-club tests with `TenantFixture`
- [x] ADR-007 implementation notes

**#21 feat: training groups & attendance** (`type:feature`, `area:api`)
- [ ] Training groups with sport, schedule and coach
- [ ] `DetachSportFromClubAction`: block removal while the sport has training groups (new error code in `en` and `ar`, replace the TODO)
- [ ] Bulk attendance endpoint

**#22 feat: bulk import players** (`type:feature`, `area:api`)
- [ ] Upload CSV/Excel (columns include the sport)
- [ ] Process as a queued Job + status endpoint
- [ ] Report of rejected rows and reasons

## M5 Player Subscriptions

(GitHub milestone renamed from "M5 Tournaments".)

**#57 feat: player subscriptions** (`type:feature`, `area:api`, `priority:high`) - NEW
- [ ] Subscribe a player to a membership plan (start and end dates); snapshot price, currency and duration; compute the amount with `CalculateMembershipAmountAction` (optional percent / fixed discount)
- [ ] Status: active, expired, cancelled
- [ ] Renew (from the current end date) and cancel
- [ ] Block deleting a player who has subscriptions (`DELETE /players/{player}` is a hard delete today)
- [ ] Policy + cross-club tests with `TenantFixture`

**#58 feat: payments & receipts** (`type:feature`, `area:api`) - NEW
- [ ] Record payments against a subscription (amount in cents, method, date)
- [ ] Partial payments and outstanding balance (Action with unit tests)
- [ ] List of expiring and unpaid subscriptions (feeds #31 and #32)

## M6 Redis & Background Jobs

(GitHub milestone renamed from "M6 Redis & Real-time". The live scoreboard moved to v2.0.)

**#29 feat: Redis for cache, queue, rate limiting** (`type:feature`, `area:infra`)
- [ ] Move queue and cache to Redis + Horizon
- [ ] Cache permissions and reference data (tenant in the cache key)

**#31 feat: notifications** (`type:feature`, `area:api`)
- [ ] Mail + database notifications (registration, expiring player subscriptions)
- [ ] Endpoint to read notifications
- [ ] Tournament result notifications: v2.0 (#27)

**#32 feat: scheduled tasks** (`type:feature`, `area:infra`)
- [ ] Auto-expire club subscriptions (SaaS plans)
- [ ] Auto-expire player subscriptions (#57)
- [ ] Reminders (expiring subscriptions, unpaid balances)
- [ ] Auto-close registration at tournament date: v2.0 (#24)

## M7 Quality & Release

**#33 perf: query & index review** (`type:chore`)
- [ ] Model::preventLazyLoading() in non-production
- [ ] Indexes (club_id first in composite indexes)
- [ ] Review slowest endpoints

**#34 docs: API documentation** (`type:docs`)
- [ ] Scribe/OpenAPI + Postman collection

**#35 chore: security hardening** (`type:chore`, `area:infra`)
- [ ] Dependabot + SECURITY.md
- [ ] Verify no secrets in git history
- [x] Throttling on auth endpoints (done in #10)

**#36 feat: Docker Compose** (`type:feature`, `area:infra`)
- [ ] app + MySQL + Redis + queue worker (Reverb is added in v2.0)
- [ ] One-command setup documented in README
- [ ] Configure trusted proxies (IP-based rate limits)

**#37 test: coverage & static analysis** (`type:test`)
- [ ] Larastan level 6+ clean
- [ ] Coverage badge

**#38 docs: v1.0.0 release** (`type:docs`)
- [ ] CHANGELOG + Release + tag
- [ ] Final README: architecture diagram, design decisions, admin panel screenshots
- [ ] README states the v1.0 scope (multi-sport clubs, player subscriptions) and links the v2.0 roadmap

---

## Post-v1.0 backlog (not scheduled)

Ideas agreed on 2026-10-26. No GitHub issues yet: create them when v1.0.0 is out. "Support sports other than karate" left this list: it is part of v1.0 now (#56, #19). Nothing here is implemented, and none of it blocks the current milestones.

**Trial plan for new clubs**
- [ ] A new club registered through `register-club` gets a trial plan automatically (instead of no plan).
- [ ] Open decisions: trial length, its limits, whether it needs a credit card (no), what happens when it ends (the club gets `402 subscription.expired` and the admin or the owner picks a paid plan).
- [ ] Revisit the #14 rule "no plan = unlimited": once every club has a plan, a club with no plan should be treated as not subscribed.
- [ ] Touches `RegisterClubAction` / `CreateClubAction`, `AssignPlanToClubAction` (a trial plan seeded with a flag), the admin panel (show trial clubs), and a scheduled task in #32 for trial reminders.

**Plan limits will change (not now)**
- [ ] The numbers and the kinds of limits on players and coaches will be revised later (new tiers, maybe other limits such as tournaments per year). Nothing changes in v1.0.0.
- [ ] Keep it cheap to change: limits are plan columns, the check is one Action (`EnsureWithinPlanLimitAction`) and one enum (`PlanLimit`). A new limit = one enum case + one column + one error code in `en` and `ar`.

---

## Implementation notes
- M1 to M3 are done; M4 is in progress (#16, #17, #18, #19, #20 and #56 done). For every new club-scoped endpoint: put it inside the `tenant` group **and its nested `subscription` group** (`RouteTenancyTest` fails otherwise), add cross-club tests with `TenantFixture`, add its policy and permissions, and add any new error or translation keys in both `en` and `ar`. Any Action that adds players or coaches calls `EnsureWithinPlanLimitAction` inside a transaction with a lock on the club row. Any new sensitive operation records an `ActivityEvent` with `RecordActivityAction` inside the Action's transaction, with the key in `en` and `ar` (ADR-006).
- Each issue: branch, PR, Conventional Commit, tests included, `composer check` green before pushing.
- Skip tests that only exercise the framework (e.g. "factory creates a model"); write tests for our own logic and rules.
- First message to send in the new chat: "Continue RYADA v1.0 from the current status (start #21 training groups & attendance)." (If the #20 PR is not merged yet, say so first.)