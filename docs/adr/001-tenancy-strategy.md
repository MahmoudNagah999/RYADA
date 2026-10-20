# ADR-001: Tenancy Strategy

- **Status:** Accepted
- **Date:** 2026-10-20
- **Deciders:** Mahmoud Nagah
- **Related issues:** #3, #5, #8, #9

## Context

RYADA is a multi-tenant SaaS: each karate club is a tenant with its own players, staff, training groups, memberships, and tournament registrations. A tenant must never see or modify another tenant's data.

Expected shape of the system:

- Many small tenants (clubs and academies), each with tens to a few thousand players.
- A super-admin (platform owner) who manages all clubs, plans, and subscriptions from a Filament panel.
- Cross-tenant features are likely later (federation-level tournaments, player transfers between clubs).
- A small team (one backend developer), so operational simplicity matters.

## Options considered

### Option A: Single database, shared tables, `club_id` column

Every tenant-owned table has a `club_id` column. A global scope (`BelongsToClub` trait) filters all queries by the current club and auto-fills `club_id` on create.

### Option B: Single database, schema per tenant

Not available as a first-class feature in MySQL (schema = database), so it collapses into Option C.

### Option C: Database per tenant

Each club gets its own database. The app switches the connection at runtime based on the current tenant (for example with `stancl/tenancy`).

## Comparison

| Criteria | A: `club_id` (single DB) | C: DB per tenant |
|---|---|---|
| Data isolation | Logical (app-enforced) | Physical (strongest) |
| Risk of data leak by bug | Higher (a missing scope leaks data) | Lower |
| Migrations | One run | One run per tenant (slow, can partially fail) |
| Onboarding a new club | Insert a row | Create DB + run migrations |
| Cross-tenant queries / reporting | Simple | Hard |
| Super-admin panel (Filament) | Simple | Needs connection switching |
| Backups / per-tenant restore | Whole DB, per-tenant restore is hard | Easy per tenant |
| Connection management at scale | One pool | Many databases and connections |
| Operational cost for a small team | Low | High |
| Fit with spatie/laravel-permission Teams | Native (`club_id` as team key) | Works, but redundant |

## Decision

We use **Option A: a single database with a `club_id` column on every tenant-owned table**, enforced by:

1. A `BelongsToClub` trait that applies a global scope and auto-fills `club_id` on creating.
2. A tenant resolution middleware that sets the current club from the authenticated user (#8).
3. `spatie/laravel-permission` in Teams mode, with `club_id` as the team key (#7).
4. A dedicated tenant isolation test suite that covers every endpoint (#9).

## Consequences

### Positive

- Simple onboarding, migrations, and deployment.
- Cross-tenant reporting and the super-admin panel stay straightforward.
- Low operational cost, which fits a small team.

### Negative and risks

- Isolation is enforced in application code. A model without the trait, a raw query, or `withoutGlobalScopes()` can leak data.
- One large tenant can affect others (noisy neighbor).
- Per-tenant backup and restore is harder.

### Mitigations

- Every tenant-owned model must use `BelongsToClub`; a test fails if a model with a `club_id` column does not use it.
- Tenant isolation tests (#9) are mandatory for every new endpoint.
- Raw queries and `withoutGlobalScopes()` are forbidden in club-facing code, and allowed only in the admin panel.
- Composite indexes start with `club_id`.
- Unique constraints are scoped per club (for example `unique(club_id, national_id)`).
- Queued jobs, cache keys, and broadcast channels carry the tenant explicitly (the club id is part of the job payload, cache key, and channel name).

## Revisit when

- A customer requires physical data isolation for compliance reasons.
- A single tenant grows large enough to need its own database.

In that case, the `BelongsToClub` abstraction keeps the migration path open: large tenants can be moved to dedicated databases without changing domain code.
