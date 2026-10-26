<?php

namespace App\Domain\Subscription\Models;

use App\Domain\Subscription\Enums\PlanLimit;
use Database\Factories\PlanFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * @property int|null $max_players
 * @property int|null $max_coaches
 * @property int $price_cents
 * @property int $duration_months
 * @property bool $is_active
 */
#[UseFactory(PlanFactory::class)]
class Plan extends Model
{
    /** @use HasFactory<PlanFactory> */
    use HasFactory;

    use HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $fillable = [
        'slug',
        'name',
        'max_players',
        'max_coaches',
        'price_cents',
        'currency',
        'duration_months',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'max_players' => 'integer',
            'max_coaches' => 'integer',
            'price_cents' => 'integer',
            'duration_months' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** Null means unlimited. */
    public function limitFor(PlanLimit $limit): ?int
    {
        $value = $this->getAttribute($limit->column());

        return $value === null ? null : (int) $value;
    }
}
