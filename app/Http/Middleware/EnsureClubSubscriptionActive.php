<?php

namespace App\Http\Middleware;

use App\Domain\Shared\Exceptions\BusinessRuleException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureClubSubscriptionActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $club = $request->user()?->club;

        // A user without a club is rejected by the `tenant` middleware.
        if ($club !== null) {
            if ($club->isSuspended()) {
                throw new BusinessRuleException('club.suspended', [], 403);
            }

            if ($club->hasExpiredSubscription()) {
                throw new BusinessRuleException('subscription.expired', [], 402);
            }
        }

        return $next($request);
    }
}
