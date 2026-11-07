<?php

namespace App\Domain\Auth\Enums;

enum RoleName: string
{
    case Owner = 'owner';
    case Coach = 'coach';
    case Staff = 'staff';
    case Referee = 'referee';

    /**
     * @return list<PermissionName>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => PermissionName::cases(),
            self::Coach => [
                PermissionName::ClubView,
                PermissionName::PlayersView,
                PermissionName::PlayersManage,
                PermissionName::TournamentsView,
                PermissionName::MembershipPlansView,
                PermissionName::TrainingGroupsView,
                PermissionName::AttendanceView,
                PermissionName::AttendanceRecord,
            ],
            self::Staff => [
                PermissionName::ClubView,
                PermissionName::StaffView,
                PermissionName::PlayersView,
                PermissionName::PlayersManage,
                PermissionName::TournamentsView,
                PermissionName::TournamentsManage,
                PermissionName::MembershipPlansView,
                PermissionName::MembershipPlansManage,
                PermissionName::TrainingGroupsView,
                PermissionName::TrainingGroupsManage,
                PermissionName::AttendanceView,
                PermissionName::AttendanceRecord,
            ],
            self::Referee => [
                PermissionName::ClubView,
                PermissionName::TournamentsView,
                PermissionName::ScoresSubmit,
            ],
        };
    }

    /**
     * @return list<self>
     */
    public static function assignableToStaff(): array
    {
        return [
            self::Coach,
            self::Staff,
            self::Referee,
        ];
    }
}
