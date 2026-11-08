<?php

namespace App\Domain\PlayerSubscription\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Membership\Actions\CalculateMembershipAmountAction;
use App\Domain\Membership\Models\MembershipPlan;
use App\Domain\Player\Models\Player;
use App\Domain\PlayerSubscription\DTOs\SubscribePlayerData;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Sport\Models\PlayerSport;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscribePlayerAction
{
    public function __construct(
        private readonly CalculateMembershipAmountAction $calculateAmount,
        private readonly RecordActivityAction $recordActivity,
    ) {}

    /**
     * Runs in the current club context. Locks the player row so concurrent requests serialize,
     * then checks the plan (active), the enrollment and the same-plan overlap. The sport comes
     * from the plan; price, currency and duration are snapshotted at subscription time.
     */
    public function execute(SubscribePlayerData $data): PlayerSubscription
    {
        return DB::transaction(function () use ($data): PlayerSubscription {
            $today = $this->today();

            $player = Player::query()->whereKey($data->playerId)->lockForUpdate()->firstOrFail();

            $plan = MembershipPlan::query()->findOrFail($data->membershipPlanId);

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

            $startsOn = $data->startsOn ?? $today;
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
            ]);

            $this->recordActivity->execute(
                ActivityEvent::PlayerSubscriptionCreated,
                $subscription,
                [
                    'subscription_id' => $subscription->id,
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
