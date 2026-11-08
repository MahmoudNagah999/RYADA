<?php

namespace App\Domain\Payment\Models;

use App\Domain\Payment\Enums\DocumentKind;
use App\Domain\Shared\Tenancy\BelongsToClub;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Per-club counter used to allocate receipt and refund numbers (tenant-owned).
 * Read and incremented inside the caller's transaction through CurrentClub.
 *
 * @property int $id
 * @property int $club_id
 * @property DocumentKind $kind
 * @property int $last_number
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
class DocumentSequence extends Model
{
    use BelongsToClub;

    /**
     * club_id is not mass-assignable: BelongsToClub fills it from CurrentClub.
     *
     * @var list<string>
     */
    protected $fillable = ['kind', 'last_number'];

    protected function casts(): array
    {
        return [
            'kind' => DocumentKind::class,
            'last_number' => 'integer',
        ];
    }
}
