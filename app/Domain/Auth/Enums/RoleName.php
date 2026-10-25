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
            ],
            self::Staff => [
                PermissionName::ClubView,
                PermissionName::StaffView,
                PermissionName::PlayersView,
                PermissionName::PlayersManage,
                PermissionName::TournamentsView,
                PermissionName::TournamentsManage,
            ],
            self::Referee => [
                PermissionName::ClubView,
                PermissionName::TournamentsView,
                PermissionName::ScoresSubmit,
            ],
        };
    }
}
