<?php

namespace App\Domain\Notification\Actions;

use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\Models\Club;
use App\Domain\Notification\DTOs\NotifyPlanExpiringResult;
use App\Domain\Notification\Enums\PlanExpirySkipReason;
use App\Domain\Notification\Notifications\PlanExpiringNotification;
use App\Domain\Notification\Support\ExistingDedupKeys;
use App\Domain\Notification\Support\NotificationSchedule;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reminds the owner, once per configured threshold, that the club's SaaS plan ends soon.
 *
 * Nothing is written to `clubs` or `player_subscriptions`: the plan status stays computed at
 * request time (ADR-010). Running it twice in a row creates nothing new, because the dedup key
 * of a recipient that already holds it is skipped (the rule the #31 notifier already uses).
 */
class NotifyPlanExpiringAction
{
    public function __construct(
        private readonly CurrentClub $currentClub,
        private readonly ResolveNotificationRecipientsAction $resolveRecipients,
        private readonly LoadExistingDedupKeysAction $loadExistingDedupKeys,
    ) {}

    public function execute(Club $club): NotifyPlanExpiringResult
    {
        return $this->currentClub->runAs($club, fn (): NotifyPlanExpiringResult => $this->notify($club));
    }

    private function notify(Club $club): NotifyPlanExpiringResult
    {
        if ($club->isSuspended()) {
            return NotifyPlanExpiringResult::skipped(PlanExpirySkipReason::ClubSuspended);
        }

        if ($club->plan_id === null) {
            return NotifyPlanExpiringResult::skipped(PlanExpirySkipReason::NoPlan);
        }

        $endsAt = $club->subscription_ends_at;

        // The #14 rule: a club without an end date is never restricted, so never reminded.
        if ($endsAt === null) {
            return NotifyPlanExpiringResult::skipped(PlanExpirySkipReason::NoEndDate);
        }

        // The `subscription` middleware answers 402 from this moment on, so the bell is closed
        // and a reminder would never be read.
        if ($club->hasExpiredSubscription()) {
            return NotifyPlanExpiringResult::skipped(PlanExpirySkipReason::PlanExpired);
        }

        // Both dates are calendar dates in the subscriptions timezone, never in UTC (APP_TIMEZONE).
        $timezone = (string) config('subscriptions.timezone');
        $today = Carbon::now($timezone)->startOfDay();
        $endsOn = $endsAt->copy()->setTimezone($timezone)->toDateString();
        $daysLeft = NotificationSchedule::daysLeft($today->toDateString(), $endsOn);

        /** @var list<int> $thresholds */
        $thresholds = config('club_notifications.plan_expiry_reminder_days', [7, 1]);
        $threshold = NotificationSchedule::planExpiryThreshold($daysLeft, $thresholds);

        if ($threshold === null) {
            return NotifyPlanExpiringResult::skipped(PlanExpirySkipReason::NotDue);
        }

        $recipients = $this->resolveRecipients->execute($club, [RoleName::Owner]);

        if ($recipients->isEmpty()) {
            return NotifyPlanExpiringResult::skipped(PlanExpirySkipReason::NoOwner);
        }

        return $this->send(
            $recipients,
            new PlanExpiringNotification(
                clubId: (int) $club->getKey(),
                endsOn: $endsOn,
                daysLeft: $daysLeft,
                threshold: $threshold,
            ),
            $threshold,
        );
    }

    /**
     * One insert per recipient, inside one transaction, exactly like the #31 notifier.
     *
     * The key is the same for every recipient, so it is resolved for all of them in one query
     * instead of one `exists()` per recipient.
     *
     * @param  Collection<int, User>  $recipients
     */
    private function send(
        Collection $recipients,
        PlanExpiringNotification $notification,
        int $threshold,
    ): NotifyPlanExpiringResult {
        $dedupKey = $notification->dedupKey();

        return DB::transaction(function () use ($recipients, $notification, $dedupKey, $threshold): NotifyPlanExpiringResult {
            /** @var list<int> $recipientIds */
            $recipientIds = $recipients->modelKeys();

            $existing = new ExistingDedupKeys;
            $existing->merge($this->loadExistingDedupKeys->execute($recipientIds, [$dedupKey]));

            $created = 0;

            foreach ($recipients as $recipient) {
                $recipientId = (int) $recipient->getKey();

                if ($existing->contains($recipientId, $dedupKey)) {
                    continue;
                }

                $recipient->notify($notification);
                $existing->add($recipientId, $dedupKey);
                $created++;
            }

            return $created === 0
                ? NotifyPlanExpiringResult::skipped(PlanExpirySkipReason::AlreadySent)
                : NotifyPlanExpiringResult::created($created, $threshold);
        });
    }
}
