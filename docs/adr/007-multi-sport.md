# ADR-007: Multi-sport model

- Status: Accepted
- Date: 2026-11-03
- Issue: #56
- Related: ADR-001 (tenancy), ADR-002 (structure), ADR-003 (localization), ADR-004 (roles and permissions)

## Context

RYADA v1.0 manages clubs and academies that can offer several sports. Players, membership plans, training groups and player subscriptions all need to know about sports, and issues #18 to #21 and #57 depend on the same model. Karate belts, age categories, weight classes and tournaments arrive in v2.0 and must plug in without changing the v1.0 core.

Questions this ADR answers:

1. Does a club offer one sport or several? Is a player enrolled in one or several?
2. Is the list of sports global or per club?
3. Which entities belong to a sport?
4. Do SaaS plan limits stay per club or count per sport?
5. How do sport-specific rules plug in later?

## Decision

### 1. Many-to-many on both sides

- A club offers one or more sports: `club_sport`.
- A player is enrolled in one or more of the club's sports: `player_sport`.
- Both tables are tenant-owned: they carry `club_id`, use `BelongsToClub`, and every composite index starts with `club_id`.

```text
Sport 1 ── * club_sport * ── 1 Club
Sport 1 ── * player_sport * ── 1 Player
Sport 1 ── * MembershipPlan 1 ── * PlayerSubscription * ── 1 Player
Sport 1 ── * TrainingGroup
```

Constraints:

- `club_sport`: unique `(club_id, sport_id)`.
- `player_sport`: unique `(club_id, player_id, sport_id)`.
- A player can only be enrolled in a sport the club offers (checked in the enrollment Action).
- A sport cannot be removed from a club while the club still has enrollments, active membership plans or training groups in it. The Action throws a `BusinessRuleException` (code added in `en` and `ar`).

### 2. Global sports catalog

- `Sport` is platform reference data, not tenant-owned: it does **not** use `BelongsToClub` (same as `Plan`).
- Columns: `slug` (unique, stable key), `name` (translatable ar/en with `spatie/laravel-translatable`), `is_active`.
- Managed by the super-admin in Filament. No delete: deactivate with `is_active`, as with `Plan`.
- Club-facing endpoints only read the catalog and choose from it. A club cannot create a sport.
- Deactivating a sport hides it from new choices (club selection, new enrollments, new plans and groups). Existing rows keep working.
- Seeded with a starting list that includes karate. In v1.0 karate is just one more row.

### 3. What belongs to a sport

- `MembershipPlan` (#20): `sport_id` required.
- `TrainingGroup` (#21): `sport_id` required.
- `PlayerSubscription` (#57): no `sport_id` of its own. The sport comes from its plan. The Action checks that the player is enrolled in that sport.
- `PlayerSubscription` stores a **snapshot** of price, currency and duration at subscription time, so changing a plan later never rewrites past subscriptions, balances or reports.
- `Player` (#18) has no sport column. Sports come only through `player_sport`.

### 4. SaaS plan limits stay per club

- `Plan.max_players` and `Plan.max_coaches` count per club. A player enrolled in three sports counts **once**.
- The count is `COUNT(players)`, never `COUNT(player_sport)`. #18 includes an explicit test for this.
- `EnsureWithinPlanLimitAction` and `PlanLimit` do not change.
- If limits per sport are needed later, that is a new `PlanLimit` case plus a column, as described in the backlog.

### 5. Sport-specific rules plug in from outside the core

- The v1.0 core has no sport-specific columns: no belt, no weight, no category on `Player`.
- Each sport module owns its tables and its domain folder (for example `App\Domain\Karate` in v2.0). Its tables reference `player_id` and, where needed, `sport_id`, for example a `karate_profiles` table with belt and weight.
- The link between a module and a sport is `Sport.slug`.
- The core never imports a sport module. Modules depend on the core, not the other way around (to be enforced with an arch test when the first module exists).
- The registration mechanism for modules (registry or interface) is designed in ADR-008 (#59), not here.

## Consequences

Good:

- One schema serves a club with one sport or many, with no later migration of the core.
- Pricing, groups and subscriptions are sport-aware from day one.
- Plan limits keep their meaning (platform cost per player) and need no change.
- v2.0 adds karate without touching players, plans or subscriptions.

Costs:

- Two pivot tables with their own policies and cross-club tests.
- A club cannot add a sport that is not in the catalog; the super-admin must add it first.
- Removing a sport from a club needs dependency checks.
- A snapshot on `PlayerSubscription` duplicates a little data on purpose.

## Alternatives considered

- **Single sport per club or per player (`sport_id` on `clubs` / `players`):** simpler, but contradicts the v1.0 scope and forces a schema change later.
- **Per-club sports (each club creates its own):** duplicates and inconsistent names, no shared translations, no stable key for v2.0 rules.
- **Limits per sport:** closer to usage, but a player in two sports would count twice, and it complicates plans and the admin panel for no current need.
- **Sport-specific columns or a JSON column on `Player`:** pollutes the core and weakens validation; separate module tables are typed and testable.
- **Subscription with its own `sport_id`:** allows a subscription whose sport differs from its plan. Deriving the sport from the plan removes that inconsistency.

## Implementation notes (#19)

- `sports` is the global catalog (`Sport`, translatable `name`, `is_active`, no delete). The slug is set once and never changes, because v2.0 sport modules link to it.
- `club_sport` and `player_sport` are tenant-owned (`BelongsToClub`) with unique `(club_id, sport_id)` and `(player_id, sport_id)`. Deleting a player cascades its enrollments; deleting a sport is restricted.
- Club sports: `POST /club/sports` and `DELETE /club/sports/{sport}` (Owner only, `ClubPolicy::update`), `GET /club/sports` and `GET /sports` (active catalog).
- Player enrollment: `GET|POST /players/{player}/sports`, `DELETE /players/{player}/sports/{sport}` (`PlayerPolicy`).
- Attaching or enrolling is idempotent. An inactive sport cannot be newly attached or enrolled; clubs that already offer it keep it.
- A sport cannot be removed from a club while players are enrolled (`player.sport_has_enrollments`). The checks for membership plans (#20) and training groups (#21) are added with their tables.
