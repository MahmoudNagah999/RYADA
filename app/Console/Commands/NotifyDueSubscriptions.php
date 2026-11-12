<?php

namespace App\Console\Commands;

use App\Domain\Club\Models\Club;
use App\Domain\Notification\Jobs\SendDueSubscriptionNotificationsJob;
use Illuminate\Console\Command;

class NotifyDueSubscriptions extends Command
{
    protected $signature = 'club:notify-due {--club= : Only dispatch for this club id}';

    protected $description = 'Queue the due-subscription notifications for every club';

    /**
     * Scheduling is out of scope here (issue #32); the command only dispatches the jobs.
     */
    public function handle(): int
    {
        $clubId = $this->option('club');

        if ($clubId !== null && $clubId !== '') {
            $club = Club::query()->find((int) $clubId);

            if ($club === null) {
                $this->error("Club {$clubId} was not found.");

                return self::FAILURE;
            }

            SendDueSubscriptionNotificationsJob::dispatch($club->id);
            $this->info('Dispatched 1 notification job.');

            return self::SUCCESS;
        }

        $dispatched = 0;

        Club::query()->chunkById(100, function ($clubs) use (&$dispatched): void {
            foreach ($clubs as $club) {
                SendDueSubscriptionNotificationsJob::dispatch($club->id);
                $dispatched++;
            }
        });

        $this->info("Dispatched {$dispatched} notification job(s).");

        return self::SUCCESS;
    }
}
