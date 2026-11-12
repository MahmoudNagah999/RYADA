<?php

namespace App\Domain\Sport\Cache;

use App\Domain\Sport\Models\Sport;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class SportCatalogObserver implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly SportCatalog $catalog) {}

    public function saved(Sport $sport): void
    {
        $this->catalog->forget();
    }

    public function deleted(Sport $sport): void
    {
        $this->catalog->forget();
    }
}
