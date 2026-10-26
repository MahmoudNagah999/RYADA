<?php

namespace App\Domain\Club\Actions;

use App\Domain\Auth\Actions\SyncClubRolesAction;
use App\Domain\Club\Models\Club;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CreateClubAction
{
    public function __construct(private readonly SyncClubRolesAction $syncClubRoles) {}

    public function execute(string $name): Club
    {
        return DB::transaction(function () use ($name) {
            $club = Club::create([
                'name' => $name,
                'slug' => $this->uniqueSlug($name),
            ]);

            $this->syncClubRoles->execute($club);

            return $club;
        });
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
