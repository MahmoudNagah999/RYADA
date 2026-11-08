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
    case ClubSportAdded = 'club.sport_added';
    case ClubSportRemoved = 'club.sport_removed';
    case PlayerSportEnrolled = 'player.sport_enrolled';
    case PlayerSportUnenrolled = 'player.sport_unenrolled';
    case MembershipPlanCreated = 'membership_plan.created';
    case MembershipPlanUpdated = 'membership_plan.updated';
    case TrainingGroupCreated = 'training_group.created';
    case TrainingGroupUpdated = 'training_group.updated';
    case TrainingGroupPlayerAdded = 'training_group.player_added';
    case TrainingGroupPlayerRemoved = 'training_group.player_removed';
    case TrainingGroupAttendanceRecorded = 'training_group.attendance_recorded';
    case PlayerSubscriptionCreated = 'player_subscription.created';
    case PlayerSubscriptionRenewed = 'player_subscription.renewed';
    case PlayerSubscriptionCancelled = 'player_subscription.cancelled';

    /**
     * Log name = the part before the dot (club, plan, ...).
     */
    public function logName(): string
    {
        return explode('.', $this->value)[0];
    }
}
