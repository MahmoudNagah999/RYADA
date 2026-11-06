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
}
