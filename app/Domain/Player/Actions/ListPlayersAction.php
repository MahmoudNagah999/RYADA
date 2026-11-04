<?php

namespace App\Domain\Player\Actions;

use App\Domain\Player\DTOs\ListPlayersData;
use App\Domain\Player\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

class ListPlayersAction
{
    /**
     * The sort column comes from a validated whitelist (see ListPlayersRequest).
     *
     * @return LengthAwarePaginator<int, Player>
     */
    public function execute(ListPlayersData $data): LengthAwarePaginator
    {
        $query = Player::query();

        if ($data->search !== null) {
            $term = addcslashes($data->search, '\\%_');

            $query->where(function (Builder $q) use ($term, $data): void {
                $q->where('name', 'like', "%{$term}%")
                    ->orWhere('phone', 'like', "{$term}%")
                    ->orWhere('national_id', $data->search);
            });
        }

        if ($data->gender !== null) {
            $query->where('gender', $data->gender->value);
        }

        if ($data->ageMin !== null) {
            $query->where('birth_date', '<=', CarbonImmutable::today()->subYears($data->ageMin)->toDateString());
        }

        if ($data->ageMax !== null) {
            $query->where('birth_date', '>', CarbonImmutable::today()->subYears($data->ageMax + 1)->toDateString());
        }

        return $query
            ->orderBy($data->sortColumn, $data->sortDirection)
            ->orderBy('id')
            ->paginate($data->perPage);
    }
}
