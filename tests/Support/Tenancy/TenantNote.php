<?php

namespace Tests\Support\Tenancy;

use App\Domain\Shared\Tenancy\BelongsToClub;
use Illuminate\Database\Eloquent\Model;

class TenantNote extends Model
{
    use BelongsToClub;

    protected $table = 'tenant_notes';

    protected $fillable = ['title'];
}
