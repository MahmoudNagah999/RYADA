<?php

namespace App\Domain\Club\Models;

use App\Domain\Club\Policies\ClubPolicy;
use Database\Factories\ClubFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

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
}
