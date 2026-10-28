# ADR-006: Audit log

- **Status**: Accepted
- **Date**: 2026-10-28
- **Deciders:** Mahmoud Nagah
- **Related issues:** #15

## Context

The super-admin needs to know who did what on the platform: suspending a club,
assigning a plan, changing a plan's price or limits. RYADA bills clubs, so this
is also a basic accountability requirement.

## Decision

### Package and model

- `spatie/laravel-activitylog`, with our own model `App\Domain\Audit\Models\Activity`
  (set in `config/activitylog.php`).
- The `activity_log` table gets a `club_id` column (nullable, `nullOnDelete`,
  composite index with `created_at`). The log survives the deletion of a club.

### Recording an event

- One entry point: `RecordActivityAction`. Actions never call `activity()` directly.
- Events are an enum, `ActivityEvent` (for example `club.suspended`). The key is
  stored in `description` and `event`, and the log name is the part before the dot.
  The text shown to the admin is translated at display time (`audit.events` in
  `lang/*/admin.php`), so stored rows never depend on a locale (ADR-003).
- The log is written **inside the same `DB::transaction`** as the change: if the
  change rolls back, the log rolls back with it.
- Idempotent no-ops (suspending a suspended club, saving a plan with no changes)
  are not logged.

### Who and where

- **Causer:** resolved explicitly, `admin` guard first, then `sanctum`. We do not
  rely on the default guard, because Filament does not use it. Public registration
  has no causer.
- **`club_id`:** taken from the subject when it is a `Club`, otherwise from
  `CurrentClub`, or passed explicitly. It is `null` for platform-level events
  (plans).
- `Activity` does **not** use `BelongsToClub`: the super-admin reads all clubs.
  A club-facing audit view would need its own scoped query, not this model.

### Properties

- Only what is needed to understand the change: `from` / `to` for changed fields,
  ids and slugs for references.
- Never passwords, tokens, or national IDs.

### Events recorded so far

| Event | Action | Properties |
|---|---|---|
| `club.created` | `CreateClubAction` | `name`, `slug` |
| `club.suspended` | `SuspendClubAction` | `status` (from / to) |
| `club.activated` | `ActivateClubAction` | `status` (from / to) |
| `club.plan_assigned` | `AssignPlanToClubAction` | `plan_id`, `plan_slug`, `previous_plan_id`, `starts_at`, `ends_at` |
| `plan.created` | `CreatePlanAction` | `slug`, price, duration, limits, `is_active` |
| `plan.updated` | `UpdatePlanAction` | `changes` (field: from / to) |

### Admin panel

- Filament `ActivityResource`: list only, with filters by event and club.
- Read-only and append-only: no create, edit or delete.

## Consequences

- A new sensitive operation = one `ActivityEvent` case + one `RecordActivityAction`
  call inside the Action's transaction + the event key in `en` and `ar`
  (`LangFilesTest` enforces matching keys).
- Events for club users (staff #17, players #18, ...) follow the same convention.
  Plan changes made outside `CreatePlanAction` / `UpdatePlanAction` are not logged,
  so the Filament pages must keep going through them.
- Rows cannot be edited or deleted from the app. Retention and pruning are not
  decided yet.
