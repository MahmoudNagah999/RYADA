# Performance: query and index review (#33)

This document records the fifth and last PR of issue #33. It is an evidence-based index review:
every index added here has a before/after `EXPLAIN`, an `EXPLAIN ANALYZE` actual time and a
measured write cost. Everything that could not be fixed with an index is listed, with the
reason, so a later query-rewrite PR can pick it up.

> **Principle:** no index without evidence. Every index slows writes and costs disk. "It might
> help" is not a reason. Doing nothing is a valid, documented outcome.

## Environment

- MySQL **8.0.46** (InnoDB) on Windows through Herd, time zone `Africa/Cairo`.
- The test suite keeps running on SQLite `:memory:` (`phpunit.xml`); the new migrations use only
  portable Schema-builder calls and have a working `down()`.
- PHP 8.4.26, Laravel 13.
- All work was done in a throwaway database, `ryada_perf`. The developer database was not touched.

## Dataset

[`PerformanceSeeder`](../database/seeders/PerformanceSeeder.php) builds 20 clubs. Club `1` is
the big one, clubs `2..20` are small (50–150 players) so `club_id` selectivity is realistic.

| Big club (id 1) | Rows |
|---|---:|
| users (1 owner + 15 staff) | 16 |
| players | 5,000 |
| player_sport | 5,000 |
| membership_plans (3 sports, 4 each) | 12 |
| training_groups | 60 |
| training_group_schedules | 120 |
| training_group_player | 2,400 |
| training_attendances | 100,800 |
| player_subscriptions (mixed states) | 8,000 |
| payments (some voided) | 12,503 |
| payment_refunds | 458 |
| notifications (owner + staff, mixed read) | 2,000 |
| staff_invitations | 3 |
| activity_log | 20,000 |

Across all 20 clubs: 3,900 notifications and 23,800 activity rows.

The data is deterministic for a given day: `mt_srand(33)` is set, and all dates are relative to
"today", so the shape and the plans stay stable while the calendar dates move. A full seed takes
about **84 seconds** on the reference machine (`PerformanceSeeder finished in 81.2 s`, 84.2 s
including process start-up).

### Rebuild it

PowerShell, throwaway database only:

```powershell
& 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe' -h 127.0.0.1 -P 3306 -u root -proot `
  -e "DROP DATABASE IF EXISTS ryada_perf; CREATE DATABASE ryada_perf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

$env:DB_CONNECTION='mysql'; $env:DB_DATABASE='ryada_perf'
$env:CACHE_STORE='array'; $env:QUEUE_CONNECTION='sync'; $env:SESSION_DRIVER='array'

