<?php

namespace App\Domain\PlayerSubscription\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\PlayerSubscription\DTOs\CancelPlayerSubscriptionData;
use App\Domain\PlayerSubscription\Enums\SubscriptionStatus;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CancelPlayerSubscriptionAction
{
    public function __construct(private readonly RecordActivityAction $recordActivity) {}

    /**
     * Runs in the current club context. Only an active subscription (including one that has not
     * started yet) can be cancelled. The row is locked so two concurrent cancels cannot both pass.
     */
    public function execute(PlayerSubscription $subscription, CancelPlayerSubscriptionData $data): PlayerSubscription
    {
        return DB::transaction(function () use ($subscription, $data): PlayerSubscription {
            $locked = PlayerSubscription::query()->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            $status = $locked->statusOn($this->today());

            if ($status === SubscriptionStatus::Cancelled) {
                throw new BusinessRuleException('player_subscription.already_cancelled', [], 422);
            }

            if ($status !== SubscriptionStatus::Active) {
                throw new BusinessRuleException('player_subscription.not_cancellable', [], 422);
            }

            $locked->update([
                'cancelled_at' => Carbon::now(),
                'cancelled_by' => $data->cancelledBy,
            ]);

            $this->recordActivity->execute(
                ActivityEvent::PlayerSubscriptionCancelled,
                $locked,
                [
                    'subscription_id' => $locked->id,
                    'player_id' => $locked->player_id,
                    'membership_plan_id' => $locked->membership_plan_id,
                    'cancelled_by' => $data->cancelledBy,
                ],
                null,
                null,
            );

            return $locked;
        });
    }

    private function today(): string
    {
        return Carbon::now(config('subscriptions.timezone'))->startOfDay()->toDateString();
    }
}
