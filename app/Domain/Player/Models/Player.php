<?php

namespace App\Domain\Player\Models;

use App\Domain\Player\Enums\Gender;
use App\Domain\Player\Policies\PlayerPolicy;
use App\Domain\Shared\Tenancy\BelongsToClub;
use Carbon\CarbonInterface;
use Database\Factories\PlayerFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $club_id
 * @property string $name
 * @property string|null $national_id
 * @property CarbonInterface $birth_date
 * @property Gender $gender
 * @property string|null $phone
 * @property CarbonInterface|null $created_at
 * @property CarbonInterface|null $updated_at
 */
#[UsePolicy(PlayerPolicy::class)]
#[UseFactory(PlayerFactory::class)]
class Player extends Model
{
    use BelongsToClub;
    use HasFactory;

    /**
     * club_id is not mass-assignable: BelongsToClub fills it from CurrentClub.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'national_id',
        'birth_date',
        'gender',
        'phone',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'birth_date' => 'date',
            'gender' => Gender::class,
        ];
    }

    public function age(): int
    {
        return $this->birth_date->age;
    }
}
