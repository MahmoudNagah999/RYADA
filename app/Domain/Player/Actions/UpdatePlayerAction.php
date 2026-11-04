<?php

namespace App\Domain\Player\Actions;

use App\Domain\Audit\Actions\RecordActivityAction;
use App\Domain\Audit\Enums\ActivityEvent;
use App\Domain\Player\DTOs\UpdatePlayerData;
use App\Domain\Player\Models\Player;
use App\Domain\Shared\Exceptions\BusinessRuleException;
use Illuminate\Support\Facades\DB;

class UpdatePlayerAction
{
    public function __construct(
        private readonly ResolvePlayerBirthDataAction $resolveBirthData,
        private readonly RecordActivityAction $recordActivity,
    ) {}

    public function execute(Player $player, UpdatePlayerData $data): Player
    {
        [$birthDate, $gender] = $this->resolveBirthData->execute(
            $data->nationalId,
            $data->birthDate,
            $data->gender,
        );

        return DB::transaction(function () use ($player, $data, $birthDate, $gender): Player {
            if ($data->nationalId !== null
                && Player::query()
                    ->where('national_id', $data->nationalId)
                    ->whereKeyNot($player->id)
                    ->exists()) {
                throw new BusinessRuleException('player.national_id_taken', [], 422);
            }

            $before = $this->snapshot($player);

            $player->fill([
                'name' => $data->name,
                'national_id' => $data->nationalId,
                'birth_date' => $birthDate,
                'gender' => $gender,
                'phone' => $data->phone,
            ]);

            $after = $this->snapshot($player);
            $changed = array_keys(array_diff_assoc($after, $before));

            if ($changed === []) {
                return $player;
            }

            $player->save();

            $changes = [];
            foreach (['name', 'birth_date', 'gender'] as $field) {
                if (in_array($field, $changed, true)) {
                    $changes[$field] = ['from' => $before[$field], 'to' => $after[$field]];
                }
            }

            // National ID and phone are never written to the audit log (ADR-006): field names only.
            $this->recordActivity->execute(
                ActivityEvent::PlayerUpdated,
                $player,
                [
                    'changes' => $changes,
                    'sensitive_fields_changed' => array_values(array_intersect(['national_id', 'phone'], $changed)),
                ],
                null,
                null,
            );

            return $player;
        });
    }

    /**
     * @return array<string, string|null>
     */
    private function snapshot(Player $player): array
    {
        return [
            'name' => $player->name,
            'national_id' => $player->national_id,
            'birth_date' => $player->birth_date->toDateString(),
            'gender' => $player->gender->value,
            'phone' => $player->phone,
        ];
    }
}
