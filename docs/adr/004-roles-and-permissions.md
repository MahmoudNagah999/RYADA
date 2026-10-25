# ADR-004: Roles and permissions

- **Status**: Accepted
- **Date**: 2026-10-25
- **Deciders:** Mahmoud Nagah
- **Related issues:** #7

## Context

RYADA is multi-tenant (ADR-001): every club's data is isolated by `club_id`.
Roles and permissions must follow the same boundary, so an Owner of one club
is never an Owner in another, and clubs can later customize their own roles.

## Decision

- Use `spatie/laravel-permission` in **Teams mode**, with `club_id` as the team key
  (`teams => true`, `team_foreign_key => club_id`).
- **Roles are per club.** Each club gets its own copy of the four roles: `owner`,
  `coach`, `staff`, `referee`. `SyncClubRolesAction` creates them idempotently and
  syncs their permissions. It is called from `RegisterClubAction` and can be re-run
  when permissions are added.
- **Permissions are global.** They are code-defined in `PermissionName` and mapped
  to roles in `RoleName::permissions()`. Role and permission names are stable
  identifiers, never translated.
- **Policies check two things:** the user belongs to the model's club, and the user
  has the matching permission (`checkPermissionTo`). The club check is a second line
  of defence on top of `ClubScope`.
- Controllers authorize against the **model** (`$this->authorize('update', $club)`),
  never against a bare permission string. spatie registers permissions as Gate
  abilities, so `can('club.update')` would skip the club check.
- Each resource's policy is created with its first issue, per ADR-002
  (`StaffPolicy` in #17, `PlayerPolicy` in #18, `TournamentPolicy` in #23).
- Sanctum token abilities (`read` / `write`) only cap what a token can do.
  Real authorization comes from roles and policies.

## Consequences

- The current team must be set before any role or permission check:
  `setPermissionsTeamId($club->id)`. The tenant middleware does this in #8.
  Actions, jobs, seeders and tests set it explicitly and restore the previous value.
- Adding a permission means editing `PermissionName` and `RoleName::permissions()`,
  then running `SyncClubRolesAction` for existing clubs (a migration or command).
- The permission cache is per team. When Redis arrives (#29) the tenant must be part
  of the cache key.
- A user belongs to one club (email is unique platform-wide), so Teams mode is used
  for role isolation, not for multi-club users.
