# ADR-010: Scheduled tasks and plan-expiry reminders

- Status: Accepted
- Date: 2026-11-15
- Issue: #32

## Context

Two recurring jobs are needed: the due-subscription notifier from #31 (`club:notify-due`, written but never scheduled) and a reminder to a club owner before the club's SaaS plan ends. Both must run on a schedule, in the club business timezone, without sending anything twice when several servers run the scheduler.

The obvious "expire things" job was rejected while designing this. A club's SaaS status is already computed at request time: `EnsureClubSubscriptionActive` reads `clubs.subscription_ends_at` and answers `402 subscription.expired` once the date is past. A player subscription's status is computed from `ends_on` too; only `cancelled_at` is stored. A scheduled job writing a `status` column would duplicate that truth, need its own failure handling, and make the computed value and the stored value disagree whenever the job is late.

## Decision

1. **No job expires anything in the database.** Club plan expiry stays computed by the `subscription` middleware from `clubs.subscription_ends_at`; player subscription status stays computed from `ends_on`. This ADR adds no status column and never writes to `clubs` or `player_subscriptions`.
2. **The new job only reminds before the end date.** `clubs:notify-plan-expiry` queues one `SendPlanExpiryNotificationsJob` per club, which runs `NotifyPlanExpiringAction` inside the club context (`TenantQueuedJob`, ADR-009). The Action writes one notification row (the bell) and nothing else.
3. **Thresholds are days before the end date**, configured by `NOTIFY_PLAN_EXPIRY_DAYS` (default `7,1`, parsed into a sorted list of positive ints, junk ignored). Both "today" and the end date are calendar dates in `config('subscriptions.timezone')`, never in `APP_TIMEZONE` (UTC).
4. **The applicable threshold is the smallest configured one that is `>= days_left`**, and only that one is sent. A missed scheduler day therefore delays a reminder instead of losing it; a club that starts with 3 days left gets only the "7" reminder and then the "1" one; a club that reaches 1 day left with nothing sent gets only the "1" reminder. Nothing is sent once `days_left` exceeds the largest threshold.
5. **Dedup key: `club.plan_expiring:{club_id}:{ends_on}:{threshold}`.** It is built by `NotificationSchedule`, the same pure helper that builds the #31 keys, and the same rule applies: a recipient that already holds the key (read or unread) is skipped. Renewing the plan changes the end date, which changes the key, so a renewed club is reminded again.
6. **Owner only.** The SaaS plan is the owner's money; staff, coaches and referees never see it in the bell. `ResolveNotificationRecipientsAction` takes the roles to include (default `owner` + `staff`, so #31 is unchanged) and the plan reminder passes `[Owner]`.
7. **Nothing after expiry.** A suspended club, a club without a plan (`plan_id` null), a club without `subscription_ends_at` (#14: not restricted) and a club already past its end date are skipped. The expired case matters: the `402` closes the notifications endpoints for that club, so a reminder written after expiry would never be read.
8. **Translation stays at read time.** The row stores `code = club.plan_expiring` plus `ends_on`, `days_left` and `threshold`; no plan or club name (both translatable, both would need extra queries at read time). The bell has no `trans_choice`, so the singular sentence lives in a separate `notifications.club.plan_expiring.one` key, picked by `NotificationCode::translationKey()` from `days_left`.
9. **The scheduler has to run.** `routes/console.php` schedules `club:notify-due` at 08:00 and `clubs:notify-plan-expiry` at 08:05, both `->timezone(config('subscriptions.timezone'))`, `->withoutOverlapping()` and `->onOneServer()`, each named so it can be found in `schedule:list`. Nothing happens unless a cron entry runs `php artisan schedule:run` every minute (the Docker scheduler service arrives in #36).
10. **`onOneServer()` needs the shared cache store.** Both the "one server" and the "no overlap" mutexes are cache locks, so they need Redis (`CACHE_STORE=redis` on the scheduler host, ADR-009). With the `array` store the mutex is per process and the flag silently does nothing; tests assert the flags, not the locking.
11. **Deployment.** The scheduler and the queue workers keep the old code in memory, so a deploy runs `php artisan queue:restart` (Horizon: `horizon:terminate`) after the new code is in place.

## Consequences

- A reminder is a normal bell notification: it needs no mail template, no new endpoint, no new permission, and it is translated per request like every other notification.
- Running the command twice in a day (or twice by mistake) creates no duplicate: the dedup key stops it.
- A club whose owner has no `owner` role gets nothing, which is the same fail-closed behaviour as #31.
- The "days left" of a reminder is a calendar count in Cairo, so a plan ending at 05:00 UTC tomorrow says "1 day" tonight even though the UTC date is still today.
- No audit event is recorded: time passing has no causer, and the notification row itself is the record.
- If the scheduler stops, no reminder is sent and nothing else breaks: statuses are still computed at request time. This is the deliberate trade-off of decision 1.

## Revisit when

- Reminders should reach the owner by mail or push (add a channel to `PlanExpiringNotification`, keep the row as the dedup source).
- Per-club reminder preferences are needed (a column on `clubs`, not on the config).
- The platform wants to auto-suspend expired clubs, which would need an auditable admin action, not a silent job.
