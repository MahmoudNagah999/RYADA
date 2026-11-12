<?php

namespace App\Domain\Notification\Jobs;

use App\Domain\Notification\Actions\NotifyDueSubscriptionsAction;
use App\Domain\Shared\Tenancy\CurrentClub;
use App\Domain\Shared\Tenancy\Queue\TenantQueuedJob;

/**
 * One job per club. It carries only the club id; the club itself is loaded and made
 * current by the RunsInClubContext middleware.
 */
class SendDueSubscriptionNotificationsJob extends TenantQueuedJob
{
    public function handle(NotifyDueSubscriptionsAction $action, CurrentClub $currentClub): void
    {
        $club = $currentClub->get();

        if ($club === null) {
            return;
        }

        $action->execute($club);
    }
}
