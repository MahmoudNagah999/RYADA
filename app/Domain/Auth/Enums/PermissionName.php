<?php

namespace App\Domain\Auth\Enums;

enum PermissionName: string
{
    case ClubView = 'club.view';
    case ClubUpdate = 'club.update';
    case StaffView = 'staff.view';
    case StaffManage = 'staff.manage';
    case PlayersView = 'players.view';
    case PlayersManage = 'players.manage';
    case TournamentsView = 'tournaments.view';
    case TournamentsManage = 'tournaments.manage';
    case ScoresSubmit = 'scores.submit';
    case MembershipPlansView = 'membership_plans.view';
    case MembershipPlansManage = 'membership_plans.manage';
    case TrainingGroupsView = 'training_groups.view';
    case TrainingGroupsManage = 'training_groups.manage';
    case AttendanceView = 'attendance.view';
    case AttendanceRecord = 'attendance.record';
    case PlayerSubscriptionsView = 'player_subscriptions.view';
    case PlayerSubscriptionsManage = 'player_subscriptions.manage';
    case PaymentsView = 'payments.view';
    case PaymentsManage = 'payments.manage';
    case PaymentsRefund = 'payments.refund';
}
