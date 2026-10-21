<?php

namespace App\Providers;

use App\Domain\Shared\Tenancy\CurrentClub;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register CurrentClub as a scoped service not singleton, so each request/queue workers gets its own instance.
        $this->app->scoped(CurrentClub::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
