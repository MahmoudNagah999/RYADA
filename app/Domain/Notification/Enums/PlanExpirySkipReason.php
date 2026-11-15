<?php

namespace App\Domain\Notification\Enums;

/**
 * Why a plan-expiry run created no notification. Internal: never stored, never returned by the
 * API, only reported by the Action result (and by its tests).
 */
enum PlanExpirySkipReason: string
{
    case ClubSuspended = 'club_suspended';
    case NoPlan = 'no_plan';
    case NoEndDate = 'no_end_date';
    case PlanExpired = 'plan_expired';
    case NotDue = 'not_due';
    case NoOwner = 'no_owner';
    case AlreadySent = 'already_sent';
}
