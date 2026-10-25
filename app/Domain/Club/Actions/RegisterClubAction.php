<?php

namespace App\Domain\Club\Actions;

use App\Domain\Auth\Actions\SyncClubRolesAction;
use App\Domain\Auth\DTOs\LoginResult;
use App\Domain\Auth\Enums\RoleName;
use App\Domain\Club\DTOs\RegisterClubData;
use App\Domain\Club\Models\Club;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RegisterClubAction
{
    public function __construct(private readonly SyncClubRolesAction $syncClubRoles) {}

    public function execute(RegisterClubData $data): LoginResult
    {
        return DB::transaction(function () use ($data) {
            $club = Club::create([
                'name' => $data->clubName,
                'slug' => $this->uniqueSlug($data->clubName),
            ]);

            $user = new User([
                'name' => $data->ownerName,
                'email' => $data->email,
                'password' => $data->password,
                'locale' => $data->locale,
            ]);
            $user->club()->associate($club);
            $user->save();

            $this->syncClubRoles->execute($club);
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

    private function uniqueSlug(string $name): string
    {
        // Arabic names are transliterated by Str::slug; fall back if nothing is left.
        $base = Str::slug($name) ?: 'club';
        $slug = $base;

        while (Club::query()->where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(4));
        }

        return $slug;
    }
}
