<?php

namespace App\Domain\Staff\Actions;

use App\Domain\Staff\DTOs\ListStaffData;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class ListStaffAction
{
    public function execute(User $user, ListStaffData $data): LengthAwarePaginator
    {
        $query = User::query()
            ->where('club_id', $user->club_id)
            ->with('roles');

        if ($data->role !== null) {
            $role = $data->role->value;

            $query->whereHas('roles', fn ($roles) => $roles->where('roles.name', $role));
        }

        if ($data->search !== null && $data->search !== '') {
            $search = '%'.$data->search.'%';

            $query->where(fn ($users) => $users
                ->where('name', 'like', $search)
                ->orWhere('email', 'like', $search));
        }

        return $query
            ->orderBy($data->sort, $data->direction)
            ->orderBy('id')
            ->paginate($data->perPage, ['users.*'], 'page', $data->page);
    }
}
