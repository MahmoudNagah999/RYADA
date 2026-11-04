<?php

namespace App\Domain\Player\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Club\Models\Club;
use App\Domain\Player\DTOs\CreatePlayerData;
use App\Domain\Player\Models\Player;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use App\Domain\Subscription\Actions\EnsureWithinPlanLimitAction;
use App\Domain\Subscription\Enums\PlanLimit;
use Illuminate\Support\Facades\DB;

class CreatePlayerAction
{
    public function __construct(
        private readonly ResolvePlayerBirthDataAction $resolveBirthData,
        private readonly EnsureWithinPlanLimitAction $ensureWithinPlanLimit,
        private readonly RecordActivityAction $recordActivity,
    ) {}

    /**
     * Must run inside a tenant context (CurrentClub set), because Player is scoped by club.
     */
    public function execute(Club $club, CreatePlayerData $data): Player
    {
        [$birthDate, $gender] = $this->resolveBirthData->execute(
            $data->nationalId,
            $data->birthDate,
            $data->gender,
        );

        return DB::transaction(function () use ($club, $data, $birthDate, $gender): Player {
            // Lock the club row so two concurrent requests cannot both pass the plan limit.
            $lockedClub = Club::query()->whereKey($club->id)->lockForUpdate()->firstOrFail();

            if ($data->nationalId !== null
                && Player::query()->where('national_id', $data->nationalId)->exists()) {
                throw new BusinessRuleException('player.national_id_taken', [], 422);
            }

            $this->ensureWithinPlanLimit->execute(
                $lockedClub,
                PlanLimit::Players,
                Player::query()->count(),
            );

            $player = Player::query()->create([
                'name' => $data->name,
                'national_id' => $data->nationalId,
                'birth_date' => $birthDate,
                'gender' => $gender,
                'phone' => $data->phone,
            ]);

            $this->recordActivity->execute(
                ActivityEvent::PlayerCreated,
                $player,
                ['name' => $player->name],
                null,
                null,
            );

            return $player;
        });
    }
}
