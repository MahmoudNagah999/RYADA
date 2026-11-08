<?php

namespace App\Domain\PlayerSubscription\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Membership\Actions\CalculateMembershipAmountAction;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\DTOs\RenewPlayerSubscriptionData;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Sport\Models\PlayerSport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class RenewPlayerSubscriptionAction
{
    public function __construct(
        private readonly CalculateMembershipAmountAction $calculateAmount,
        private readonly RecordActivityAction $recordActivity,
    ) {}

    /**
     * Runs in the current club context. Only a non-cancelled subscription can be renewed. The new
     * period starts at max(previous ends_on, today) and uses the plan's CURRENT price, currency
     * and duration; the previous row keeps its own snapshot. The same overlap and enrollment
     * checks as subscribe apply, and the player row is locked to serialize concurrent requests.
     */
    public function execute(PlayerSubscription $previous, RenewPlayerSubscriptionData $data): PlayerSubscription
    {
        return DB::transaction(function () use ($previous, $data): PlayerSubscription {
            $today = $this->today();

            $previous = PlayerSubscription::query()->whereKey($previous->id)->lockForUpdate()->firstOrFail();

            if ($previous->isCancelled()) {
                throw new BusinessRuleException('player_subscription.cannot_renew_cancelled', [], 422);
            }

            $player = Player::query()->whereKey($previous->player_id)->lockForUpdate()->firstOrFail();

            $plan = MembershipPlan::query()->findOrFail($previous->membership_plan_id);

            if (! $plan->is_active) {
                throw new BusinessRuleException('player_subscription.plan_inactive', [], 422);
            }

            $enrolled = PlayerSport::query()
                ->where('player_id', $player->id)
                ->where('sport_id', $plan->sport_id)
                ->exists();

            if (! $enrolled) {
                throw new BusinessRuleException('player_subscription.player_not_enrolled', [], 422);
            }

            $startsOn = max($previous->ends_on->toDateString(), $today);
            $endsOn = Carbon::parse($startsOn)->addMonthsNoOverflow($plan->duration_months)->toDateString();

            $overlaps = PlayerSubscription::query()
                ->where('player_id', $player->id)
                ->where('membership_plan_id', $plan->id)
                ->whereNull('cancelled_at')
                ->where('starts_on', '<', $endsOn)
                ->where('ends_on', '>', $startsOn)
                ->exists();

            if ($overlaps) {
                throw new BusinessRuleException('player_subscription.overlap', [], 422);
            }

            $amount = $this->calculateAmount->execute($plan->price_cents, $data->discount);

            $subscription = PlayerSubscription::query()->create([
                'player_id' => $player->id,
                'membership_plan_id' => $plan->id,
                'starts_on' => $startsOn,
                'ends_on' => $endsOn,
                'price_cents' => $plan->price_cents,
                'currency' => $plan->currency,
                'duration_months' => $plan->duration_months,
                'discount_type' => $data->discount?->type,
                'discount_value' => $data->discount?->value,
                'discount_cents' => $amount->discountCents,
                'total_cents' => $amount->totalCents,
                'renewed_from_id' => $previous->id,
            ]);

            $this->recordActivity->execute(
                ActivityEvent::PlayerSubscriptionRenewed,
                $subscription,
                [
                    'subscription_id' => $subscription->id,
                    'renewed_from_id' => $previous->id,
                    'player_id' => $player->id,
                    'membership_plan_id' => $plan->id,
                    'starts_on' => $startsOn,
                    'ends_on' => $endsOn,
                    'price_cents' => $plan->price_cents,
                    'total_cents' => $amount->totalCents,
                ],
                null,
                null,
            );

            return $subscription;
        });
    }

    private function today(): string
    {
        return Carbon::now(config('subscriptions.timezone'))->startOfDay()->toDateString();
    }
}
