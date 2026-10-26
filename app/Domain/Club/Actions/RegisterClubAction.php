<?php

namespace App\Domain\Club\Actions;

use App\Domain\Auth\DTOs\LoginResult;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\DTOs\RegisterClubData;
use App\Domain\Club\Models\Club;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RegisterClubAction
{
    public function __construct(private readonly CreateClubAction $createClub) {}

    public function execute(RegisterClubData $data): LoginResult
    {
        return DB::transaction(function () use ($data) {
            $club = $this->createClub->execute($data->clubName);

            $user = new User([
                'name' => $data->ownerName,
                'email' => $data->email,
                'password' => $data->password,
                'locale' => $data->locale,
            ]);
            $user->club()->associate($club);
            $user->save();

            $this->assignOwnerRole($user, $club);

            $token = $user->createToken('api', ['*'])->plainTextToken;

            return new LoginResult($user, $token, ['*']);
        });
    }

    private function assignOwnerRole(User $user, Club $club): void
    {
        $previousTeamId = getPermissionsTeamId();

        setPermissionsTeamId($club->id);

        try {
            $user->assignRole(RoleName::Owner->value);
        } finally {
            setPermissionsTeamId($previousTeamId);
        }
    }
}
