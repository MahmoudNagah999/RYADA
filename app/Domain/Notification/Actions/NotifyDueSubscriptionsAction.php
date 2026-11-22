<?php

namespace App\Domain\Notification\Actions;

use App\Domain\Club\Models\Club;
use App\Domain\Notification\DTOs\NotifyDueSubscriptionsResult;
use App\Domain\Notification\DTOs\PendingNotification;
use App\Domain\Notification\Notifications\SubscriptionExpiringNotification;
use App\Domain\Notification\Notifications\SubscriptionUnpaidNotification;
use App\Domain\Notification\Support\ExistingDedupKeys;
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
        private readonly LoadExistingDedupKeysAction $loadExistingDedupKeys,
    ) {}

    /**
     * Runs inside the club context. Idempotent: a recipient that already holds the same
     * dedup key gets nothing, so running it twice in a row creates no new rows.
     */
    public function execute(Club $club): NotifyDueSubscriptionsResult
    {
        return $this->currentClub->runAs($club, function () use ($club): NotifyDueSubscriptionsResult {
            return DB::transaction(function () use ($club): NotifyDueSubscriptionsResult {
                // One recipient resolution per run, never one per subscription.
                $recipients = $this->resolveRecipients->execute($club);
                $existing = new ExistingDedupKeys;
                $today = Carbon::now(config('subscriptions.timezone'))->startOfDay();

                return new NotifyDueSubscriptionsResult(
                    expiring: $this->notifyExpiring(
                        $recipients,
                        $existing,
                        (int) config('club_notifications.expiring_within_days'),
                        $today,
                    ),
                    unpaid: $this->notifyUnpaid(
                        $recipients,
                        $existing,
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
    private function notifyExpiring(
        Collection $recipients,
        ExistingDedupKeys $existing,
        int $expiringWithinDays,
        Carbon $today,
    ): int {
        $todayString = $today->toDateString();

        return $this->eachDueSubscriptionPage(
            'expiring',
            $expiringWithinDays,
            $recipients,
            $existing,
            function (DueSubscriptionData $due) use ($todayString): PendingNotification {
                $subscription = $due->subscription;
                $endsOn = $subscription->ends_on->toDateString();

                return new PendingNotification(
                    dedupKey: NotificationSchedule::expiringDedupKey($subscription->id, $endsOn),
                    notification: new SubscriptionExpiringNotification(
                        subscriptionId: $subscription->id,
                        playerId: $subscription->player_id,
                        playerName: $subscription->player->name,
                        planName: $subscription->membershipPlan->name,
                        endsOn: $endsOn,
                        daysLeft: NotificationSchedule::daysLeft($todayString, $endsOn),
                    ),
                );
            },
        );
    }

    /**
     * @param  Collection<int, User>  $recipients
     * @return int number of notifications created
     */
    private function notifyUnpaid(
        Collection $recipients,
        ExistingDedupKeys $existing,
        Carbon $today,
        int $graceDays,
    ): int {
        $todayString = $today->toDateString();
        // Compared as `Y-m-d` strings: both sides are calendar dates, so no timezone shifts them.
        $graceThreshold = $today->copy()->subDays($graceDays)->toDateString();

        return $this->eachDueSubscriptionPage(
            'unpaid',
            0,
            $recipients,
            $existing,
            function (DueSubscriptionData $due) use ($today, $todayString, $graceThreshold): ?PendingNotification {
                $subscription = $due->subscription;

                // A subscription that just started is not chased before the grace period ends.
                if ($subscription->starts_on->toDateString() > $graceThreshold) {
                    return null;
                }

                return new PendingNotification(
                    dedupKey: NotificationSchedule::unpaidDedupKey($subscription->id, $today),
                    notification: new SubscriptionUnpaidNotification(
                        subscriptionId: $subscription->id,
                        playerId: $subscription->player_id,
                        playerName: $subscription->player->name,
                        planName: $subscription->membershipPlan->name,
                        outstandingCents: $due->balance->outstandingCents,
                        currency: $subscription->currency,
                        today: $todayString,
                    ),
                );
            },
        );
    }

    /**
     * The due list is walked page by page, never loaded whole. The dedup keys of a whole page
     * are resolved in ONE query before any recipient is touched, instead of one `exists()` per
     * subscription and recipient.
     *
     * The page is passed in explicitly so no global request state is touched while a job runs.
     *
     * @param  Collection<int, User>  $recipients
     * @param  Closure(DueSubscriptionData): ?PendingNotification  $build  `null` means "skip"
     * @return int number of notifications created
     */
    private function eachDueSubscriptionPage(
        string $type,
        int $expiringWithinDays,
        Collection $recipients,
        ExistingDedupKeys $existing,
        Closure $build,
    ): int {
        $pageSize = (int) config('club_notifications.page_size', 100);
        $page = 1;
        $created = 0;

        do {
            /** @var LengthAwarePaginator<int, DueSubscriptionData> $paginator */
            $paginator = $this->listDueSubscriptions->execute(
                new ListDueSubscriptionsData($type, $expiringWithinDays, $pageSize, $page),
            );

            /** @var list<PendingNotification> $candidates */
            $candidates = [];

            foreach ($paginator->items() as $due) {
                $pending = $build($due);

                if ($pending !== null) {
                    $candidates[] = $pending;
                }
            }

            if ($candidates !== []) {
                /** @var list<int> $recipientIds */
                $recipientIds = $recipients->modelKeys();
                /** @var list<string> $dedupKeys */
                $dedupKeys = array_map(
                    static fn (PendingNotification $pending): string => $pending->dedupKey,
                    $candidates,
                );

                $existing->merge($this->loadExistingDedupKeys->execute($recipientIds, $dedupKeys));

                foreach ($candidates as $pending) {
                    $created += $this->sendOnce($recipients, $existing, $pending);
                }
            }

            $page++;
        } while ($paginator->hasMorePages());

        return $created;
    }

    /**
     * A key sent earlier in the same run is added to the set, so a later occurrence is skipped
     * exactly like a stored row would have skipped it.
     *
     * @param  Collection<int, User>  $recipients
     * @return int number of notifications created
     */
    private function sendOnce(Collection $recipients, ExistingDedupKeys $existing, PendingNotification $pending): int
    {
        $created = 0;

        foreach ($recipients as $recipient) {
            $recipientId = (int) $recipient->getKey();

            if ($existing->contains($recipientId, $pending->dedupKey)) {
                continue;
            }

            $recipient->notify($pending->notification);
            $existing->add($recipientId, $pending->dedupKey);
            $created++;
        }

        return $created;
    }
}
