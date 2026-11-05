<?php

namespace App\Domain\Audit\Enums;

enum ActivityEvent: string
{
    case ClubCreated = 'club.created';
    case ClubSuspended = 'club.suspended';
    case ClubActivated = 'club.activated';
    case ClubPlanAssigned = 'club.plan_assigned';
    case ClubProfileUpdated = 'club.profile_updated';
    case PlanCreated = 'plan.created';
    case PlanUpdated = 'plan.updated';
    case ClubLogoUpdated = 'club.logo_updated';
    case ClubLogoRemoved = 'club.logo_removed';
    case StaffInvited = 'staff.invited';
    case StaffInvitationRevoked = 'staff.invitation_revoked';
    case StaffJoined = 'staff.joined';
    case StaffRoleChanged = 'staff.role_changed';
    case StaffRemoved = 'staff.removed';
    case PlayerCreated = 'player.created';
    case PlayerUpdated = 'player.updated';
    case PlayerDeleted = 'player.deleted';
    case SportCreated = 'sport.created';
    case SportUpdated = 'sport.updated';

    /**
     * Log name = the part before the dot (club, plan, ...).
     */
    public function logName(): string
    {
        return explode('.', $this->value)[0];
    }
}
