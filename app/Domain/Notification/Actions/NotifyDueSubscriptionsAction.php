<?php

namespace App\Domain\Notification\Actions;

use App\Domain\Club\Models\Club;
use App\Domain\Notification\DTOs\NotifyDueSubscriptionsResult;
use App\Domain\Notification\Notifications\ClubDatabaseNotification;
use App\Domain\Notification\Notifications\SubscriptionExpiringNotification;
use App\Domain\Notification\Notifications\SubscriptionUnpaidNotification;
use App\Domain\Notification\Support\NotificationSchedule;
use App\Domain\Payment\Actions\ListDueSubscriptionsAction;
use App\Domain\Payment\DTOs\DueSubscriptionData;
use App\Domain\Payment\DTOs\ListDueSubscriptionsData;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class NotifyDueSubscriptionsAction
{
    public function __construct(
        private readonly CurrentClub $currentClub,
        private readonly ListDueSubscriptionsAction $listDueSubscriptions,
        private readonly ResolveNotificationRecipientsAction $resolveRecipients,
    ) {}

    /**
     * Runs inside the club context. Idempotent: a recipient that already holds the same
     * dedup key gets nothing, so running it twice in a row creates no new rows.
     */
    public function execute(Club $club): NotifyDueSubscriptionsResult
    {
        return $this->currentClub->runAs($club, function () use ($club): NotifyDueSubscriptionsResult {
            return DB::transaction(function () use ($club): NotifyDueSubscriptionsResult {
                $recipients = $this->resolveRecipients->execute($club);
                $today = Carbon::now(config('subscriptions.timezone'))->startOfDay();

                return new NotifyDueSubscriptionsResult(
                    expiring: $this->notifyExpiring(
                        $recipients,
                        (int) config('club_notifications.expiring_within_days'),
                        $today,
                    ),
                    unpaid: $this->notifyUnpaid(
                        $recipients,
                        $today,
                        (int) config('club_notifications.unpaid_grace_days'),
                    ),
                );
            });
        });
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @return int number of notifications created
     */
    private function notifyExpiring(Collection $recipients, int $expiringWithinDays, Carbon $today): int
    {
        $created = 0;
        $todayString = $today->toDateString();

        $this->eachDueSubscription('expiring', $expiringWithinDays, function (DueSubscriptionData $due) use ($recipients, $todayString, &$created): void {
            $subscription = $due->subscription;
            $endsOn = $subscription->ends_on->toDateString();

            $created += $this->sendOnce(
                $recipients,
                NotificationSchedule::expiringDedupKey($subscription->id, $endsOn),
                new SubscriptionExpiringNotification(
                    subscriptionId: $subscription->id,
                    playerId: $subscription->player_id,
                    playerName: $subscription->player->name,
                    planName: $subscription->membershipPlan->name,
                    endsOn: $endsOn,
                    daysLeft: NotificationSchedule::daysLeft($todayString, $endsOn),
                ),
            );
        });

        return $created;
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @return int number of notifications created
     */
    private function notifyUnpaid(Collection $recipients, Carbon $today, int $graceDays): int
    {
        $created = 0;
        $todayString = $today->toDateString();
        // Compared as `Y-m-d` strings: both sides are calendar dates, so no timezone shifts them.
        $graceThreshold = $today->copy()->subDays($graceDays)->toDateString();

        $this->eachDueSubscription('unpaid', 0, function (DueSubscriptionData $due) use ($recipients, $today, $todayString, $graceThreshold, &$created): void {
            $subscription = $due->subscription;

            // A subscription that just started is not chased before the grace period ends.
            if ($subscription->starts_on->toDateString() > $graceThreshold) {
                return;
            }

            $created += $this->sendOnce(
                $recipients,
                NotificationSchedule::unpaidDedupKey($subscription->id, $today),
                new SubscriptionUnpaidNotification(
                    subscriptionId: $subscription->id,
                    playerId: $subscription->player_id,
                    playerName: $subscription->player->name,
                    planName: $subscription->membershipPlan->name,
                    outstandingCents: $due->balance->outstandingCents,
                    currency: $subscription->currency,
                    today: $todayString,
                ),
            );
        });

        return $created;
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @return int number of notifications created
     */
    private function sendOnce(Collection $recipients, string $dedupKey, ClubDatabaseNotification $notification): int
    {
        $created = 0;

        foreach ($recipients as $recipient) {
            $alreadySent = $recipient->notifications()
                ->where('data->dedup_key', $dedupKey)
                ->exists();

            if ($alreadySent) {
                continue;
            }

            $recipient->notify($notification);
            $created++;
        }

        return $created;
    }

    /**
     * The due list is walked page by page, never loaded whole. The page is passed in
     * explicitly so no global request state is touched while a job runs.
     *
     * @param  Closure(DueSubscriptionData): void  $callback
     */
    private function eachDueSubscription(string $type, int $expiringWithinDays, Closure $callback): void
    {
        $pageSize = (int) config('club_notifications.page_size', 100);
        $page = 1;

        do {
            /** @var LengthAwarePaginator<int, DueSubscriptionData> $paginator */
            $paginator = $this->listDueSubscriptions->execute(
                new ListDueSubscriptionsData($type, $expiringWithinDays, $pageSize, $page),
            );

            foreach ($paginator->items() as $due) {
                $callback($due);
            }

            $page++;
        } while ($paginator->hasMorePages());
    }
}
