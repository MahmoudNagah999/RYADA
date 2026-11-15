<?php

namespace App\Console\Commands;

use App\Domain\Club\Enums\ClubStatus;
use App\Domain\Club\Models\Club;
use App\Domain\Notification\Jobs\SendPlanExpiryNotificationsJob;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class NotifyPlanExpiry extends Command
{
    protected $signature = 'clubs:notify-plan-expiry {--club= : Only dispatch for this club id}';

    protected $description = 'Queue the plan-expiry reminders for the clubs whose SaaS plan ends soon';

    /**
     * Only the ids are read here; the job carries one id and the Action loads everything else
     * inside the club context. Scheduling lives in routes/console.php (ADR-010).
     */
    public function handle(): int
    {
        $clubId = $this->option('club');

        if ($clubId !== null && $clubId !== '') {
            return $this->dispatchForClub((int) $clubId);
        }

        $dispatched = 0;

        $this->dueQuery()->chunkById(100, function ($clubs) use (&$dispatched): void {
            foreach ($clubs as $club) {
                SendPlanExpiryNotificationsJob::dispatch($club->id);
                $dispatched++;
            }
        });

        $this->info("Dispatched {$dispatched} plan-expiry reminder job(s).");

        return self::SUCCESS;
    }

    private function dispatchForClub(int $clubId): int
    {
        if (Club::query()->find($clubId) === null) {
            $this->error("Club {$clubId} was not found.");

            return self::FAILURE;
        }

        $club = $this->dueQuery()->whereKey($clubId)->first();

        if ($club === null) {
            $this->info('Dispatched 0 plan-expiry reminder job(s): the club is not due.');

            return self::SUCCESS;
        }

        SendPlanExpiryNotificationsJob::dispatch($club->id);
        $this->info('Dispatched 1 plan-expiry reminder job(s).');

        return self::SUCCESS;
    }

    /**
     * Active clubs on a plan, ending between now and the longest threshold plus one day (the
     * extra day covers a plan ending late today once the date is read in the club timezone).
     *
     * @return Builder<Club>
     */
    private function dueQuery(): Builder
    {
        /** @var list<int> $thresholds */
        $thresholds = config('club_notifications.plan_expiry_reminder_days', [7, 1]);
        $maxDays = $thresholds === [] ? 0 : max($thresholds);
        $now = Carbon::now();

        return Club::query()
            ->where('status', ClubStatus::Active->value)
            ->whereNotNull('plan_id')
            ->whereNotNull('subscription_ends_at')
            ->whereBetween('subscription_ends_at', [$now, $now->copy()->addDays($maxDays + 1)]);
    }
}
