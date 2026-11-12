<?php

namespace App\Domain\Payment\Actions;

use App\Domain\Payment\DTOs\DueSubscriptionData;
use App\Domain\Payment\DTOs\ListDueSubscriptionsData;
use App\Domain\PlayerSubscription\Models\PlayerSubscription;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class ListDueSubscriptionsAction
{
    /**
     * Correlated aggregate: sum of non-voided payments of this subscription.
     */
    private const PAID_SQL = '(select coalesce(sum(pay.amount_cents), 0) from payments pay'
        .' where pay.player_subscription_id = player_subscriptions.id and pay.voided_at is null)';

    /**
     * Correlated aggregate: refunds recorded against this subscription's non-voided payments.
     */
    private const REFUNDED_SQL = '(select coalesce(sum(ref.amount_cents), 0) from payment_refunds ref'
        .' inner join payments rpay on rpay.id = ref.payment_id'
        .' where rpay.player_subscription_id = player_subscriptions.id and rpay.voided_at is null)';

    public function __construct(private readonly CalculateSubscriptionBalanceAction $calculate) {}

    /**
     * Runs in the current club context. Every sum is a SQL subquery inside the same
     * statement (no per-row queries). Cancelled subscriptions are always excluded.
     *
     * @return LengthAwarePaginator<int, DueSubscriptionData>
     */
    public function execute(ListDueSubscriptionsData $data): LengthAwarePaginator
    {
        $today = Carbon::now(config('subscriptions.timezone'))->startOfDay()->toDateString();
        $until = Carbon::parse($today)->addDays($data->expiringWithinDays)->toDateString();

        $query = PlayerSubscription::query()
            ->with(['player', 'membershipPlan'])
            ->select('player_subscriptions.*')
            ->selectRaw(self::PAID_SQL.' as paid_cents')
            ->selectRaw(self::REFUNDED_SQL.' as refunded_cents')
            ->whereNull('player_subscriptions.cancelled_at');

        match ($data->type) {
            'expiring' => $query
                ->where('player_subscriptions.ends_on', '>', $today)
                ->where('player_subscriptions.ends_on', '<=', $until),
            'unpaid' => $query->whereRaw($this->unpaidSql()),
            default => $query->where(function ($group) use ($today, $until): void {
                $group->where(function ($expiring) use ($today, $until): void {
                    $expiring->where('player_subscriptions.ends_on', '>', $today)
                        ->where('player_subscriptions.ends_on', '<=', $until);
                })->orWhereRaw($this->unpaidSql());
            }),
        };

        $paginator = $query
            ->orderBy('player_subscriptions.ends_on')
            ->orderBy('player_subscriptions.id')
            ->paginate($data->perPage, ['*'], 'page', $data->page);

        /** @var LengthAwarePaginator<int, DueSubscriptionData> $result */
        $result = $paginator->through(function (PlayerSubscription $subscription): DueSubscriptionData {
            $balance = $this->calculate->execute(
                totalCents: $subscription->total_cents,
                paymentAmounts: [(int) $subscription->getAttribute('paid_cents')],
                refundAmounts: [(int) $subscription->getAttribute('refunded_cents')],
                isCancelled: false,
            );

            return new DueSubscriptionData($subscription, $balance);
        });

        return $result;
    }

    /**
     * `outstanding > 0` and the computed status is unpaid or partial. A fully refunded
     * subscription has an outstanding amount but its status is `refunded`, so it is excluded.
     */
    private function unpaidSql(): string
    {
        $net = '('.self::PAID_SQL.' - '.self::REFUNDED_SQL.')';

        return 'player_subscriptions.total_cents > '.$net
            .' and (('.$net.') > 0 or '.self::REFUNDED_SQL.' = 0)';
    }
}
