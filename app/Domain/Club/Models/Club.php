<?php

namespace App\Domain\Club\Models;

use App\Domain\Club\Enums\ClubStatus;
use App\Domain\Club\Policies\ClubPolicy;
use App\Models\User;
use Database\Factories\ClubFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property ClubStatus $status
 * @property Carbon|null $suspended_at
 */
#[UseFactory(ClubFactory::class)]
#[UsePolicy(ClubPolicy::class)]
class Club extends Model
{
    /** @use HasFactory<ClubFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'status' => ClubStatus::class,
            'suspended_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function isActive(): bool
    {
        return $this->status === ClubStatus::Active;
    }

    public function isSuspended(): bool
    {
        return $this->status === ClubStatus::Suspended;
    }
}
