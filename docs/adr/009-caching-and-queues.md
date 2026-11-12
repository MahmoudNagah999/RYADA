# ADR-009: Caching, queues and Redis

- Status: Accepted
- Date: 2026-11-11
- Issue: #29

## Context

RYADA is multi-tenant with a single database (ADR-001). Anything stored outside the database (cache entries, queued jobs) is also outside the reach of the `ClubScope` global scope, so a wrong key or a missing club context could leak data from one club to another. Rate limiting, reference data (sports) and background work (mails now, notifications and reminders in #31 and #32) need a fast shared store.

## Decision

1. **Redis** is the store for the cache, the queue and the rate limiter. The client is `predis` (pure PHP, no extension needed on Windows). Tests do not use Redis: `phpunit.xml` forces the `array` cache and the `sync` queue, and a test fails if the cache store is anything else.
2. **Club-specific cache entries only go through `TenantCache`.** Every key is `club:{id}:{name}` built from `CurrentClub`; with no current club it throws (fail-closed, like `ClubScope`). No cache tags: explicit keys and `forget` are simpler and work on every store.
3. **What is cached:** the sports catalog (`sports:catalog`, global, holds every translation, so one entry serves all locales) and the sports of a club (`club:{id}:sports`, holds sport ids and dates only; names and flags come from the catalog, so renaming a sport can never leave a stale club entry). Entries hold plain arrays, never Eloquent models. TTL is one hour as a safety net; correctness comes from invalidation.
4. **Invalidation is done by model observers (`Sport`, `ClubSport`) that run after the commit** (`ShouldHandleEventsAfterCommit`). Clearing inside the transaction could let a concurrent read refill the cache with the old value; observers also cover Filament, seeders and any future writer, so Actions stay unchanged. A rolled-back transaction clears nothing.
5. **Not cached on purpose:** players, membership plans, subscriptions, payments (many filters, high write rate, little gain) and SaaS plans (read only in the admin panel). Roles and permissions are already cached by `spatie/laravel-permission` in the default cache store, so there is no custom permission cache.`SyncClubRolesAction` needs no explicit flush: `syncPermissions()` clears that cache, and `PermissionCacheTest` proves it.
6. **Queued jobs carry the club.** A worker has no request, so nobody sets `CurrentClub`. Club jobs extend `TenantQueuedJob` (implements `TenantAwareJob`, dispatch with `clubId` plus plain ids). The `RunsInClubContext` job middleware loads the club, runs the job inside `CurrentClub::runAs` with the roles team set, restores the previous context, and drops the job if the club no longer exists. Jobs never hold a tenant-owned model (a worker would reload it outside the scope and fail). Architecture rules (`QueueRulesTest`) fail when a queued class is not tenant-aware (mailables are exempt) or holds a tenant-owned model.
7. **Queue options:** `after_commit` is on for the Redis connection (a job dispatched inside a transaction runs only after the commit). Job `timeout` (60 s) stays below `retry_after` (90 s). Queued mails are sent in the recipient's locale (ADR-003).
8. **Rate limiting** keeps the limiters from ADR-005; they use the default cache store, so they run on Redis with no code change. `throttleWithRedis()` is not used because it would force Redis into the test suite.
9. **Horizon** is installed and configured (one supervisor, queue `default`). Its dashboard (`/horizon`) is open only to a signed-in super-admin (the `admin` guard); club users and API tokens get 403. Horizon needs `pcntl` and `posix` (Linux), so it runs in Docker (#36); on Windows the package is installed with `--ignore-platform-req=ext-pcntl --ignore-platform-req=ext-posix` and the local worker is `php artisan queue:work redis`.
10. **Environments are separated by prefix** (`CACHE_PREFIX`, `REDIS_PREFIX`, `HORIZON_PREFIX`), so two environments on one Redis never share keys and `cache:clear` stays scoped.

## Consequences

- A new club-specific cache entry costs one `TenantCache` call and one observer; a new club job costs one subclass of `TenantQueuedJob`. Forgetting the club is a failing test, not a data leak.
- Redis becomes a hard dependency of the running application: if it is down, requests that touch the cache or the rate limiter fail with 500. The known failure mode is accepted for v1.0; Docker (#36) adds a Redis healthcheck. A `failover` cache store was rejected because falling back to the per-process `array` store would silently break invalidation and rate limits.
- The sports catalog is cached whole, with all translations, so it stays small (about ten rows) and one invalidation covers every locale. If a cached collection ever grows large, it needs its own design.
- `sync` queue in tests runs a job inside the request that dispatched it, which hides missing club context. Job tests therefore dispatch with no current club on purpose (`RunsInClubContextTest`).
- A queue worker keeps the old code in memory: deploys must run `php artisan queue:restart` (Horizon: `horizon:terminate`).

## Revisit when

- Cached collections grow, or a read endpoint becomes slow in the #33 review (add a cache there with the same `TenantCache` pattern).
- Redis availability becomes a requirement (Sentinel or a managed Redis).
- Jobs need priorities or separate queues (add supervisors in `config/horizon.php`).