php artisan migrate:fresh --force
php artisan db:seed --class=PerformanceSeeder
```

`PerformanceSeeder` is not in `DatabaseSeeder`, refuses to run when `app()->isProduction()`, and
refuses to run on a non-empty database.

## Methodology

1. **Capture the real SQL.** A throwaway script booted the app, logged in as the big club's owner
   over the real API (`POST /api/v1/auth/login`), and called every endpoint in scope through the
   HTTP kernel. A `DB::listen` listener recorded `$query->toRawSql()` for each request. No SQL was
   hand-written. Write endpoints (renew, payment, attendance upsert, create player) ran inside one
   outer transaction that was rolled back before the plans were measured and explained.
2. **Explain.** For every captured `SELECT` touching a large table, the script ran
   `EXPLAIN <sql>` and `EXPLAIN ANALYZE <sql>` on MySQL 8.0.46. The plan columns below are
   `type`, `key`, `rows` and `Extra`; the actual time is the largest `actual time` in the
   `EXPLAIN ANALYZE` tree.
3. **Write cost.** 20,000-row inserts in one transaction, after a warm-up, median of three runs.
   The benchmark temporarily sets `innodb_flush_log_at_trx_commit=2` to remove the Windows fsync
   noise (a 1,000-row probe varied from 200 to 750 ms on the same data); every insert is rolled
   back, so nothing is committed. The setting is restored to `1` afterwards.

The capture and write-cost scripts were throwaway and are not committed; the commands to repeat
the analysis are in [How to repeat](#how-to-repeat).

## Verdict table

`key=-` means no index was used. `filesort` means `Using filesort`; `temporary` means
`Using temporary`. Times are milliseconds from `EXPLAIN ANALYZE`.

### Players

| Query | Before | Before ms | After | After ms | Verdict |
|---|---|---|---:|---|---:|---|
| `GET /players` default (`name, id`) | `players_club_id_name_index`, 3,362 rows | 1.00 | same | 1.00 | **OK** |
| `sort=name` | `players_club_id_name_index` | 0.91 | same | 0.91 | **OK** |
| `sort=-name` | unique club/national-id index + filesort | 36.50 | same | 36.50 | **NOT AN INDEX PROBLEM** – `name DESC, id ASC` mixes directions |
| `sort=birth_date` | `players_club_id_birth_date_index` | 0.52 | same | 0.52 | **OK** |
| `sort=-created_at` | unique index + filesort | 80.40 | same | 80.40 | **NOT AN INDEX PROBLEM** – mixed direction and no `created_at` index |
| `search=Ahmed` count | club scan, `name LIKE '%Ahmed%'` cannot use a B-tree | 43.70 | same | 43.70 | **NOT AN INDEX PROBLEM** – leading wildcard |
| `search=Ahmed` list | `players_club_id_name_index`, stops after 25 | 0.59 | same | 0.59 | **OK** |
| `gender=female` count | unique index, 5,000 rows, gender filtered out of the index | 118.00 | `players_club_id_gender_index`, 2,500 rows, `Using index` | 10.60 | **NEEDS INDEX** – added |
| `gender=female` list | `players_club_id_name_index` | 0.55 | `players_club_id_name_index` | 0.62 | **OK** |
| `age_min/age_max` count | `players_club_id_birth_date_index`, 625 rows | 0.78 | same | 0.76 | **OK** |
| `age_min/age_max` list | `players_club_id_name_index`, index condition | 1.72 | same | 1.18 | **OK** |
| search + gender count | unique index, 5,000 rows | 126.00 | `players_club_id_gender_index`, 2,500 rows | 13.50 | **NEEDS INDEX** – added (gender prefix) |
| search + gender + `-created_at` list | unique index + filesort | 99.50 | gender index + filesort | 78.10 | **NOT AN INDEX PROBLEM** – leading wildcard plus mixed sort |
| deep page (`page=40`, offset 975) | `players_club_id_name_index`, 1,000 index rows | 91.00 | same | 91.00 | **NOT AN INDEX PROBLEM** – offset pagination; keyset pagination is the fix |

### Player subscriptions

| Query | Before | Before ms | After | After ms | Verdict |
|---|---|---|---:|---|---:|---|
| default `-starts_on` | club/player index + filesort | 82.30 | same | 82.30 | **NOT AN INDEX PROBLEM** – mixed direction |
| `player_id` filter | `player_subscriptions_player_id_foreign`, 1 row | 0.09 | same | 0.09 | **OK** |
| `membership_plan_id` filter | `(club_id, membership_plan_id)`, 665 rows | 5.62 | same | 5.62 | **OK** |
| `status=active` | club/player index, 5,298 rows + filesort | 218.00 | same | 218.00 | **NOT AN INDEX PROBLEM** – mixed sort |
| `status=expired` | same, 5,298 rows + filesort | 87.30 | same | 87.30 | **NOT AN INDEX PROBLEM** |
| `status=cancelled` | same, 5,298 rows + filesort | 203.00 | same | 203.00 | **NOT AN INDEX PROBLEM** |
| `sort=starts_on` | club/player index + filesort | 269.00 | same | 269.00 | **NOT AN INDEX PROBLEM** |
| `sort=-starts_on` | club/player index + filesort | 208.00 | same | 208.00 | **NOT AN INDEX PROBLEM** |
| `sort=ends_on` | `(club_id, ends_on)` | 2.87 | same | 2.87 | **OK** |
| `due?type=expiring` count/list | `(club_id, ends_on)` range, 927 rows | 39.70 / 0.56 | same | 39.70 / 0.56 | **OK** |
| `due?type=unpaid` count | club index, 5,298 rows, correlated `payments`/`payment_refunds` per row | **1,929.00** | same | 1,929.00 | **NOT AN INDEX PROBLEM** – computed net amount; needs a rewrite (#84) |
| `due?type=unpaid` list | `(club_id, ends_on)`, stops at 25 | 7.34 | same | 7.34 | **OK** |
| `due?type=all` count | club index + correlated aggregates | **1,930.00** | same | 1,930.00 | **NOT AN INDEX PROBLEM** (#84) |
| `due?type=all` list | `(club_id, ends_on)`, stops at 25 | 9.79 | same | 9.79 | **OK** |
| subscribe overlap `exists` | `player_subscriptions_player_id_foreign`, 1 row | 0.04 | same | 0.04 | **OK** |
| renew overlap `exists` | `player_subscriptions_player_id_foreign`, 1 row | 0.05 | same | 0.05 | **OK** |
| delete player has-subscriptions `exists` | `player_subscriptions_player_id_foreign`, 1 row | 0.04 | same | 0.04 | **OK** |

### Payments

| Query | Before | Before ms | Verdict |
|---|---|---|---:|
| payments of one subscription | `payments_player_subscription_id_foreign`, 3 rows + tiny filesort | 0.12 | **OK** |
| refunds of those payments | `payment_refunds_payment_id_foreign`, 3 rows | 0.04 | **OK** |
| balance paid sum | `payments_player_subscription_id_foreign` | 0.06 | **OK** |
| balance refunded sum | `payments` FKs + `payment_refunds_payment_id_foreign` | 0.08 | **OK** |
| `NextDocumentNumberAction` | `document_sequences_club_id_kind_unique`, const | 0.00 | **OK** |

### Training

| Query | Before | Before ms | After | After ms | Verdict |
|---|---|---|---:|---|---:|---|
| `GET /training-groups` | `(club_id, sport_id, name)` unique | 0.53 | same | 0.53 | **OK** |
| `GET /training-groups/{id}/players` | `training_group_player_training_group_id_player_id_unique`, 40 rows | 0.17 | same | 0.17 | **OK** |
| `GET .../attendance?date=...` | unique key scans all 1,680 rows of the group and filters `date` | 1.53 | `training_attendances_club_id_group_date_index`, 40 rows | 0.20 | **NEEDS INDEX** – added |
| attendance upsert lookup | unique `(group, player, date)`, 2 rows | 0.07 | same | 0.07 | **OK** |

### Staff

| Query | Before | Before ms | Verdict |
|---|---|---|---:|
| `GET /staff` | `users_club_id_foreign`, 16 rows + tiny filesort | 0.20 | **OK** |
| `GET /staff?role=coach` | roles unique + `model_has_roles` primary, 5 users | 0.42 | **OK** |
| `GET /staff/invitations` | `staff_invitations_pending_email_unique` prefix, 3 rows | 0.09 | **OK** |

### Notifications

| Query | Before | Before ms | Verdict |
|---|---|---|---:|
| newest first | morph index, 125 rows + filesort | 1.86 | **OK** |
| `?unread=1` | `(type, id, read_at)`, 100 rows + filesort | 0.96 | **OK** |
| unread count | `(type, id, read_at)`, covering | 0.19 | **OK** |
| read-all update | `(type, id, read_at)` | — | **OK** |
| batched dedup lookup (`data->dedup_key IN (...)`) | full scan, 3,846 rows | 14.5–50.3 | **NOT AN INDEX PROBLEM** – see below |

### Create player

| Query | Before | Before ms | Verdict |
|---|---|---|---:|
| national-id uniqueness `exists` | `players_club_id_national_id_unique`, const | 0.00 | **OK** |
| plan-limit `COUNT(players)` | unique club/national-id index, covering | 4.25 | **OK** |

### Filament admin (no current club)

| Query | Before | Before ms | After | After ms | Verdict |
|---|---|---|---:|---|---:|---|
| clubs list | table scan of 20 rows + filesort | 0.19 | same | 0.19 | **OK** – table is tiny |
| clubs list, status filter | `clubs_status_index` + 20-row filesort | 0.15 | same | 0.15 | **OK** |
| activity list (newest first) | full scan 23,577 rows + filesort | **249.00** | `activity_log_created_at_index`, 25 rows backward | 0.24 | **NEEDS INDEX** – added |
| activity list, event filter | full scan 23,577 rows + filesort | 160.00 | created_at index backward + filter | 1.84 | **NEEDS INDEX** – added (same index) |
| activity count, event filter | full scan 23,577 rows | 20.70 | full scan (still) | 20.7–42.9 | **NOT AN INDEX PROBLEM** – a `(event, created_at)` index would violate club_id-first and add a second index to an append-heavy table |
| activity list, club filter | `(club_id, created_at)` backward | 0.24 | same | 0.24 | **OK** |

## New indexes and their write cost

| Index | Table | Read before → after | Write cost (20,000 inserts, warm median) |
|---|---|---|---|
| `players_club_id_gender_index` (`club_id, gender`) | players | count 118 ms → **10.6 ms**; combined count 126 ms → **13.5 ms** | 3,562 ms → 3,522 ms (−1.1%, within noise) |
| `training_attendances_club_id_group_date_index` (`club_id, training_group_id, date`) | training_attendances | sheet 1.53 ms / 1,680 rows → **0.20 ms / 40 rows** | 7,856 ms → 6,466 ms (within noise; no measurable cost) |
| `activity_log_created_at_index` (`created_at`) | activity_log | list 249 ms → **0.24 ms**; event-filtered list 160 ms → **1.84 ms** | 3,926 ms → 4,421 ms (**+12.6%**) |

The `activity_log` index is the one deliberate non-tenant index. `activity_log` has a nullable
`club_id`, is platform-wide, and the super-admin lists it with no current club. The existing
`(club_id, created_at)` already serves the club-filtered view. The +12.6% write cost is stated
above; it buys a 1,000× faster default admin page.

Migrations:

- [`2026_11_22_100001_add_players_gender_index.php`](../database/migrations/2026_11_22_100001_add_players_gender_index.php)
- [`2026_11_22_100002_add_training_attendances_group_date_index.php`](../database/migrations/2026_11_22_100002_add_training_attendances_group_date_index.php)
- [`2026_11_22_100003_add_activity_log_created_at_index.php`](../database/migrations/2026_11_22_100003_add_activity_log_created_at_index.php)

## Deliberately not changed

- **`name LIKE '%Ahmed%'` (`GET /players?search=`).** A B-tree index cannot serve a leading
  wildcard. The count scans the 5,000 players of the club (43.7 ms). Options for later: a
  `FULLTEXT` index on `name` (MySQL-only, needs a different query), or a prefix search
  (`name LIKE 'Ahmed%'`, which the existing `(club_id, name)` index can serve). Both are query or
  product changes, not an index added here. The `phone LIKE 'Ahmed%'` branch could use an index,
  but the `OR` with a leading-wildcard `name` makes the whole predicate non-indexable.
- **Mixed sort directions.** `ORDER BY name DESC, id ASC` and `ORDER BY starts_on DESC, id ASC`
  (and the `-created_at` variant) mix ascending and descending columns. A `(club_id, name)` or
  `(club_id, starts_on)` index cannot serve the tie-breaker, so MySQL filesorts: `-name` 36.5 ms,
  `-created_at` 80.4 ms, subscriptions default 82.3 ms, `sort=starts_on` up to 269 ms. The fix is a
  query rewrite (make the tie-breaker direction match, or use keyset pagination). A MySQL
  descending index (`name DESC, id ASC`) would work but is not portable to SQLite through the
  Schema builder, so it was not added.
- **`GET /player-subscriptions/due?type=unpaid|all`.** The unpaid predicate compares
  `total_cents` with a correlated `SUM(payments) - SUM(refunds)` for every non-cancelled
  subscription. The count takes **1.9 s** on 8,000 subscriptions. No index can change a computed
  predicate. Options: a derived table / join that aggregates once, or a maintained `paid_cents`
  column. This is recorded for #84 (query rewrite), not fixed here.
- **Batched notification dedup.** `where data->dedup_key IN (...)` on 3,846 rows takes
  14.5–50.3 ms with a full scan. The existing `(notifiable_type, notifiable_id, read_at)` index
  narrows each recipient to ~125 rows, but the optimizer prefers the full scan at this size. A
  functional index on `json_unquote(json_extract(data, '$.dedup_key'))` (or a generated column)
  is MySQL-only and the migration must also run on SQLite, so it was not added. At 3,846 rows the
  cost does not justify a schema change; revisit if a club's notification volume grows by orders
  of magnitude.
- **Deep offset pagination.** `page=40` scans 1,000 index entries and takes 91 ms because of the
  row lookups. Keyset pagination (where `name > last_name`) is the fix; an index cannot remove the
  offset work.
- **Activity count filtered by event.** The list is fixed by the `created_at` index, but the
  `COUNT(*) WHERE event = ?` still scans the table (20.7–42.9 ms). A composite `(event, created_at)`
  would fix it but would be a non-club-first composite index on an append-heavy table; the
  super-admin page is low-frequency, so it was left alone.
- **`GET /players` gender list** keeps using `(club_id, name)` for ordering; only the count needed
  the new gender index. The new index is narrow and covering for the count.

## Redundant or unused index report (nothing dropped)

These are reported with evidence so a later PR can decide. **No index was dropped in this PR.**

Left-prefix redundancy (the shorter index is fully covered by the first columns of the longer one):

| Redundant index | Covered by | Notes |
|---|---|---|
| `notifications_notifiable_type_notifiable_id_index` (from `morphs`) | `notifications_notifiable_type_notifiable_id_read_at_index` | The explicit three-column index is a strict superset. The two-column index still served the newest-first list in the capture; dropping it would need a re-check. |
| `roles_team_foreign_key_index` (`club_id`) | `roles_club_id_name_guard_name_unique` | Spatie Teams-mode index. |
| `model_has_roles_team_foreign_key_index` (`club_id`) | primary `(club_id, role_id, model_id, model_type)` | Spatie Teams-mode index; the primary also starts with `club_id`. |
| `model_has_permissions_team_foreign_key_index` (`club_id`) | primary `(club_id, permission_id, model_id, model_type)` | Spatie Teams-mode index. |

Not used by any captured query (kept because the table is small or the endpoint is out of scope):

| Index | Why it is unused in this review |
|---|---|
| `sports_is_active_index` | The sports catalog is cached in Redis (`SportCatalog`); the captured catalog query does not filter `is_active`. The table has 4 rows. |
| `staff_invitations_club_id_email_index`, `staff_invitations_club_id_role_index`, `staff_invitations_club_id_expires_at_index` | The invitations list filters `club_id` and uses the `club_id` prefix of `staff_invitations_pending_email_unique`; the role and expiry indexes are not touched by the captured queries. The table is tiny. |
| `training_attendances_player_id_date_index` | The attendance sheet and the upsert use the unique `(group, player, date)` key. A future "attendance history of a player" endpoint would use this index. |
| `activity_log_log_name_index` | The Filament list filters `event`, not `log_name`; a future log-name filter would use it. |

Not redundant (kept because they are used):

- `players_club_id_national_id_unique` — the national-id uniqueness check (const lookup) and the
  cheapest covering index for `COUNT(players)`.
- `payments_player_subscription_id_foreign` and `payment_refunds_payment_id_foreign` — auto-created
  FK indexes, used by every correlated payment/refund aggregate and by the payments list. The
  club-first composites cannot serve these correlated subqueries because they filter by
  `player_subscription_id` / `payment_id` without `club_id`.
- `player_subscriptions_player_id_foreign` — used by the subscribe/renew overlap checks and the
  delete-player `exists` (0.04–0.05 ms).
- `training_attendances_club_id_date_index` — used by the "latest attendance date of a group"
  lookup and any club-wide date range; the new group/date index is narrower.
- `(club_id, created_at)` on `activity_log` — serves the club-filtered admin list (0.24 ms).

## How to repeat

The capture scripts are throwaway (they are not committed). The procedure:

1. Rebuild the dataset with the commands above.
2. Write a small script that boots the app and logs in as the big club's owner:

   ```php
   $app = require __DIR__.'/bootstrap/app.php';
   $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
   // POST /api/v1/auth/login through Illuminate\Contracts\Http\Kernel, then call the
   // endpoints with the returned meta.token.
   ```

3. Register one listener and reset its buffer per request:

   ```php
   DB::listen(fn ($query) => $GLOBALS['captured'][] = $query->toRawSql());
   ```

4. Run `EXPLAIN <sql>` and `EXPLAIN ANALYZE <sql>` for every captured `SELECT`. Skip
   `FOR UPDATE` statements in `EXPLAIN ANALYZE` (they lock rows).
5. For the write cost, insert 20,000 rows into the target table in one transaction, after a
   warm-up, and roll back. Repeat three times and report the median. Use
   `innodb_flush_log_at_trx_commit=2` for the probe and restore it to `1` afterwards.
6. To recreate the throwaway database later:

   ```powershell
   & 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe' -h 127.0.0.1 -P 3306 -u root -proot `
     -e "CREATE DATABASE IF NOT EXISTS ryada_perf CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

   To remove it:

   ```powershell
   & 'C:\Program Files\MySQL\MySQL Server 8.0\bin\mysql.exe' -h 127.0.0.1 -P 3306 -u root -proot `
     -e "DROP DATABASE IF EXISTS ryada_perf;"
   ```

## The #33 series

| PR | Outcome |
|---|---|
| 1 | Test suite speed: **350 s → ~178 s**. |
| 2 | Lazy-loading guard always on; production uses a logging handler instead of throwing. |
| 3 | Query-budget tests. One proven N+1 remains: `RecordTrainingAttendanceAction` inserts one row per record, tracked in [#84](https://github.com/MahmoudNagah999/RYADA/issues/84). |
| 4 | Batched notification dedup: **41 → 11 queries** on a rerun at 12 subscriptions. |
| 5 | This index review: three indexes backed by `EXPLAIN`/`EXPLAIN ANALYZE`, measured write costs, and the query-rewrite backlog above. |

The remaining known issue from this review is the `type=unpaid|all` due-subscriptions count
(1.9 s). It is not an index problem and is tracked with the query rewrite work in
[#84](https://github.com/MahmoudNagah999/RYADA/issues/84).
