<?php

namespace App\Domain\Sport\Models;

use Database\Factories\SportFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

/**
 * Global catalog entry (not tenant-owned, no BelongsToClub). See ADR-007.
 *
 * @property int $id
 * @property string $slug
 * @property bool $is_active
 */
#[UseFactory(SportFactory::class)]
class Sport extends Model
{
    use HasFactory, HasTranslations;

    /** @var list<string> */
    public array $translatable = ['name'];

    protected $fillable = ['slug', 'name', 'is_active'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }
}
